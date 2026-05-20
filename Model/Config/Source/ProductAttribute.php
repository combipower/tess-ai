<?php
namespace Combipower\TessAI\Model\Config\Source;

use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttribute implements OptionSourceInterface
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var array|null
     */
    private $options;

    public function __construct(
        EavConfig $eavConfig,
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        $this->eavConfig = $eavConfig;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $entityType = $this->eavConfig->getEntityType(CatalogProduct::ENTITY);

        $collection = $this->attributeCollectionFactory->create()
            ->addFieldToFilter('entity_type_id', (int) $entityType->getId())
            ->setOrder('frontend_label', 'ASC');

        $options = [];
        foreach ($collection as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            if ($code === '') {
                continue;
            }

            $label = trim((string) $attribute->getFrontendLabel());
            $display = $label !== '' ? sprintf('%s [%s]', $label, $code) : $code;

            $options[] = [
                'value' => $code,
                'label' => $display,
            ];
        }

        usort($options, static function (array $a, array $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        return $this->options = $options;
    }
}
