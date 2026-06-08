<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\PriceUpdateInterface;

class PriceUpdate extends AbstractSimpleObject implements PriceUpdateInterface
{
    public function getSku()
    {
        return $this->_get(self::SKU);
    }

    public function getPrice()
    {
        return $this->_get(self::PRICE);
    }

    public function getSpecialPrice()
    {
        return $this->_get(self::SPECIAL_PRICE);
    }

    public function getSpecialFromDate()
    {
        return $this->_get(self::SPECIAL_FROM_DATE);
    }

    public function getSpecialToDate()
    {
        return $this->_get(self::SPECIAL_TO_DATE);
    }

    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    public function setPrice($price)
    {
        return $this->setData(self::PRICE, $price);
    }

    public function setSpecialPrice($specialPrice)
    {
        return $this->setData(self::SPECIAL_PRICE, $specialPrice);
    }

    public function setSpecialFromDate($specialFromDate)
    {
        return $this->setData(self::SPECIAL_FROM_DATE, $specialFromDate);
    }

    public function setSpecialToDate($specialToDate)
    {
        return $this->setData(self::SPECIAL_TO_DATE, $specialToDate);
    }
}
