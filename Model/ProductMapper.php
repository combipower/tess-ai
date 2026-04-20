<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Tess\PricingTool\Api\Data\ProductInterface;
use Tess\PricingTool\Model\Data\ProductFactory;
use Tess\PricingTool\Model\Data\SaleUnitFactory;

class ProductMapper
{
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
        $unitLabel = $this->attributeProvider->getProductAttributeValue($catalogProduct, AttributeProvider::UNIT_ATTRIBUTE);
        $currencyCode = $this->resolveCurrencyCode($catalogProduct);
        $tierPriceRows = $this->resolveTierPriceRows($catalogProduct);

        $saleUnits = [];
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
                ->setShippingCost(
                    $this->resolveScaledValue(
                        $this->attributeProvider->getProductAttributeValue(
                            $catalogProduct,
                            AttributeProvider::SHIPPING_COST_ATTRIBUTE
                        ),
                        $unitAmount
                    )
                )
                ->setAvailableStock($stockQty);
        }

        return $this->productFactory->create()
            ->setId((string) $catalogProduct->getSku())
            ->setArticleNumber((string) $catalogProduct->getSku())
            ->setEan(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue($catalogProduct, AttributeProvider::EAN_ATTRIBUTE)
                )
            )
            ->setManufacturerNumber(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue(
                        $catalogProduct,
                        AttributeProvider::MANUFACTURER_NUMBER_ATTRIBUTE
                    )
                )
            )
            ->setDescription($this->resolveDescription($catalogProduct))
            ->setBrandDge(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue($catalogProduct, AttributeProvider::BRAND_ATTRIBUTE)
                )
            )
            ->setDeliveryTime(
                $this->normalizeString(
                    $this->attributeProvider->getProductAttributeValue($catalogProduct, AttributeProvider::DELIVERY_TIME_ATTRIBUTE)
                )
            )
            ->setProductType($this->normalizeString($catalogProduct->getTypeId()))
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
     * Prefer the actual product description fields over the product name.
     *
     * @param Product $catalogProduct
     * @return string|null
     */
    private function resolveDescription(Product $catalogProduct)
    {
        $description = $this->normalizeString($catalogProduct->getData('description'));
        if ($description !== null) {
            return strip_tags($description);
        }

        $shortDescription = $this->normalizeString($catalogProduct->getData('short_description'));
        if ($shortDescription !== null) {
            return strip_tags($shortDescription);
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
