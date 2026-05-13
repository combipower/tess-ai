<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\ObjectManager;
use Combipower\TessAI\Api\Data\ProductInterface;
use Combipower\TessAI\Api\DeliveryTimeProviderInterface;
use Combipower\TessAI\Model\Data\ProductFactory;
use Combipower\TessAI\Model\Data\SaleUnitFactory;

class ProductMapper
{
    private const CONFIGURABLE_PRODUCT_TYPE = 'configurable';

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var SaleUnitFactory
     */
    private $saleUnitFactory;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var AttributeProvider
     */
    private $attributeProvider;

    /**
     * @var CatalogHelper
     */
    private $catalogHelper;

    /**
     * @var ShippingCostResolver
     */
    private $shippingCostResolver;

    /**
     * @var DeliveryTimeProviderInterface
     */
    private $deliveryTimeProvider;

    /**
     * @var OrderQuantityResolver
     */
    private $orderQuantityResolver;

    /**
     * @var StockQtyResolver
     */
    private $stockQtyResolver;

    /**
     * @var array<int, Product[]>
     */
    private $configurableChildrenCache = [];

    public function __construct(
        ProductFactory $productFactory,
        SaleUnitFactory $saleUnitFactory,
        StockRegistryInterface $stockRegistry,
        AttributeProvider $attributeProvider,
        CatalogHelper $catalogHelper,
        ShippingCostResolver $shippingCostResolver = null,
        DeliveryTimeProviderInterface $deliveryTimeProvider = null,
        OrderQuantityResolver $orderQuantityResolver = null,
        StockQtyResolver $stockQtyResolver = null
    ) {
        $this->productFactory = $productFactory;
        $this->saleUnitFactory = $saleUnitFactory;
        $this->stockRegistry = $stockRegistry;
        $this->attributeProvider = $attributeProvider;
        $this->catalogHelper = $catalogHelper;
        $this->shippingCostResolver = $shippingCostResolver;
        $this->deliveryTimeProvider = $deliveryTimeProvider;
        $this->orderQuantityResolver = $orderQuantityResolver;
        $this->stockQtyResolver = $stockQtyResolver;
    }

    /**
     * @param Product $catalogProduct
     * @param mixed $forcedCategoryId
     * @return ProductInterface
     */
    public function map(Product $catalogProduct, $forcedCategoryId = null)
    {
        $stockQty = $this->resolveStockQty($catalogProduct);
        $categoryId = $this->resolveCategoryId($catalogProduct, $forcedCategoryId);
        $barcodeAttributeCode = $this->attributeProvider->getBarcodeAttributeCode();
        $manufacturerNumberAttributeCode = $this->attributeProvider->getManufacturerNumberAttributeCode();
        $brandAttributeCode = $this->attributeProvider->getBrandAttributeCode();
        $unitAttributeCode = $this->attributeProvider->getUnitAttributeCode();
        $unitLabel = $this->attributeProvider->getProductAttributeValue($catalogProduct, $unitAttributeCode);
        $currencyCode = $this->resolveCurrencyCode($catalogProduct);
        $tierPriceRows = $this->resolveTierPriceRows($catalogProduct);
        $priceValues = $this->resolvePriceValues($catalogProduct);

        $saleUnits = $catalogProduct->getTypeId() === self::CONFIGURABLE_PRODUCT_TYPE
            ? $this->resolveConfigurableSaleUnits(
                $catalogProduct,
                $currencyCode,
                $unitAttributeCode
            )
            : [];

        if (empty($saleUnits)) {
            foreach ($tierPriceRows as $tierPriceRow) {
                $unitAmount = $tierPriceRow['qty'];
                $unitId = $this->formatUnitAmount($unitAmount);
                $unitPriceExclVat = $tierPriceRow['price'];
                $purchasePrice = $this->normalizeDecimal($catalogProduct->getCost());
                $saleUnits[] = $this->saleUnitFactory->create()
                    ->setId($unitId)
                    ->setSaleId($unitId)
                    ->setLabel($this->resolveSaleUnitLabel($unitLabel, $unitAmount))
                    ->setValue($unitPriceExclVat)
                    ->setCurrency($currencyCode)
                    ->setCurrentSalesPriceExclVat($unitPriceExclVat)
                    ->setCurrentSalesPriceInclVat(
                        $this->resolveProductPriceInclVat($catalogProduct, $unitPriceExclVat)
                    )
                    ->setPurchasePrice($purchasePrice)
                    ->setPurchasePriceExclVat($purchasePrice)
                    ->setPurchasePriceInclVat(
                        $this->applyTaxToPrice($catalogProduct, $purchasePrice)
                    )
                    ->setShippingCost($this->getShippingCostResolver()->resolve($catalogProduct, $unitAmount))
                    ->setAvailableStock($stockQty);
            }
        }

        $barcodeValue = $this->normalizeString(
            $this->attributeProvider->getProductAttributeValue($catalogProduct, $barcodeAttributeCode)
        );

        return $this->productFactory->create()
            ->setId((string) $catalogProduct->getId())
            ->setArticleNumber((string) $catalogProduct->getSku())
            ->setBarcode($barcodeValue)
            ->setEan($barcodeValue)
            ->setManufacturerNumber(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue(
                        $catalogProduct,
                        $manufacturerNumberAttributeCode
                    )
                )
            )
            ->setDescription($this->resolveDescription($catalogProduct))
            ->setBrandDge(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue($catalogProduct, $brandAttributeCode)
                )
            )
            ->setArticleGroup(null)
            ->setDeliveryTime(
                $this->resolveDeliveryTime($catalogProduct)
            )
            ->setProductType($this->normalizeString($catalogProduct->getTypeId()))
            ->setPrice($priceValues)
            ->setSpecialPrice($this->normalizeDecimal($catalogProduct->getSpecialPrice()))
            ->setOrderNumber($this->resolveOrderQuantity($catalogProduct))
            ->setCategoryId($categoryId)
            ->setSaleUnits($saleUnits);
    }

    /**
     * @param Product $catalogProduct
     * @return float
     */
    private function resolveOrderQuantity(Product $catalogProduct)
    {
        try {
            return $this->getOrderQuantityResolver()->resolve((int) $catalogProduct->getId());
        } catch (\Throwable $exception) {
            return 0.0;
        }
    }

    /**
     * @return OrderQuantityResolver
     */
    private function getOrderQuantityResolver()
    {
        if ($this->orderQuantityResolver === null) {
            $this->orderQuantityResolver = ObjectManager::getInstance()->get(OrderQuantityResolver::class);
        }

        return $this->orderQuantityResolver;
    }

    /**
     * Resolve raw physical stock qty for a product. Routes through
     * StockQtyResolver, which auto-detects MSI vs legacy storage. Bypasses
     * `getData('qty')` and StockRegistry so any third-party plugin override
     * (notably Magento_Inventory plugins) cannot inject synthetic values.
     *
     * @param Product $catalogProduct
     * @return float|null
     */
    private function resolveStockQty(Product $catalogProduct)
    {
        $sku = $this->normalizeString($catalogProduct->getSku());
        if ($sku === null) {
            return null;
        }

        try {
            return $this->getStockQtyResolver()->getQty($sku);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @return StockQtyResolver
     */
    private function getStockQtyResolver()
    {
        if ($this->stockQtyResolver === null) {
            $this->stockQtyResolver = ObjectManager::getInstance()->get(StockQtyResolver::class);
        }

        return $this->stockQtyResolver;
    }

    /**
     * @param Product $catalogProduct
     * @param mixed $forcedCategoryId
     * @return string|null
     */
    private function resolveCategoryId(Product $catalogProduct, $forcedCategoryId = null)
    {
        $productCategoryIds = array_map('strval', $catalogProduct->getCategoryIds() ?: []);
        $forcedCategoryIds = $this->normalizeCategoryIds($forcedCategoryId);

        if (!empty($forcedCategoryIds)) {
            foreach ($forcedCategoryIds as $categoryId) {
                if (in_array((string) $categoryId, $productCategoryIds, true)) {
                    return (string) $categoryId;
                }
            }

            return (string) reset($forcedCategoryIds);
        }

        return !empty($productCategoryIds) ? (string) reset($productCategoryIds) : null;
    }

    /**
     * @param mixed $categoryId
     * @return int[]
     */
    private function normalizeCategoryIds($categoryId)
    {
        $values = [];
        $this->collectCategoryIdValues($categoryId, $values);

        $ids = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $id = (int) $part;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $value
     * @param array $values
     * @return void
     */
    private function collectCategoryIdValues($value, array &$values)
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectCategoryIdValues($item, $values);
            }
            return;
        }

        if (is_scalar($value)) {
            $values[] = $value;
        }
    }

    /**
     * Build sale-unit rows from base price + tier prices.
     *
     * @param Product $catalogProduct
     * @return array[]
     */
    private function resolveTierPriceRows(Product $catalogProduct)
    {
        $rows = [
            '1' => [
                'qty' => 1.0,
                'price' => $this->normalizeDecimal($catalogProduct->getPrice()),
            ],
        ];

        $tierPrices = $catalogProduct->getTierPrice();
        if (!is_array($tierPrices)) {
            return array_values($rows);
        }

        foreach ($tierPrices as $tierPrice) {
            if (!is_array($tierPrice)) {
                continue;
            }

            $qty = isset($tierPrice['price_qty']) ? (float) $tierPrice['price_qty'] : 0.0;
            if ($qty <= 0.0) {
                continue;
            }

            $qtyKey = $this->formatUnitAmount($qty);
            $price = null;
            if (array_key_exists('website_price', $tierPrice)) {
                $price = $this->normalizeDecimal($tierPrice['website_price']);
            } elseif (array_key_exists('price', $tierPrice)) {
                $price = $this->normalizeDecimal($tierPrice['price']);
            }

            if (!isset($rows[$qtyKey])) {
                $rows[$qtyKey] = [
                    'qty' => (float) $qtyKey,
                    'price' => $price,
                ];
                continue;
            }

            $currentPrice = $rows[$qtyKey]['price'];
            if ($currentPrice === null || ($price !== null && $price < $currentPrice)) {
                $rows[$qtyKey]['price'] = $price;
            }
        }

        uksort($rows, 'strnatcmp');
        return array_values($rows);
    }

    /**
     * Build product-level price list.
     *
     * Simple products expose one base price.
     * Configurable products expose distinct child prices.
     *
     * @param Product $catalogProduct
     * @return float[]
     */
    private function resolvePriceValues(Product $catalogProduct)
    {
        if ($catalogProduct->getTypeId() === self::CONFIGURABLE_PRODUCT_TYPE) {
            $configurablePrices = $this->resolveConfigurableChildPrices($catalogProduct);
            if (!empty($configurablePrices)) {
                return $configurablePrices;
            }
        }

        $basePrice = $this->normalizeDecimal($catalogProduct->getPrice());
        if ($basePrice === null) {
            return [];
        }

        return [$basePrice];
    }

    /**
     * @param Product $catalogProduct
     * @return float[]
     */
    private function resolveConfigurableChildPrices(Product $catalogProduct)
    {
        $prices = [];
        $children = $this->getConfigurableChildren($catalogProduct);
        if (empty($children)) {
            return [];
        }

        foreach ($children as $childProduct) {
            $price = $this->normalizeDecimal($childProduct->getPrice());
            if ($price === null) {
                continue;
            }

            $prices[sprintf('%.4F', $price)] = $price;
        }

        ksort($prices, SORT_NATURAL);
        return array_values($prices);
    }

    /**
     * @param Product $catalogProduct
     * @param mixed $price
     * @return float|null
     */
    private function resolveProductPriceInclVat(Product $catalogProduct, $price = null)
    {
        $price = $this->normalizeDecimal($price);
        if ($price === null) {
            $price = $this->normalizeDecimal($catalogProduct->getFinalPrice());
            if ($price === null) {
                $price = $this->normalizeDecimal($catalogProduct->getPrice());
            }
        }

        return $this->applyTaxToPrice($catalogProduct, $price);
    }

    /**
     * Apply product tax to a given excl-VAT price. Returns null when input is null.
     *
     * @param Product $catalogProduct
     * @param float|null $price
     * @return float|null
     */
    private function applyTaxToPrice(Product $catalogProduct, $price)
    {
        if ($price === null) {
            return null;
        }

        try {
            $store = $catalogProduct->getStore();
            $storeId = $store ? $store->getId() : null;
            return $this->normalizeDecimal(
                $this->catalogHelper->getTaxPrice(
                    $catalogProduct,
                    $price,
                    true,
                    null,
                    null,
                    null,
                    $storeId,
                    null,
                    true
                )
            );
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * For configurable products, return sale_units from child variants.
     *
     * @param Product $catalogProduct
     * @param string|null $currencyCode
     * @param string|null $unitAttributeCode
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface[]
     */
    private function resolveConfigurableSaleUnits(
        Product $catalogProduct,
        $currencyCode,
        $unitAttributeCode
    )
    {
        $saleUnits = [];
        $children = $this->getConfigurableChildren($catalogProduct);
        if (empty($children)) {
            return $saleUnits;
        }

        foreach ($children as $childProduct) {
            $childSku = (string) $childProduct->getSku();
            if ($childSku === '') {
                continue;
            }

            $purchasePrice = $this->normalizeDecimal($childProduct->getCost());
            $unitPriceExclVat = $this->normalizeDecimal($childProduct->getPrice());
            $saleUnits[] = $this->saleUnitFactory->create()
                ->setId($childSku)
                ->setSaleId($childSku)
                ->setLabel($this->resolveConfigurableSaleUnitLabel($childProduct, $unitAttributeCode, $childSku))
                ->setValue($unitPriceExclVat)
                ->setCurrency($currencyCode)
                ->setCurrentSalesPriceExclVat($unitPriceExclVat)
                ->setCurrentSalesPriceInclVat($this->resolveProductPriceInclVat($childProduct, $unitPriceExclVat))
                ->setPurchasePrice($purchasePrice)
                ->setPurchasePriceExclVat($purchasePrice)
                ->setPurchasePriceInclVat($this->applyTaxToPrice($childProduct, $purchasePrice))
                ->setShippingCost($this->getShippingCostResolver()->resolve($childProduct))
                ->setAvailableStock($this->resolveStockQty($childProduct));
        }

        return $saleUnits;
    }

    /**
     * @return ShippingCostResolver
     */
    private function getShippingCostResolver()
    {
        if ($this->shippingCostResolver === null) {
            $this->shippingCostResolver = ObjectManager::getInstance()->get(ShippingCostResolver::class);
        }

        return $this->shippingCostResolver;
    }

    /**
     * @param Product $catalogProduct
     * @return string|null
     */
    private function resolveDeliveryTime(Product $catalogProduct)
    {
        try {
            return $this->normalizeString($this->getDeliveryTimeProvider()->resolve($catalogProduct));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @return DeliveryTimeProviderInterface
     */
    private function getDeliveryTimeProvider()
    {
        if ($this->deliveryTimeProvider === null) {
            $this->deliveryTimeProvider = ObjectManager::getInstance()->get(DeliveryTimeProviderInterface::class);
        }

        return $this->deliveryTimeProvider;
    }

    /**
     * Resolve configurable child label.
     *
     * Priority:
     * 1) Configured unit attribute on child product
     * 2) Suffix from child name after " - "
     * 3) Full child name
     * 4) Child sku fallback
     *
     * @param Product $childProduct
     * @param string|null $unitAttributeCode
     * @param string $fallback
     * @return string
     */
    private function resolveConfigurableSaleUnitLabel(Product $childProduct, $unitAttributeCode, $fallback)
    {
        $unitLabel = $this->normalizeString(
            $this->attributeProvider->getProductAttributeValue($childProduct, $unitAttributeCode)
        );
        if ($unitLabel !== null) {
            return $unitLabel;
        }

        $childName = $this->normalizeString($childProduct->getName());
        if ($childName === null) {
            return $fallback;
        }

        $separator = ' - ';
        $separatorPosition = strrpos($childName, $separator);
        if ($separatorPosition !== false) {
            $suffix = trim(substr($childName, $separatorPosition + strlen($separator)));
            if ($suffix !== '') {
                return $suffix;
            }
        }

        return strip_tags($childName);
    }

    /**
     * Load configurable children with explicit attribute selection so cost,
     * special_price, tax_class_id and the configured unit attribute are
     * available on each child. Magento's default getUsedProducts() only loads
     * attributes flagged for product listing, which excludes cost.
     *
     * @param Product $catalogProduct
     * @return Product[]
     */
    private function getConfigurableChildren(Product $catalogProduct)
    {
        $productId = (int) $catalogProduct->getId();
        if ($productId > 0 && isset($this->configurableChildrenCache[$productId])) {
            return $this->configurableChildrenCache[$productId];
        }

        try {
            $typeInstance = $catalogProduct->getTypeInstance();
            if (!is_object($typeInstance) || !method_exists($typeInstance, 'getUsedProductCollection')) {
                return [];
            }

            $collection = $typeInstance->getUsedProductCollection($catalogProduct);
            $store = $catalogProduct->getStore();
            if ($store) {
                $collection->setStoreId($store->getId());
            }

            $attributesToSelect = array_values(array_unique(array_filter([
                'name',
                'price',
                'cost',
                'special_price',
                'tax_class_id',
                'type_id',
                $this->attributeProvider->getUnitAttributeCode(),
            ])));
            $collection->addAttributeToSelect($attributesToSelect);

            if (method_exists($collection, 'addFilterByRequiredOptions')) {
                $collection->addFilterByRequiredOptions();
            }

            $collection->joinField(
                'qty',
                'cataloginventory_stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );
            $collection->joinField(
                'is_in_stock',
                'cataloginventory_stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );

            $children = $collection->getItems();
        } catch (\Throwable $exception) {
            return [];
        }

        $result = [];
        foreach ($children as $childProduct) {
            if (!$childProduct instanceof Product) {
                continue;
            }

            $childSku = trim((string) $childProduct->getSku());
            if ($childSku === '') {
                continue;
            }

            $result[$childSku] = $childProduct;
        }

        ksort($result, SORT_NATURAL);
        $result = array_values($result);

        if ($productId > 0) {
            $this->configurableChildrenCache[$productId] = $result;
        }

        return $result;
    }

    /**
     * @param float $amount
     * @return string
     */
    private function formatUnitAmount($amount)
    {
        if ((float) (int) $amount === (float) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(sprintf('%.4F', (float) $amount), '0'), '.');
    }

    /**
     * @param mixed $unitLabel
     * @param float $unitAmount
     * @return string
     */
    private function resolveSaleUnitLabel($unitLabel, $unitAmount)
    {
        $normalizedUnitLabel = $this->normalizeString($unitLabel) ?: 'unit';
        if ((float) $unitAmount === 1.0) {
            return $normalizedUnitLabel;
        }

        return $this->formatUnitAmount($unitAmount) . ' x ' . $normalizedUnitLabel;
    }

    /**
     * @param Product $catalogProduct
     * @return string|null
     */
    private function resolveCurrencyCode(Product $catalogProduct)
    {
        $store = $catalogProduct->getStore();
        if (!$store) {
            return null;
        }

        $currencyCode = $store->getCurrentCurrencyCode();
        if ($currencyCode) {
            return (string) $currencyCode;
        }

        $baseCurrencyCode = $store->getBaseCurrencyCode();
        if ($baseCurrencyCode) {
            return (string) $baseCurrencyCode;
        }

        return null;
    }

    /**
     * API field "description" is mapped to product name.
     *
     * @param Product $catalogProduct
     * @return string|null
     */
    private function resolveDescription(Product $catalogProduct)
    {
        $name = $this->normalizeString($catalogProduct->getName());
        if ($name !== null) {
            return strip_tags($name);
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function normalizeDecimal($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function normalizeString($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
