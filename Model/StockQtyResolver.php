<?php
namespace Combipower\TessAI\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;

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
     * @var array<string, float|null>
     */
    private $cache = [];

    /**
     * @var bool|null
     */
    private $msiEnabled = null;

    public function __construct(
        ResourceConnection $resourceConnection,
        ModuleManager $moduleManager
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->moduleManager = $moduleManager;
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

        // MSI may report no row for SKUs that exist only in legacy storage —
        // fall back so we never silently lose data on partially-migrated shops.
        if ($qty === null && $this->isMsiEnabled()) {
            $qty = $this->getLegacyQty($sku);
        }

        $this->cache[$sku] = $qty;

        return $qty;
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
