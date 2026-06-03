<?php
namespace Combipower\TessAI\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ShippingEstimate
{
    public const XML_PATH_COUNTRY_ID = 'combipower_tess_ai/shipping_estimate/country_id';
    public const XML_PATH_REGION_ID = 'combipower_tess_ai/shipping_estimate/region_id';
    public const XML_PATH_REGION = 'combipower_tess_ai/shipping_estimate/region';
    public const XML_PATH_POSTCODE = 'combipower_tess_ai/shipping_estimate/postcode';
    public const XML_PATH_CITY = 'combipower_tess_ai/shipping_estimate/city';
    public const XML_PATH_STREET = 'combipower_tess_ai/shipping_estimate/street';
    public const XML_PATH_SHIPPING_METHOD = 'combipower_tess_ai/shipping_estimate/shipping_method';
    public const XML_PATH_QUOTE_ATTRIBUTES = 'combipower_tess_ai/shipping_estimate/quote_attributes';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @return string|null
     */
    public function getCountryId()
    {
        return $this->getConfiguredString(self::XML_PATH_COUNTRY_ID);
    }

    /**
     * @return int|null
     */
    public function getRegionId()
    {
        $regionId = $this->getConfiguredString(self::XML_PATH_REGION_ID);
        if ($regionId === null || !ctype_digit($regionId)) {
            return null;
        }

        return (int) $regionId;
    }

    /**
     * @return string|null
     */
    public function getRegion()
    {
        return $this->getConfiguredString(self::XML_PATH_REGION);
    }

    /**
     * @return string|null
     */
    public function getPostcode()
    {
        return $this->getConfiguredString(self::XML_PATH_POSTCODE);
    }

    /**
     * @return string|null
     */
    public function getCity()
    {
        return $this->getConfiguredString(self::XML_PATH_CITY);
    }

    /**
     * @return string|null
     */
    public function getStreet()
    {
        return $this->getConfiguredString(self::XML_PATH_STREET);
    }

    /**
     * @return string|null
     */
    public function getShippingMethod()
    {
        return $this->getConfiguredString(self::XML_PATH_SHIPPING_METHOD);
    }

    /**
     * Product attribute codes to load into the temporary shipping quote so
     * carrier table rates and restriction rules (dimensions/weight) evaluate
     * against real values instead of empty ones. Stored as a multiselect
     * (comma-separated). Non-existing codes are filtered downstream.
     *
     * @return string[]
     */
    public function getQuoteAttributeCodes()
    {
        $value = $this->getConfiguredString(self::XML_PATH_QUOTE_ATTRIBUTES);
        if ($value === null) {
            return [];
        }

        $codes = [];
        foreach (explode(',', $value) as $code) {
            $code = trim($code);
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        return implode('|', [
            $this->getCountryId(),
            $this->getRegionId(),
            $this->getRegion(),
            $this->getPostcode(),
            $this->getCity(),
            $this->getStreet(),
            $this->getShippingMethod(),
            implode(',', $this->getQuoteAttributeCodes()),
        ]);
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function getConfiguredString($path)
    {
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $this->resolveStoreId());
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return int|null
     */
    private function resolveStoreId()
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
