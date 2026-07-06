<?php
namespace Combipower\TessAI\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;

class OrderQuantityResolver
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var array<int, float>
     */
    private $cache = [];

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Aggregate ordered quantity for a product across non-canceled orders.
     *
     * Counts only top-level rows (parent_item_id IS NULL) so configurable parents
     * do not double-count with their child rows. Per-row math is
     * qty_ordered - qty_canceled - qty_refunded.
     *
     * @param int $productId
     * @return float
     */
    public function resolve($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return 0.0;
        }

        if (!array_key_exists($productId, $this->cache)) {
            $this->preload([$productId]);
        }

        return $this->cache[$productId] ?? 0.0;
    }

    /**
     * Warm the request-scope cache for a set of product ids in one grouped
     * query, so mapping a product list page does not run one aggregate per
     * product. Ids with no matching order rows are cached as 0.0.
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

        // Default first: a query failure must not leave ids uncached and
        // trigger one retry per resolve() call.
        foreach ($missingIds as $productId) {
            $this->cache[$productId] = 0.0;
        }

        try {
            $connection = $this->resource->getConnection();
            $orderItemTable = $this->resource->getTableName('sales_order_item');
            $orderTable = $this->resource->getTableName('sales_order');

            $select = $connection->select()
                ->from(['oi' => $orderItemTable], ['product_id' => 'oi.product_id'])
                ->joinInner(['o' => $orderTable], 'o.entity_id = oi.order_id', [])
                ->where('oi.product_id IN (?)', array_values($missingIds))
                ->where('oi.parent_item_id IS NULL')
                ->where('o.state != ?', Order::STATE_CANCELED)
                ->columns(['ordered' => 'SUM(oi.qty_ordered - oi.qty_canceled - oi.qty_refunded)'])
                ->group('oi.product_id');

            foreach ($connection->fetchPairs($select) as $productId => $ordered) {
                $this->cache[(int) $productId] = (float) $ordered;
            }
        } catch (\Throwable $exception) {
            return;
        }
    }
}
