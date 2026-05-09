<?php
namespace Combipower\TessAI\Model;

use Combipower\TessAI\Api\FilterManagementInterface;
use Combipower\TessAI\Model\Data\FilterOptionsFactory;
use Combipower\TessAI\Model\Data\OptionFactory;

class FilterManagement implements FilterManagementInterface
{
    /**
     * @var AttributeProvider
     */
    private $attributeProvider;

    /**
     * @var FilterOptionsFactory
     */
    private $filterOptionsFactory;

    /**
     * @var OptionFactory
     */
    private $optionFactory;

    public function __construct(
        AttributeProvider $attributeProvider,
        FilterOptionsFactory $filterOptionsFactory,
        OptionFactory $optionFactory
    ) {
        $this->attributeProvider = $attributeProvider;
        $this->filterOptionsFactory = $filterOptionsFactory;
        $this->optionFactory = $optionFactory;
    }

    public function getOptions()
    {
        $suppliers = $this->buildOptions(
            $this->attributeProvider->getAttributeOptions($this->attributeProvider->getSupplierAttributeCode())
        );
        $brands = $this->buildOptions(
            $this->attributeProvider->getAttributeOptions($this->attributeProvider->getBrandAttributeCode())
        );
        $articleNumbers = $this->buildOptions($this->attributeProvider->getSkuOptions());

        return $this->filterOptionsFactory->create()
            ->setSuppliers($suppliers)
            ->setBrands($brands)
            ->setArticleNumbers($articleNumbers);
    }

    /**
     * @param array[] $options
     * @return \Combipower\TessAI\Api\Data\OptionInterface[]
     */
    private function buildOptions(array $options)
    {
        $items = [];
        foreach ($options as $option) {
            $items[] = $this->optionFactory->create()
                ->setId($option['id'])
                ->setName($option['name']);
        }

        return $items;
    }
}
