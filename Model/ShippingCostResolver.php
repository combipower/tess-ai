<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Group;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Combipower\TessAI\Model\Config\ShippingEstimate;

class ShippingCostResolver
{
    private const CACHE_ID_PREFIX = 'combipower_tessai_shipping_';

    /**
     * The cache key already self-invalidates on product data changes (the
     * fingerprint hashes attribute values) and on estimate config changes
     * (config values are part of the key). The lifetime only guards against
     * edits the key cannot see — Amasty table rates / restriction rules — so
     * those take effect within a day (or immediately on cache flush).
     */
    private const CACHE_LIFETIME = 86400;

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

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        QuoteFactory $quoteFactory,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        ShippingEstimate $shippingEstimate,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeManager = $storeManager;
        $this->shippingEstimate = $shippingEstimate;
        $this->logger = $logger;
        $this->cache = $cache;
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
        // When rates are flat per method (no qty/package-weight brackets or
        // multipliers — see isRateQtyDependent), the qty only matters through
        // the subtotal band inside the fingerprint, so sale-unit quantities
        // can share one estimate instead of collecting totals per tier qty.
        $keyQty = $this->shippingEstimate->isRateQtyDependent()
            ? sprintf('%.4F', $qty)
            : 'qty-independent';
        $cacheKey = implode('|', [
            $this->resolveStoreId($product),
            $keyQty,
            $this->shippingEstimate->getCacheKey(),
            $this->buildProductFingerprint($product, $qty),
        ]);

        if (array_key_exists($cacheKey, $this->shippingCostCache)) {
            return $this->shippingCostCache[$cacheKey];
        }

        $cacheId = self::CACHE_ID_PREFIX . sha1($cacheKey);
        $stored = $this->cache->load($cacheId);
        if ($stored !== false && is_numeric($stored)) {
            $shippingCost = (float) $stored;
            $this->shippingCostCache[$cacheKey] = $shippingCost;
            return $shippingCost;
        }

        try {
            $shippingCost = $this->collectShippingCost($product, $qty);
        } catch (\Throwable $exception) {
            $shippingCost = null;
        }

        $this->shippingCostCache[$cacheKey] = $shippingCost;
        // Only persist real rates: a null usually means a transient failure or
        // config gap, which must not stick for a whole cache lifetime.
        if ($shippingCost !== null) {
            $this->cache->save((string) $shippingCost, $cacheId, [], self::CACHE_LIFETIME);
        }

        return $shippingCost;
    }

    /**
     * Fingerprint of the product data that determines the estimated rate, so
     * products sharing the same shipping-relevant values reuse one
     * collectTotals() result instead of caching per SKU.
     *
     * The destination is fixed by config (already part of the outer cache key),
     * so the rate can only vary by: the quote attributes carriers and
     * restriction rules read (`shipping_estimate/quote_attributes` — the same
     * contract used to load products into the temporary quote), the product
     * type (virtual/downloadable collect no shipping rates), and the row price.
     * Any rate/restriction rule reading an attribute outside that config list
     * must have the attribute added there — the temporary quote already
     * requires this to evaluate correctly, the fingerprint just shares the
     * same contract.
     *
     * getFinalPrice() is used deliberately: the quote item reads the price
     * through the same product-instance cache, so the fingerprint always
     * matches the price collectShippingCost() would actually see.
     *
     * The price enters shipping rules only through the address subtotal (e.g.
     * a free-shipping-above-threshold restriction), so when the admin declared
     * the rule boundaries (`shipping_estimate/subtotal_thresholds`) the
     * fingerprint only encodes which band the row subtotal falls in — distinct
     * prices inside one band share a single collectTotals() run. Without
     * configured thresholds the raw price stays in the fingerprint (safe for
     * unknown rules, but one collectTotals per distinct price).
     *
     * @param Product $product
     * @param float $qty
     * @return string
     */
    private function buildProductFingerprint(Product $product, $qty)
    {
        $data = ['type_id' => (string) $product->getTypeId()];
        foreach ($this->shippingEstimate->getQuoteAttributeCodes() as $attributeCode) {
            $data[$attributeCode] = $product->getData($attributeCode);
        }

        try {
            $finalPrice = $this->normalizeDecimal($product->getFinalPrice($qty));
            $thresholds = $this->shippingEstimate->getSubtotalThresholds();
            if ($finalPrice !== null && !empty($thresholds)) {
                $data['subtotal_band'] = $this->resolveSubtotalBand($finalPrice * $qty, $thresholds);
            } else {
                $data['final_price'] = $finalPrice;
            }
        } catch (\Throwable $exception) {
            // Without a reliable price the fingerprint could over-share; fall
            // back to per-SKU caching for this product.
            $data['sku'] = (string) $product->getSku();
        }

        return sha1((string) json_encode($data));
    }

    /**
     * Index of the band a subtotal falls into between the configured
     * boundaries: 0 = below all thresholds, N = at/above the Nth (rules use
     * `>=`, so the boundary itself belongs to the upper band).
     *
     * @param float $subtotal
     * @param float[] $thresholds Sorted ascending.
     * @return int
     */
    private function resolveSubtotalBand($subtotal, array $thresholds)
    {
        $band = 0;
        foreach ($thresholds as $threshold) {
            if ($subtotal >= $threshold) {
                $band++;
            }
        }

        return $band;
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
