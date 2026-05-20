<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Combipower\TessAI\Api\Data\AdditionalAttributeInterface;
use Combipower\TessAI\Model\Config\AttributeMapping;
use Combipower\TessAI\Model\Data\AdditionalAttributeFactory;

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

    /**
     * @var AdditionalAttributeFactory
     */
    private $additionalAttributeFactory;

    public function __construct(
        EavConfig $eavConfig,
        ResourceConnection $resourceConnection,
        AttributeMapping $attributeMapping,
        AdditionalAttributeFactory $additionalAttributeFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->resourceConnection = $resourceConnection;
        $this->attributeMapping = $attributeMapping;
        $this->additionalAttributeFactory = $additionalAttributeFactory;
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
    public function getUnitAttributeCode()
    {
        return $this->attributeMapping->getUnitAttributeCode();
    }

    /**
     * @return string[]
     */
    public function getAdditionalAttributeCodes()
    {
        $codes = $this->attributeMapping->getAdditionalAttributeCodes();

        $result = [];
        foreach ($codes as $code) {
            if ($this->hasProductAttribute($code)) {
                $result[] = $code;
            }
        }

        return $result;
    }

    /**
     * Decide which collection filter operator suits an attribute.
     *
     * Numeric (int/decimal) and source-using attributes (select/multiselect)
     * → exact match.
     * Text-ish (varchar/text) → LIKE '%value%' for partial search.
     *
     * @param string $attributeCode
     * @return string 'exact' or 'like'
     */
    public function resolveAttributeOperator($attributeCode)
    {
        if (!$this->hasProductAttribute($attributeCode)) {
            return 'exact';
        }

        $attribute = $this->eavConfig->getAttribute(CatalogProduct::ENTITY, $attributeCode);
        if ($attribute->usesSource()) {
            return 'exact';
        }

        $backendType = (string) $attribute->getBackendType();
        if (in_array($backendType, ['int', 'decimal'], true)) {
            return 'exact';
        }

        return 'like';
    }

    /**
     * Build {code, value} DTOs for the configured additional attributes.
     *
     * Wrapping as objects (instead of an associative array) is required so
     * Magento's webapi marshaller preserves the attribute code in JSON; a
     * map declared as `string[]` would be re-indexed and the codes lost.
     *
     * @param CatalogProduct $product
     * @return AdditionalAttributeInterface[]
     */
    public function buildAdditionalAttributes(CatalogProduct $product)
    {
        $result = [];
        foreach ($this->getAdditionalAttributeCodes() as $code) {
            $raw = $this->getProductAttributeValue($product, $code);
            if ($raw === null || $raw === '' || $raw === false) {
                continue;
            }

            if (is_array($raw)) {
                $raw = implode(', ', array_filter(array_map('strval', $raw), static function ($v) {
                    return $v !== '';
                }));
                if ($raw === '') {
                    continue;
                }
            }

            $result[] = $this->additionalAttributeFactory->create()
                ->setCode($code)
                ->setValue((string) $raw);
        }

        return $result;
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
