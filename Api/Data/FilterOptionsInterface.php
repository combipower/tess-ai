<?php
namespace Combipower\TessAI\Api\Data;

interface FilterOptionsInterface
{
    const SUPPLIERS = 'suppliers';
    const BRANDS = 'brands';
    const SKUS = 'skus';

    /**
     * @return \Combipower\TessAI\Api\Data\OptionInterface[]|null
     */
    public function getSuppliers();

    /**
     * @return \Combipower\TessAI\Api\Data\OptionInterface[]|null
     */
    public function getBrands();

    /**
     * @return \Combipower\TessAI\Api\Data\OptionInterface[]|null
     */
    public function getSkus();

    /**
     * @param \Combipower\TessAI\Api\Data\OptionInterface[] $suppliers
     * @return \Combipower\TessAI\Api\Data\FilterOptionsInterface
     */
    public function setSuppliers(array $suppliers);

    /**
     * @param \Combipower\TessAI\Api\Data\OptionInterface[] $brands
     * @return \Combipower\TessAI\Api\Data\FilterOptionsInterface
     */
    public function setBrands(array $brands);

    /**
     * @param \Combipower\TessAI\Api\Data\OptionInterface[] $skus
     * @return \Combipower\TessAI\Api\Data\FilterOptionsInterface
     */
    public function setSkus(array $skus);
}
