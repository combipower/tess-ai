<?php
namespace Tess\PricingTool\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AttributeMapping
{
    public const XML_PATH_SUPPLIER_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/supplier_attribute';
    public const XML_PATH_BRAND_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/brand_attribute';
    public const XML_PATH_BARCODE_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/barcode_attribute';
    public const XML_PATH_MANUFACTURER_NUMBER_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/manufacturer_number_attribute';
    public const XML_PATH_DELIVERY_TIME_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/delivery_time_attribute';
    public const XML_PATH_UNIT_ATTRIBUTE = 'tess_pricing_tool/attribute_mapping/unit_attribute';

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
    public function getSupplierAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_SUPPLIER_ATTRIBUTE);
    }

    /**
     * @return string|null
     */
    public function getBrandAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_BRAND_ATTRIBUTE);
    }

    /**
     * @return string|null
     */
    public function getBarcodeAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_BARCODE_ATTRIBUTE);
    }

    /**
     * @return string|null
     */
    public function getManufacturerNumberAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_MANUFACTURER_NUMBER_ATTRIBUTE);
    }

    /**
     * @return string|null
     */
    public function getDeliveryTimeAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_DELIVERY_TIME_ATTRIBUTE);
    }

    /**
     * @return string|null
     */
    public function getUnitAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_UNIT_ATTRIBUTE);
    }

    /**
     * @return string[]
     */
    public function getMappedAttributeCodes()
    {
        return array_values(
            array_filter(
                [
                    $this->getSupplierAttributeCode(),
                    $this->getBrandAttributeCode(),
                    $this->getBarcodeAttributeCode(),
                    $this->getManufacturerNumberAttributeCode(),
                    $this->getDeliveryTimeAttributeCode(),
                    $this->getUnitAttributeCode(),
                ]
            )
        );
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function getConfiguredAttributeCode($path)
    {
        $storeId = $this->resolveStoreId();
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return $this->normalizeAttributeCode($value);
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

    /**
     * @param mixed $value
     * @return string|null
     */
    private function normalizeAttributeCode($value)
    {
        if ($value === null) {
            return null;
        }

        $attributeCode = trim((string) $value);
        if ($attributeCode === '') {
            return null;
        }

        return $attributeCode;
    }
}
