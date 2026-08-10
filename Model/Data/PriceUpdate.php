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

    public function getExtraFree()
    {
        return $this->_get(self::EXTRA_FREE);
    }

    public function getTessBrand()
    {
        return $this->_get(self::TESS_BRAND);
    }

    public function getTessDeliveryTime()
    {
        return $this->_get(self::TESS_DELIVERY_TIME);
    }

    public function getBolPrice()
    {
        return $this->_get(self::BOL_PRICE);
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

    public function setExtraFree($extraFree)
    {
        return $this->setData(self::EXTRA_FREE, $extraFree);
    }

    public function setTessBrand($tessBrand)
    {
        return $this->setData(self::TESS_BRAND, $tessBrand);
    }

    public function setTessDeliveryTime($tessDeliveryTime)
    {
        return $this->setData(self::TESS_DELIVERY_TIME, $tessDeliveryTime);
    }

    public function setBolPrice($bolPrice)
    {
        return $this->setData(self::BOL_PRICE, $bolPrice);
    }
}
