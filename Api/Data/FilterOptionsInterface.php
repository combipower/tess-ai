<?php
namespace Tess\PricingTool\Api\Data;

interface FilterOptionsInterface
{
    const SUPPLIERS = 'suppliers';
    const BRANDS = 'brands';
    const ARTICLE_NUMBERS = 'article_numbers';

    /**
     * @return \Tess\PricingTool\Api\Data\OptionInterface[]|null
     */
    public function getSuppliers();

    /**
     * @return \Tess\PricingTool\Api\Data\OptionInterface[]|null
     */
    public function getBrands();

    /**
     * @return \Tess\PricingTool\Api\Data\OptionInterface[]|null
     */
    public function getArticleNumbers();

    /**
     * @param \Tess\PricingTool\Api\Data\OptionInterface[] $suppliers
     * @return \Tess\PricingTool\Api\Data\FilterOptionsInterface
     */
    public function setSuppliers(array $suppliers);

    /**
     * @param \Tess\PricingTool\Api\Data\OptionInterface[] $brands
     * @return \Tess\PricingTool\Api\Data\FilterOptionsInterface
     */
    public function setBrands(array $brands);

    /**
     * @param \Tess\PricingTool\Api\Data\OptionInterface[] $articleNumbers
     * @return \Tess\PricingTool\Api\Data\FilterOptionsInterface
     */
    public function setArticleNumbers(array $articleNumbers);

}
