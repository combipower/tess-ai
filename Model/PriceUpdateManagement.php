<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;
use Combipower\TessAI\Api\PriceUpdateManagementInterface;
use Combipower\TessAI\Api\Data\PriceUpdateResultInterfaceFactory;

class PriceUpdateManagement implements PriceUpdateManagementInterface
{
    private const HAS_TESS_PRICE_ATTRIBUTE_CODE = 'has_tess_price';

    private const EXTRA_FREE_ATTRIBUTE_CODE = 'extra_free';

    private const TESS_BRAND_ATTRIBUTE_CODE = 'tess_brand';

    private const BOL_PRICE_ATTRIBUTE_CODE = 'bol_price';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var PriceUpdateResultInterfaceFactory
     */
    private $resultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ShopByBrandResolver
     */
    private $shopByBrandResolver;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        PriceUpdateResultInterfaceFactory $resultFactory,
        LoggerInterface $logger,
        ShopByBrandResolver $shopByBrandResolver
    ) {
        $this->productRepository = $productRepository;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->shopByBrandResolver = $shopByBrandResolver;
    }

    /**
     * @param \Combipower\TessAI\Api\Data\PriceUpdateInterface[] $items
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface[]
     */
    public function updatePrices(array $items)
    {
        $results = [];
        foreach ($items as $item) {
            $sku = trim((string) $item->getSku());
            $result = $this->resultFactory->create()->setSku($sku);

            try {
                $this->applyUpdate($sku, $item);
                $result->setSuccess(true)->setMessage(null);
            } catch (NoSuchEntityException $exception) {
                $result->setSuccess(false)->setMessage((string) $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->logger->error('Combipower_TessAI price update failed', [
                    'sku' => $sku,
                    'exception' => $exception,
                ]);
                $result->setSuccess(false)->setMessage((string) $exception->getMessage());
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Load the product in default scope, apply whichever fields were provided
     * (price, special price, validity dates, extra_free, bol_price, tess
     * brand/delivery) and persist. `price` is optional; when present the product
     * is flagged as TESS-priced (`has_tess_price = 1`). At least one field must
     * be provided.
     *
     * @param string $sku
     * @param \Combipower\TessAI\Api\Data\PriceUpdateInterface $item
     * @return void
     * @throws LocalizedException
     */
    private function applyUpdate($sku, $item)
    {
        if ($sku === '') {
            throw new LocalizedException(__('SKU is required.'));
        }

        $price = $item->getPrice();
        $hasPrice = ($price !== null && $price !== '');
        $normalizedPrice = null;
        if ($hasPrice) {
            $normalizedPrice = $this->normalizePrice($price);
            if ($normalizedPrice === null) {
                throw new LocalizedException(
                    __('A valid non-negative price is required for SKU "%1".', $sku)
                );
            }
        }

        if (!$this->hasAnyUpdate($item)) {
            throw new LocalizedException(__('No fields to update for SKU "%1".', $sku));
        }

        // editMode=true returns an editable product, default store scope so the
        // price is written to the admin/default value (global price scope).
        $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        $product->setStoreId(Store::DEFAULT_STORE_ID);

        if ($hasPrice) {
            $product->setPrice($normalizedPrice);
            // Setting the price flags the product as TESS-managed.
            $product->setData(self::HAS_TESS_PRICE_ATTRIBUTE_CODE, 1);
        }

        $specialPrice = $item->getSpecialPrice();
        if ($specialPrice !== null && $specialPrice !== '') {
            $normalizedSpecial = $this->normalizePrice($specialPrice);
            if ($normalizedSpecial === null) {
                throw new LocalizedException(
                    __('Invalid special price for SKU "%1".', $sku)
                );
            }
            $product->setSpecialPrice($normalizedSpecial);
        }

        $this->applyDate($product, 'special_from_date', $item->getSpecialFromDate(), $sku);
        $this->applyDate($product, 'special_to_date', $item->getSpecialToDate(), $sku);

        $extraFree = $item->getExtraFree();
        if ($extraFree !== null && $extraFree !== '') {
            $normalizedExtraFree = $this->normalizePrice($extraFree);
            if ($normalizedExtraFree === null) {
                throw new LocalizedException(
                    __('Invalid extra_free for SKU "%1". Must be a non-negative number.', $sku)
                );
            }
            $product->setData(self::EXTRA_FREE_ATTRIBUTE_CODE, $normalizedExtraFree);
        }

        $this->applyPrice($product, self::BOL_PRICE_ATTRIBUTE_CODE, $item->getBolPrice(), $sku);
        $this->applyBrand($product, $item->getTessBrand());
        $this->applyText($product, 'tess_delivery_time', $item->getTessDeliveryTime());

        $this->productRepository->save($product);
    }

    /**
     * Whether the item carries at least one field to update. A field counts as
     * present when it is not null (empty string `''` is a deliberate clear, so
     * it counts).
     *
     * @param \Combipower\TessAI\Api\Data\PriceUpdateInterface $item
     * @return bool
     */
    private function hasAnyUpdate($item)
    {
        return $item->getPrice() !== null
            || $item->getSpecialPrice() !== null
            || $item->getSpecialFromDate() !== null
            || $item->getSpecialToDate() !== null
            || $item->getExtraFree() !== null
            || $item->getTessBrand() !== null
            || $item->getTessDeliveryTime() !== null
            || $item->getBolPrice() !== null;
    }

    /**
     * Apply an optional price attribute to the product.
     *
     * - `null` (field omitted) → leave the current value untouched.
     * - empty string `''` → clear the override.
     * - a non-negative number → set it.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $attributeCode
     * @param mixed $value
     * @param string $sku
     * @return void
     * @throws LocalizedException
     */
    private function applyPrice($product, $attributeCode, $value, $sku)
    {
        if ($value === null) {
            return;
        }

        if ($value === '') {
            $product->setData($attributeCode, null);
            return;
        }

        $normalized = $this->normalizePrice($value);
        if ($normalized === null) {
            throw new LocalizedException(
                __('Invalid "%1" for SKU "%2". Must be a non-negative number.', $attributeCode, $sku)
            );
        }

        $product->setData($attributeCode, $normalized);
    }

    /**
     * Apply a special-price validity date to the product.
     *
     * - `null` (field omitted) → leave the current value untouched.
     * - empty string `''` → clear the date.
     * - a valid `Y-m-d` or `Y-m-d H:i:s` string → set it (normalized).
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $attributeCode
     * @param mixed $value
     * @param string $sku
     * @return void
     * @throws LocalizedException
     */
    private function applyDate($product, $attributeCode, $value, $sku)
    {
        if ($value === null) {
            return;
        }

        if ($value === '') {
            $product->setData($attributeCode, null);
            return;
        }

        $normalized = $this->normalizeDate($value);
        if ($normalized === null) {
            throw new LocalizedException(
                __('Invalid "%1" for SKU "%2". Use Y-m-d or Y-m-d H:i:s.', $attributeCode, $sku)
            );
        }

        $product->setData($attributeCode, $normalized);
    }

    /**
     * Apply the `tess_brand` text and, when Amasty Shop by Brand is installed,
     * assign the real brand attribute too.
     *
     * - `null` (field omitted) → leave everything untouched.
     * - empty string `''` → clear `tess_brand` only; the assigned brand is kept.
     * - any other string → set `tess_brand`, then find-or-create the brand
     *   option (case-insensitive) and assign it to the product's brand attribute.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param mixed $value
     * @return void
     */
    private function applyBrand($product, $value)
    {
        if ($value === null) {
            return;
        }

        $value = trim((string) $value);
        $product->setData(self::TESS_BRAND_ATTRIBUTE_CODE, $value === '' ? null : $value);

        if ($value === '') {
            return;
        }

        if (!$this->shopByBrandResolver->isEnabled()) {
            return;
        }

        $brandAttributeCode = $this->shopByBrandResolver->getBrandAttributeCode();
        if ($brandAttributeCode === null) {
            return;
        }

        $optionId = $this->shopByBrandResolver->getOrCreateOptionId($value);
        if ($optionId !== null) {
            $product->setData($brandAttributeCode, $optionId);
        }
    }

    /**
     * Apply a free-text attribute value to the product.
     *
     * - `null` (field omitted) → leave the current value untouched.
     * - empty string `''` → clear the value.
     * - any other string → set it (trimmed).
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $attributeCode
     * @param mixed $value
     * @return void
     */
    private function applyText($product, $attributeCode, $value)
    {
        if ($value === null) {
            return;
        }

        $value = trim((string) $value);
        $product->setData($attributeCode, $value === '' ? null : $value);
    }

    /**
     * Validate and normalize a date string to `Y-m-d H:i:s`. Accepts `Y-m-d`
     * (time defaults to 00:00:00) and `Y-m-d H:i:s`. Returns null when invalid.
     *
     * @param mixed $value
     * @return string|null
     */
    private function normalizeDate($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false && $date->format($format) === $value) {
                if ($format === 'Y-m-d') {
                    $date->setTime(0, 0, 0);
                }
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function normalizePrice($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < 0) {
            return null;
        }

        return $float;
    }
}
