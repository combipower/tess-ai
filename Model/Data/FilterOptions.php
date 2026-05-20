<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\FilterOptionsInterface;

class FilterOptions extends AbstractSimpleObject implements FilterOptionsInterface
{
    public function getSuppliers()
    {
        return $this->_get(self::SUPPLIERS);
    }

    public function getBrands()
    {
        return $this->_get(self::BRANDS);
    }

    public function getSkus()
    {
        return $this->_get(self::SKUS);
    }

    public function setSuppliers(array $suppliers)
    {
        return $this->setData(self::SUPPLIERS, $suppliers);
    }

    public function setBrands(array $brands)
    {
        return $this->setData(self::BRANDS, $brands);
    }

    public function setSkus(array $skus)
    {
        return $this->setData(self::SKUS, $skus);
    }
}
