<?php
namespace Combipower\TessAI\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class AttributeMapping
{
    public const XML_PATH_SUPPLIER_ATTRIBUTE = 'combipower_tess_ai/attribute_mapping/supplier_attribute';
    public const XML_PATH_BRAND_ATTRIBUTE = 'combipower_tess_ai/attribute_mapping/brand_attribute';
    public const XML_PATH_BARCODE_ATTRIBUTE = 'combipower_tess_ai/attribute_mapping/barcode_attribute';
    public const XML_PATH_MANUFACTURER_NUMBER_ATTRIBUTE = 'combipower_tess_ai/attribute_mapping/manufacturer_number_attribute';
    public const XML_PATH_UNIT_ATTRIBUTE = 'combipower_tess_ai/attribute_mapping/unit_attribute';
    public const XML_PATH_ADDITIONAL_ATTRIBUTES_MAPPINGS = 'combipower_tess_ai/additional_attributes/mappings';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
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
    public function getUnitAttributeCode()
    {
        return $this->getConfiguredAttributeCode(self::XML_PATH_UNIT_ATTRIBUTE);
    }

    /**
     * Return the configured list of additional attribute codes to expose in the
     * API response and to enable as `attr[code]` filters. Duplicates and the
     * five mapped codes (supplier/brand/barcode/manufacturer/unit) are removed
     * so callers don't double-process them.
     *
     * @return string[]
     */
    public function getAdditionalAttributeCodes()
    {
        $rawValue = $this->scopeConfig->getValue(
            self::XML_PATH_ADDITIONAL_ATTRIBUTES_MAPPINGS,
            ScopeInterface::SCOPE_STORE,
            $this->resolveStoreId()
        );

        if ($rawValue === null || $rawValue === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($rawValue);
        } catch (\Throwable $exception) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $reserved = array_filter([
            $this->getSupplierAttributeCode(),
            $this->getBrandAttributeCode(),
            $this->getBarcodeAttributeCode(),
            $this->getManufacturerNumberAttributeCode(),
            $this->getUnitAttributeCode(),
        ]);

        $codes = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $this->normalizeAttributeCode($row['code'] ?? null);
            if ($code === null || in_array($code, $reserved, true)) {
                continue;
            }

            $codes[$code] = $code;
        }

        return array_values($codes);
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
