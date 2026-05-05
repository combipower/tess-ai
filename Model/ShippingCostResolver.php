<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tess\PricingTool\Model\Config\ShippingEstimate;

class ShippingCostResolver
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ShippingEstimate
     */
    private $shippingEstimate;

    /**
     * @var array
     */
    private $shippingCostCache = [];

    public function __construct(
        QuoteFactory $quoteFactory,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        ShippingEstimate $shippingEstimate
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeManager = $storeManager;
        $this->shippingEstimate = $shippingEstimate;
    }

    /**
     * @param Product $product
     * @param float $qty
     * @return float|null
     */
    public function resolve(Product $product, $qty = 1.0)
    {
        $countryId = $this->shippingEstimate->getCountryId();
        if ($countryId === null) {
            return null;
        }

        $qty = max(1.0, (float) $qty);
        $cacheKey = implode('|', [
            $this->resolveStoreId($product),
            $product->getSku(),
            sprintf('%.4F', $qty),
            $this->shippingEstimate->getCacheKey(),
        ]);

        if (array_key_exists($cacheKey, $this->shippingCostCache)) {
            return $this->shippingCostCache[$cacheKey];
        }

        try {
            $shippingCost = $this->collectShippingCost($product, $qty);
        } catch (\Throwable $exception) {
            $shippingCost = null;
        }

        $this->shippingCostCache[$cacheKey] = $shippingCost;
        return $shippingCost;
    }

    /**
     * @param Product $product
     * @param float $qty
     * @return float|null
     */
    private function collectShippingCost(Product $product, $qty)
    {
        $store = $this->storeManager->getStore($this->resolveStoreId($product));
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
        $quote->setIsActive(false);

        $request = $this->dataObjectFactory->create(['data' => ['qty' => $qty]]);
        $quoteItem = $quote->addProduct($product, $request);
        if (is_string($quoteItem)) {
            return null;
        }

        $address = $quote->getShippingAddress();
        $address->setCountryId($this->shippingEstimate->getCountryId());
        $address->setRegionId($this->shippingEstimate->getRegionId());
        $address->setRegion($this->shippingEstimate->getRegion());
        $address->setPostcode($this->shippingEstimate->getPostcode());
        $address->setCity($this->shippingEstimate->getCity());
        $address->setStreet($this->shippingEstimate->getStreet());
        $address->setCollectShippingRates(true);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        return $this->resolveRatePrice($address->getAllShippingRates());
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\Rate[] $rates
     * @return float|null
     */
    private function resolveRatePrice(array $rates)
    {
        $configuredMethod = $this->shippingEstimate->getShippingMethod();
        $cheapestPrice = null;

        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $price = $this->normalizeDecimal($rate->getPrice());
            if ($price === null) {
                continue;
            }

            if ($configuredMethod !== null && $rate->getCode() === $configuredMethod) {
                return $price;
            }

            if ($cheapestPrice === null || $price < $cheapestPrice) {
                $cheapestPrice = $price;
            }
        }

        if ($configuredMethod !== null) {
            return null;
        }

        return $cheapestPrice;
    }

    /**
     * @param Product $product
     * @return int|null
     */
    private function resolveStoreId(Product $product)
    {
        $storeId = $product->getStoreId();
        if ($storeId) {
            return (int) $storeId;
        }

        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $exception) {
            return null;
        }
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
}
