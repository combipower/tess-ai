<?php
namespace Tess\PricingTool\Model;

use GbiVarpo\DeliveryTime\Helper\Data as DeliveryTimeHelper;
use GbiVarpo\Inventory\Helper\Data as InventoryHelper;
use Magento\Catalog\Model\Product;

class DeliveryTimeResolver
{
    private const CONFIGURABLE_PRODUCT_TYPE = 'configurable';

    /**
     * @var DeliveryTimeHelper
     */
    private $deliveryTimeHelper;

    /**
     * @var InventoryHelper
     */
    private $inventoryHelper;

    public function __construct(
        DeliveryTimeHelper $deliveryTimeHelper,
        InventoryHelper $inventoryHelper
    ) {
        $this->deliveryTimeHelper = $deliveryTimeHelper;
        $this->inventoryHelper = $inventoryHelper;
    }

    /**
     * @param Product $product
     * @return string|null
     */
    public function resolve(Product $product)
    {
        if (!$this->deliveryTimeHelper->isEnabled()) {
            return null;
        }

        $warehouse1Qty = $this->getWarehouseQty($product, $this->deliveryTimeHelper->getWarehouse1Source());
        $warehouse2Qty = $this->getWarehouseQty($product, $this->deliveryTimeHelper->getWarehouse2Source());
        $deliveryInfo = $this->deliveryTimeHelper->getDynamicDeliveryInfoByQty($warehouse1Qty, $warehouse2Qty);

        if (!empty($deliveryInfo['header'])) {
            return (string) $deliveryInfo['header'];
        }

        return null;
    }

    /**
     * @param Product $product
     * @param string $sourceCode
     * @return float
     */
    private function getWarehouseQty(Product $product, $sourceCode)
    {
        if (!$sourceCode) {
            return 0.0;
        }

        if ($product->getTypeId() === self::CONFIGURABLE_PRODUCT_TYPE) {
            $qty = 0.0;
            foreach ($this->getConfigurableChildren($product) as $childProduct) {
                $qty += $this->getSimpleWarehouseQty($childProduct, $sourceCode);
            }

            return $qty;
        }

        return $this->getSimpleWarehouseQty($product, $sourceCode);
    }

    /**
     * @param Product $product
     * @param string $sourceCode
     * @return float
     */
    private function getSimpleWarehouseQty(Product $product, $sourceCode)
    {
        $items = $this->inventoryHelper->getStockSourceItems($product);
        if (!isset($items[$sourceCode]['salableQty'])) {
            return 0.0;
        }

        return max(0.0, (float) $items[$sourceCode]['salableQty']);
    }

    /**
     * @param Product $product
     * @return Product[]
     */
    private function getConfigurableChildren(Product $product)
    {
        try {
            $children = $product->getTypeInstance()->getUsedProducts($product);
            if (!is_array($children)) {
                return [];
            }
        } catch (\Throwable $exception) {
            return [];
        }

        return array_filter(
            $children,
            static function ($childProduct) {
                return $childProduct instanceof Product;
            }
        );
    }
}
