<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Module\Manager as ModuleManager;
use Combipower\TessAI\Model\Config\StockFilter;

/**
 * Resolve physical stock qty for a SKU.
 *
 * Returns the raw inventory quantity — NOT salable qty (which subtracts
 * reservations). When MSI is enabled, sums across all sources where
 * status=1; otherwise reads the legacy cataloginventory_stock_item table.
 */
class StockQtyResolver
{
    private const MSI_MODULE = 'Magento_InventoryApi';
    private const LEGACY_STOCK_ID = 1;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var StockFilter
     */
    private $stockFilterConfig;

    /**
     * @var array<string, float|null>
     */
    private $cache = [];

    /**
     * @var bool|null
     */
    private $msiEnabled = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        ModuleManager $moduleManager,
        StockFilter $stockFilterConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->moduleManager = $moduleManager;
        $this->stockFilterConfig = $stockFilterConfig;
    }

    /**
     * @param string $sku
     * @return float|null
     */
    public function getQty($sku)
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return null;
        }

        if (array_key_exists($sku, $this->cache)) {
            return $this->cache[$sku];
        }

        $qty = $this->isMsiEnabled()
            ? $this->getMsiQty($sku)
            : $this->getLegacyQty($sku);

        // MSI may report no row for SKUs that exist only in legacy storage.
        // Fall back to the legacy qty only when the admin opts in, so this value
        // stays consistent with the `stock` filter (see applyInStockFilter()).
        if ($qty === null
            && $this->isMsiEnabled()
            && $this->stockFilterConfig->isLegacyFallbackEnabled()
        ) {
            $qty = $this->getLegacyQty($sku);
        }

        $this->cache[$sku] = $qty;

        return $qty;
    }

    /**
     * Warm the request-scope cache for a set of SKUs with one grouped query
     * (two when the legacy fallback applies), instead of one query per SKU
     * when mapping a product list page. Mirrors getQty() semantics exactly:
     * MSI sum where status=1, legacy qty only as opt-in fallback, null when
     * the SKU has no considered stock row.
     *
     * @param string[] $skus
     * @return void
     */
    public function preload(array $skus)
    {
        $missingSkus = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && !array_key_exists($sku, $this->cache)) {
                $missingSkus[$sku] = $sku;
            }
        }

        if (empty($missingSkus)) {
            return;
        }

        $resolved = $this->isMsiEnabled()
            ? $this->fetchMsiQtys($missingSkus)
            : [];

        $needsLegacy = !$this->isMsiEnabled()
            || $this->stockFilterConfig->isLegacyFallbackEnabled();
        if ($needsLegacy) {
            $legacySkus = array_diff_key($missingSkus, $resolved);
            if (!empty($legacySkus)) {
                $resolved += $this->fetchLegacyQtys($legacySkus);
            }
        }

        foreach ($missingSkus as $sku) {
            $this->cache[$sku] = array_key_exists($sku, $resolved) ? $resolved[$sku] : null;
        }
    }

    /**
     * @param array<string, string> $skus
     * @return array<string, float>
     */
    private function fetchMsiQtys(array $skus)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('inventory_source_item');
        if (!$connection->isTableExists($table)) {
            return [];
        }

        $select = $connection->select()
            ->from($table, ['sku', 'qty_sum' => new \Zend_Db_Expr('SUM(quantity)')])
            ->where('sku IN (?)', array_values($skus))
            ->where('status = ?', 1)
            ->group('sku');

        $qtys = [];
        foreach ($connection->fetchPairs($select) as $sku => $qty) {
            $qtys[(string) $sku] = (float) $qty;
        }

        return $qtys;
    }

    /**
     * @param array<string, string> $skus
     * @return array<string, float>
     */
    private function fetchLegacyQtys(array $skus)
    {
        $connection = $this->resourceConnection->getConnection();
        $stockItemTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from(['csi' => $stockItemTable], ['qty' => 'csi.qty'])
            ->join(
                ['cpe' => $productTable],
                'cpe.entity_id = csi.product_id',
                ['sku' => 'cpe.sku']
            )
            ->where('cpe.sku IN (?)', array_values($skus))
            ->where('csi.stock_id = ?', self::LEGACY_STOCK_ID);

        $qtys = [];
        foreach ($connection->fetchAll($select) as $row) {
            $qtys[(string) $row['sku']] = (float) $row['qty'];
        }

        return $qtys;
    }

    /**
     * Apply an in-stock / out-of-stock filter to a product collection using the
     * same physical-qty logic as getQty():
     *  - MSI enabled  → SUM(inventory_source_item.quantity) where status=1, per
     *                   SKU; optionally COALESCE with the legacy qty when the
     *                   admin enabled the legacy fallback.
     *  - MSI disabled → legacy cataloginventory_stock_item.qty (stock_id=1).
     *
     * The effective qty defaults to 0 when no stock row exists, so a SKU with no
     * (considered) inventory is treated as out of stock. Filtering happens at SQL
     * level so pagination and getSize() stay correct.
     *
     * @param ProductCollection $collection
     * @param bool $inStock True → keep qty > 0; false → keep qty <= 0.
     * @return void
     */
    public function applyInStockFilter(ProductCollection $collection, $inStock)
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $collection->getSelect();
        $msiTable = $this->resourceConnection->getTableName('inventory_source_item');

        $useMsi = $this->isMsiEnabled() && $connection->isTableExists($msiTable);

        if ($useMsi) {
            $msiAggregate = $connection->select()
                ->from(
                    ['isi' => $msiTable],
                    ['sku' => 'isi.sku', 'qty' => new \Zend_Db_Expr('SUM(isi.quantity)')]
                )
                ->where('isi.status = ?', 1)
                ->group('isi.sku');
            $select->joinLeft(
                ['tess_msi_stock' => $msiAggregate],
                'tess_msi_stock.sku = e.sku',
                []
            );

            if ($this->stockFilterConfig->isLegacyFallbackEnabled()) {
                $this->joinLegacyStock($select, $connection);
                $effectiveQty = 'COALESCE(tess_msi_stock.qty, tess_legacy_stock.qty, 0)';
            } else {
                $effectiveQty = 'COALESCE(tess_msi_stock.qty, 0)';
            }
        } else {
            $this->joinLegacyStock($select, $connection);
            $effectiveQty = 'COALESCE(tess_legacy_stock.qty, 0)';
        }

        $select->where($inStock ? $effectiveQty . ' > 0' : $effectiveQty . ' <= 0');
    }

    /**
     * Join the legacy default stock (stock_id=1) qty per product as
     * `tess_legacy_stock`. Pre-aggregated to one row per product so it never
     * multiplies the collection rows.
     *
     * @param Select $select
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return void
     */
    private function joinLegacyStock(Select $select, $connection)
    {
        $legacyTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $legacyStock = $connection->select()
            ->from(
                ['csi' => $legacyTable],
                ['product_id' => 'csi.product_id', 'qty' => 'csi.qty']
            )
            ->where('csi.stock_id = ?', self::LEGACY_STOCK_ID);
        $select->joinLeft(
            ['tess_legacy_stock' => $legacyStock],
            'tess_legacy_stock.product_id = e.entity_id',
            []
        );
    }

    /**
     * @return bool
     */
    private function isMsiEnabled()
    {
        if ($this->msiEnabled === null) {
            $this->msiEnabled = $this->moduleManager->isEnabled(self::MSI_MODULE);
        }

        return $this->msiEnabled;
    }

    /**
     * @param string $sku
     * @return float|null
     */
    private function getMsiQty($sku)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('inventory_source_item');
        if (!$connection->isTableExists($table)) {
            return null;
        }

        $select = $connection->select()
            ->from($table, ['qty_sum' => new \Zend_Db_Expr('SUM(quantity)')])
            ->where('sku = ?', $sku)
            ->where('status = ?', 1);

        $value = $connection->fetchOne($select);
        if ($value === false || $value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param string $sku
     * @return float|null
     */
    private function getLegacyQty($sku)
    {
        $connection = $this->resourceConnection->getConnection();
        $stockItemTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');

        $select = $connection->select()
            ->from(['csi' => $stockItemTable], ['qty' => 'csi.qty'])
            ->join(
                ['cpe' => $productTable],
                'cpe.entity_id = csi.product_id',
                []
            )
            ->where('cpe.sku = ?', $sku)
            ->where('csi.stock_id = ?', self::LEGACY_STOCK_ID);

        $value = $connection->fetchOne($select);
        if ($value === false || $value === null) {
            return null;
        }

        return (float) $value;
    }
}
