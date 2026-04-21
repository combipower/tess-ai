<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Tess\PricingTool\Api\Data\ProductInterface;
use Tess\PricingTool\Model\Data\ProductFactory;
use Tess\PricingTool\Model\Data\SaleUnitFactory;

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

    public function __construct(
        ProductFactory $productFactory,
        SaleUnitFactory $saleUnitFactory,
        StockRegistryInterface $stockRegistry,
        AttributeProvider $attributeProvider
    ) {
        $this->productFactory = $productFactory;
        $this->saleUnitFactory = $saleUnitFactory;
        $this->stockRegistry = $stockRegistry;
        $this->attributeProvider = $attributeProvider;
    }

    /**
     * @param Product $catalogProduct
     * @param string|null $forcedCategoryId
     * @return ProductInterface
     */
    public function map(Product $catalogProduct, $forcedCategoryId = null)
    {
        $stockQty = $this->resolveStockQty($catalogProduct);
        $categoryIds = $catalogProduct->getCategoryIds();
        $categoryId = $forcedCategoryId ?: (!empty($categoryIds) ? (string) reset($categoryIds) : null);
        $barcodeAttributeCode = $this->attributeProvider->getBarcodeAttributeCode();
        $manufacturerNumberAttributeCode = $this->attributeProvider->getManufacturerNumberAttributeCode();
        $brandAttributeCode = $this->attributeProvider->getBrandAttributeCode();
        $deliveryTimeAttributeCode = $this->attributeProvider->getDeliveryTimeAttributeCode();
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
                $saleUnits[] = $this->saleUnitFactory->create()
                    ->setId($unitId)
                    ->setSaleId($unitId)
                    ->setLabel($this->resolveSaleUnitLabel($unitLabel, $unitAmount))
                    ->setValue($unitPriceExclVat)
                    ->setCurrency($currencyCode)
                    ->setPurchasePriceExclVat($this->resolveScaledValue($catalogProduct->getCost(), $unitAmount))
                    ->setAvailableStock($stockQty);
            }
        }

        $barcodeValue = $this->normalizeString(
            $this->attributeProvider->getProductAttributeValue($catalogProduct, $barcodeAttributeCode)
        );

        return $this->productFactory->create()
            ->setId((string) $catalogProduct->getSku())
            ->setArticleNumber((string) $catalogProduct->getSku())
            ->setBarcode($barcodeValue)
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
            ->setDeliveryTime(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue($catalogProduct, $deliveryTimeAttributeCode)
                )
            )
            ->setProductType($this->normalizeString($catalogProduct->getTypeId()))
            ->setPrice($priceValues)
            ->setCategoryId($categoryId)
            ->setSaleUnits($saleUnits);
    }

    /**
     * @param Product $catalogProduct
     * @return float|null
     */
    private function resolveStockQty(Product $catalogProduct)
    {
        if ($catalogProduct->getData('qty') !== null) {
            return $this->normalizeDecimal($catalogProduct->getData('qty'));
        }

        try {
            $stockItem = $this->stockRegistry->getStockItemBySku($catalogProduct->getSku());
            if ($stockItem) {
                return $this->normalizeDecimal($stockItem->getQty());
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    /**
     * @param mixed $baseValue
     * @param float $unitAmount
     * @return float|null
     */
    private function resolveScaledValue($baseValue, $unitAmount)
    {
        $normalizedBaseValue = $this->normalizeDecimal($baseValue);
        if ($normalizedBaseValue === null) {
            return null;
        }

        return $this->normalizeDecimal($normalizedBaseValue * $unitAmount);
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
     * For configurable products, return sale_units from child variants.
     *
     * @param Product $catalogProduct
     * @param string|null $currencyCode
     * @param string|null $unitAttributeCode
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface[]
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

            $saleUnits[] = $this->saleUnitFactory->create()
                ->setId($childSku)
                ->setSaleId($childSku)
                ->setLabel($this->resolveConfigurableSaleUnitLabel($childProduct, $unitAttributeCode, $childSku))
                ->setValue($this->normalizeDecimal($childProduct->getPrice()))
                ->setCurrency($currencyCode)
                ->setPurchasePriceExclVat($this->normalizeDecimal($childProduct->getCost()))
                ->setAvailableStock($this->resolveStockQty($childProduct));
        }

        return $saleUnits;
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
     * @param Product $catalogProduct
     * @return Product[]
     */
    private function getConfigurableChildren(Product $catalogProduct)
    {
        try {
            $typeInstance = $catalogProduct->getTypeInstance();
            if (!is_object($typeInstance) || !method_exists($typeInstance, 'getUsedProducts')) {
                return [];
            }

            $children = $typeInstance->getUsedProducts($catalogProduct);
            if (!is_array($children)) {
                return [];
            }
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
        return array_values($result);
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
