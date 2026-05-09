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

        try {
            $connection = $this->resource->getConnection();
            $orderItemTable = $this->resource->getTableName('sales_order_item');
            $orderTable = $this->resource->getTableName('sales_order');

            $select = $connection->select()
                ->from(['oi' => $orderItemTable], [])
                ->joinInner(['o' => $orderTable], 'o.entity_id = oi.order_id', [])
                ->where('oi.product_id = ?', $productId)
                ->where('oi.parent_item_id IS NULL')
                ->where('o.state != ?', Order::STATE_CANCELED)
                ->columns(['ordered' => 'SUM(oi.qty_ordered - oi.qty_canceled - oi.qty_refunded)']);

            $result = $connection->fetchOne($select);
        } catch (\Throwable $exception) {
            return 0.0;
        }

        if ($result === false || $result === null) {
            return 0.0;
        }

        return (float) $result;
    }
}
