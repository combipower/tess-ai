<?php
namespace Combipower\TessAI\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Channable\Helper\Source as ChannableSourceHelper;
use Magmodules\Channable\Model\Collection\Products as ChannableProducts;
use Psr\Log\LoggerInterface;

/**
 * Derive the Channable push status of a product.
 *
 * The source of truth is Channable itself, so nothing is persisted here: the
 * feed filters decide whether a product is listed at all, and `channable_items`
 * records what happened on the last push. Both are read per request page and
 * cached in memory, mirroring StockQtyResolver / OrderQuantityResolver.
 */
class ChannableStatusResolver
{
    /**
     * Product does not match the Channable feed filters at all.
     */
    public const STATUS_NOT_LISTED = 'not_listed';

    /**
     * Listed, but an admin excluded it from the incremental item update.
     */
    public const STATUS_EXCLUDED = 'excluded';

    /**
     * The last push was rejected by Channable or failed at transport level.
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Listed and waiting: either Channable has never pulled it, or there are
     * local changes not pushed yet.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Listed and up to date — Channable holds the current data.
     */
    public const STATUS_SYNCED = 'synced';

    private const MODULE = 'Magmodules_Channable';

    private const ITEMS_TABLE = 'channable_items';

    /**
     * Value Channable stores in `channable_items.status` on a successful call.
     */
    private const CALL_STATUS_SUCCESS = 'success';

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ChannableSourceHelper
     */
    private $channableSourceHelper;

    /**
     * @var ChannableProducts
     */
    private $channableProducts;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array<int, string|null>
     */
    private $cache = [];

    /**
     * @var array<int, array>
     */
    private $configCache = [];

    /**
     * @var bool|null
     */
    private $enabled = null;

    public function __construct(
        ModuleManager $moduleManager,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        ChannableSourceHelper $channableSourceHelper,
        ChannableProducts $channableProducts,
        LoggerInterface $logger
    ) {
        $this->moduleManager = $moduleManager;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->channableSourceHelper = $channableSourceHelper;
        $this->channableProducts = $channableProducts;
        $this->logger = $logger;
    }

    /**
     * @param int $productId
     * @return string|null
     */
    public function getStatus($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return null;
        }

        if (!array_key_exists($productId, $this->cache)) {
            $this->preload([$productId]);
        }

        return $this->cache[$productId] ?? null;
    }

    /**
     * Warm the request-scope cache for a set of product ids with two queries:
     * one asking Channable which ids its own feed collection would return, one
     * reading the push bookkeeping. Ids stay uncached-as-null when the Channable
     * module is unavailable, so the field is simply omitted from the response.
     *
     * @param int[] $productIds
     * @return void
     */
    public function preload(array $productIds)
    {
        $missingIds = [];
        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId > 0 && !array_key_exists($productId, $this->cache)) {
                $missingIds[$productId] = $productId;
            }
        }

        if (empty($missingIds)) {
            return;
        }

        // Default first: an unavailable module or a failed query must not leave
        // ids uncached and trigger one retry per getStatus() call.
        foreach ($missingIds as $productId) {
            $this->cache[$productId] = null;
        }

        if (!$this->isChannableEnabled()) {
            return;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $listedIds = $this->fetchListedIds($storeId, $missingIds);
            $itemRows = $this->fetchItemRows($storeId, $missingIds);

            foreach ($missingIds as $productId) {
                $this->cache[$productId] = $this->resolveStatus(
                    isset($listedIds[$productId]),
                    $itemRows[$productId] ?? null
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Combipower_TessAI: Channable status lookup failed', [
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Ask Channable's own collection which of the given ids pass the configured
     * feed filters (category / type / status / visibility). Reusing it instead
     * of re-implementing the filters keeps this in step with Channable upgrades.
     *
     * getCollection() returns an unloaded collection, so getAllIds() issues a
     * lean `SELECT e.entity_id` instead of hydrating the full feed row set.
     *
     * @param int $storeId
     * @param array<int, int> $productIds
     * @return array<int, bool>
     */
    private function fetchListedIds($storeId, array $productIds)
    {
        $config = $this->getChannableConfig($storeId);
        $collection = $this->channableProducts->getCollection($config, '', array_values($productIds));

        $listedIds = [];
        foreach ($collection->getAllIds() as $entityId) {
            $listedIds[(int) $entityId] = true;
        }

        return $listedIds;
    }

    /**
     * Read the push bookkeeping rows keyed by product id.
     *
     * `channable_items` has no index on `id`, but `item_id` is the primary key
     * and Channable derives it as store id concatenated with the zero-padded
     * product id (see Magmodules\Channable\Model\Item::add). Building the ids
     * turns this into a primary key lookup. The concatenation is ambiguous for
     * product ids above 8 digits, so the returned rows are verified against the
     * store id and product id they actually carry.
     *
     * @param int $storeId
     * @param array<int, int> $productIds
     * @return array<int, array>
     */
    private function fetchItemRows($storeId, array $productIds)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::ITEMS_TABLE);
        if (!$connection->isTableExists($table)) {
            return [];
        }

        $itemIds = [];
        foreach ($productIds as $productId) {
            $itemIds[] = $storeId . sprintf('%08d', $productId);
        }

        $select = $connection->select()
            ->from($table, ['item_id', 'store_id', 'id', 'needs_update', 'exclude_for_update', 'status'])
            ->where('item_id IN (?)', $itemIds);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rowProductId = (int) $row['id'];
            if ((int) $row['store_id'] !== $storeId || !isset($productIds[$rowProductId])) {
                continue;
            }

            $rows[$rowProductId] = $row;
        }

        return $rows;
    }

    /**
     * Map the raw signals onto a status. Order matters — the first matching
     * condition wins.
     *
     * @param bool $isListed
     * @param array|null $itemRow
     * @return string
     */
    private function resolveStatus($isListed, $itemRow)
    {
        if (!$isListed) {
            return self::STATUS_NOT_LISTED;
        }

        if ($itemRow === null) {
            // Matches the feed filters but Channable has not pulled it yet.
            return self::STATUS_PENDING;
        }

        if ((int) $itemRow['exclude_for_update'] === 1) {
            return self::STATUS_EXCLUDED;
        }

        if ($this->isFailedCall($itemRow['status'])) {
            return self::STATUS_FAILED;
        }

        if ((int) $itemRow['needs_update'] === 1) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_SYNCED;
    }

    /**
     * Channable writes both the per-item status returned by its API and the
     * request-level outcome (`error`, `exception`, `unauthorized`, ...) into the
     * same column, so success is whitelisted rather than failures blacklisted.
     * A null/empty value means the row has never been pushed, which is not a
     * failure.
     *
     * @param mixed $status
     * @return bool
     */
    private function isFailedCall($status)
    {
        if ($status === null || trim((string) $status) === '') {
            return false;
        }

        return strtolower(trim((string) $status)) !== self::CALL_STATUS_SUCCESS;
    }

    /**
     * @param int $storeId
     * @return array
     */
    private function getChannableConfig($storeId)
    {
        if (!isset($this->configCache[$storeId])) {
            $this->configCache[$storeId] = $this->channableSourceHelper->getConfig($storeId, 'api');
        }

        return $this->configCache[$storeId];
    }

    /**
     * @return bool
     */
    private function isChannableEnabled()
    {
        if ($this->enabled === null) {
            $this->enabled = $this->moduleManager->isEnabled(self::MODULE);
        }

        return $this->enabled;
    }
}
