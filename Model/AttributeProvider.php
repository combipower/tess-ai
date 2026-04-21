<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Tess\PricingTool\Model\Config\AttributeMapping;

class AttributeProvider
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AttributeMapping
     */
    private $attributeMapping;

    public function __construct(
        EavConfig $eavConfig,
        ResourceConnection $resourceConnection,
        AttributeMapping $attributeMapping
    ) {
        $this->eavConfig = $eavConfig;
        $this->resourceConnection = $resourceConnection;
        $this->attributeMapping = $attributeMapping;
    }

    /**
     * @return string|null
     */
    public function getSupplierAttributeCode()
    {
        return $this->attributeMapping->getSupplierAttributeCode();
    }

    /**
     * @return string|null
     */
    public function getBrandAttributeCode()
    {
        return $this->attributeMapping->getBrandAttributeCode();
    }

    /**
     * @return string|null
     */
    public function getBarcodeAttributeCode()
    {
        return $this->attributeMapping->getBarcodeAttributeCode();
    }

    /**
     * @return string|null
     */
    public function getManufacturerNumberAttributeCode()
    {
        return $this->attributeMapping->getManufacturerNumberAttributeCode();
    }

    /**
     * @return string|null
     */
    public function getDeliveryTimeAttributeCode()
    {
        return $this->attributeMapping->getDeliveryTimeAttributeCode();
    }

    /**
     * @return string|null
     */
    public function getUnitAttributeCode()
    {
        return $this->attributeMapping->getUnitAttributeCode();
    }

    /**
     * @return string[]
     */
    public function getMappedAttributeCodes()
    {
        return $this->attributeMapping->getMappedAttributeCodes();
    }

    /**
     * @param string $attributeCode
     * @return bool
     */
    public function hasProductAttribute($attributeCode)
    {
        if (!$attributeCode) {
            return false;
        }

        try {
            $attribute = $this->eavConfig->getAttribute(CatalogProduct::ENTITY, $attributeCode);
        } catch (\Throwable $exception) {
            return false;
        }

        return (bool) $attribute->getId();
    }

    /**
     * @param string[] $attributeCodes
     * @return string[]
     */
    public function getExistingProductAttributes(array $attributeCodes)
    {
        return array_values(array_filter($attributeCodes, [$this, 'hasProductAttribute']));
    }

    /**
     * @param string $attributeCode
     * @return array[]
     */
    public function getAttributeOptions($attributeCode)
    {
        if (!$this->hasProductAttribute($attributeCode)) {
            return [];
        }

        $attribute = $this->eavConfig->getAttribute(CatalogProduct::ENTITY, $attributeCode);
        if (!$attribute->usesSource()) {
            return $this->getDistinctValueOptions($attribute);
        }

        $options = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $value = isset($option['value']) ? (string) $option['value'] : '';
            $label = isset($option['label']) ? trim((string) $option['label']) : '';

            if ($value === '' || $label === '') {
                continue;
            }

            $options[] = [
                'id' => $value,
                'name' => $label,
            ];
        }

        return $options;
    }

    /**
     * Return distinct SKU options for article number filtering.
     *
     * @param int $limit
     * @return array[]
     */
    public function getSkuOptions($limit = 500)
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('catalog_product_entity');
        if (!$connection->isTableExists($tableName)) {
            return [];
        }

        $select = $connection->select()
            ->distinct(true)
            ->from(
                ['product' => $tableName],
                [
                    'id' => 'sku',
                    'name' => 'sku',
                ]
            )
            ->where("TRIM(sku) <> ''")
            ->order('sku ASC')
            ->limit((int) $limit);

        return array_map(
            static function (array $row) {
                return [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                ];
            },
            $connection->fetchAll($select)
        );
    }

    /**
     * Return the product-facing value for an attribute.
     *
     * For source attributes, Magento stores option ids, so we convert them to their labels.
     *
     * @param CatalogProduct $product
     * @param string|null $attributeCode
     * @return mixed
     */
    public function getProductAttributeValue(CatalogProduct $product, $attributeCode)
    {
        if (!$this->hasProductAttribute($attributeCode)) {
            return null;
        }

        $attribute = $this->eavConfig->getAttribute(CatalogProduct::ENTITY, $attributeCode);
        if ($attribute->usesSource()) {
            $value = $product->getAttributeText($attributeCode);

            if (is_array($value)) {
                $value = implode(', ', array_filter($value));
            }

            return $value;
        }

        return $product->getData($attributeCode);
    }

    /**
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     * @return array[]
     */
    private function getDistinctValueOptions($attribute)
    {
        $backendType = (string) $attribute->getBackendType();
        if (!$backendType || $backendType === 'static') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('catalog_product_entity_' . $backendType);
        if (!$connection->isTableExists($tableName)) {
            return [];
        }

        $select = $connection->select()
            ->distinct(true)
            ->from(
                ['value_table' => $tableName],
                [
                    'id' => 'value',
                    'name' => 'value',
                ]
            )
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('value IS NOT NULL')
            ->order('value ASC')
            ->limit(500);

        if (in_array($backendType, ['varchar', 'text'], true)) {
            $select->where("TRIM(value) <> ''");
        }

        return array_map(
            static function (array $row) {
                return [
                    'id' => (string) $row['id'],
                    'name' => (string) $row['name'],
                ];
            },
            $connection->fetchAll($select)
        );
    }
}
