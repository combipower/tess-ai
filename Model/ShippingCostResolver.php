<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Combipower\TessAI\Model\Config\ShippingEstimate;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $shippingCostCache = [];

    /**
     * @var bool
     */
    private $warnedMissingConfiguredMethod = false;

    public function __construct(
        QuoteFactory $quoteFactory,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        ShippingEstimate $shippingEstimate,
        LoggerInterface $logger
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeManager = $storeManager;
        $this->shippingEstimate = $shippingEstimate;
        $this->logger = $logger;
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
     * Pick the price for a given list of rates.
     *
     * If admin configured a specific method and it returned a rate, use it.
     * Otherwise fall back to the cheapest available rate so the API stays
     * useful when the admin's choice is temporarily unavailable for the
     * configured destination (carrier disabled, zone unsupported, etc.).
     * The fallback is logged once per request scope.
     *
     * @param \Magento\Quote\Model\Quote\Address\Rate[] $rates
     * @return float|null
     */
    private function resolveRatePrice(array $rates)
    {
        $configuredMethod = $this->shippingEstimate->getShippingMethod();
        $cheapestPrice = null;
        $configuredPrice = null;

        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $price = $this->normalizeDecimal($rate->getPrice());
            if ($price === null) {
                continue;
            }

            if ($configuredMethod !== null && $rate->getCode() === $configuredMethod) {
                $configuredPrice = $price;
            }

            if ($cheapestPrice === null || $price < $cheapestPrice) {
                $cheapestPrice = $price;
            }
        }

        if ($configuredMethod !== null && $configuredPrice !== null) {
            return $configuredPrice;
        }

        if ($configuredMethod !== null && $cheapestPrice !== null && !$this->warnedMissingConfiguredMethod) {
            $this->warnedMissingConfiguredMethod = true;
            $this->logger->warning(sprintf(
                'Combipower_TessAI: configured shipping method "%s" returned no rate for destination %s/%s. Falling back to cheapest available rate.',
                $configuredMethod,
                (string) $this->shippingEstimate->getCountryId(),
                (string) $this->shippingEstimate->getPostcode()
            ));
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
