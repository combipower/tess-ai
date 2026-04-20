<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\FilterOptionsInterface;

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

    public function getArticleNumbers()
    {
        return $this->_get(self::ARTICLE_NUMBERS);
    }

    public function setSuppliers(array $suppliers)
    {
        return $this->setData(self::SUPPLIERS, $suppliers);
    }

    public function setBrands(array $brands)
    {
        return $this->setData(self::BRANDS, $brands);
    }

    public function setArticleNumbers(array $articleNumbers)
    {
        return $this->setData(self::ARTICLE_NUMBERS, $articleNumbers);
    }
}
