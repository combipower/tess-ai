<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\ProductInterface;

class Product extends AbstractSimpleObject implements ProductInterface
{
    public function getId()
    {
        return $this->_get(self::ID);
    }

    public function getSku()
    {
        return $this->_get(self::SKU);
    }

    public function getBarcode()
    {
        return $this->_get(self::BARCODE);
    }

    public function getEan()
    {
        return $this->_get(self::EAN);
    }

    public function getManufacturerNumber()
    {
        return $this->_get(self::MANUFACTURER_NUMBER);
    }

    public function getSupplier()
    {
        return $this->_get(self::SUPPLIER);
    }

    public function getUnit()
    {
        return $this->_get(self::UNIT);
    }

    public function getName()
    {
        return $this->_get(self::NAME);
    }

    public function getBrand()
    {
        return $this->_get(self::BRAND);
    }

    public function getDeliveryTime()
    {
        return $this->_get(self::DELIVERY_TIME);
    }

    public function getTessBrand()
    {
        return $this->_get(self::TESS_BRAND);
    }

    public function getTessDeliveryTime()
    {
        return $this->_get(self::TESS_DELIVERY_TIME);
    }

    public function getProductType()
    {
        return $this->_get(self::PRODUCT_TYPE);
    }

    public function getPrice()
    {
        return $this->_get(self::PRICE);
    }

    public function getSpecialPrice()
    {
        return $this->_get(self::SPECIAL_PRICE);
    }

    public function getOrderNumber()
    {
        return $this->_get(self::ORDER_NUMBER);
    }

    public function getCategoryId()
    {
        return $this->_get(self::CATEGORY_ID);
    }

    public function getSaleUnits()
    {
        return $this->_get(self::SALE_UNITS);
    }

    public function getAdditionalAttributes()
    {
        return $this->_get(self::ADDITIONAL_ATTRIBUTES);
    }

    public function getChannableStatus()
    {
        return $this->_get(self::CHANNABLE_STATUS);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    public function setBarcode($barcode)
    {
        return $this->setData(self::BARCODE, $barcode);
    }

    public function setEan($ean)
    {
        return $this->setData(self::EAN, $ean);
    }

    public function setManufacturerNumber($manufacturerNumber)
    {
        return $this->setData(self::MANUFACTURER_NUMBER, $manufacturerNumber);
    }

    public function setSupplier($supplier)
    {
        return $this->setData(self::SUPPLIER, $supplier);
    }

    public function setUnit($unit)
    {
        return $this->setData(self::UNIT, $unit);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function setBrand($brand)
    {
        return $this->setData(self::BRAND, $brand);
    }

    public function setDeliveryTime($deliveryTime)
    {
        return $this->setData(self::DELIVERY_TIME, $deliveryTime);
    }

    public function setTessBrand($tessBrand)
    {
        return $this->setData(self::TESS_BRAND, $tessBrand);
    }

    public function setTessDeliveryTime($tessDeliveryTime)
    {
        return $this->setData(self::TESS_DELIVERY_TIME, $tessDeliveryTime);
    }

    public function setProductType($productType)
    {
        return $this->setData(self::PRODUCT_TYPE, $productType);
    }

    public function setPrice(array $price)
    {
        return $this->setData(self::PRICE, $price);
    }

    public function setSpecialPrice($specialPrice)
    {
        return $this->setData(self::SPECIAL_PRICE, $specialPrice);
    }

    public function setOrderNumber($orderNumber)
    {
        return $this->setData(self::ORDER_NUMBER, $orderNumber);
    }

    public function setCategoryId($categoryId)
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
    }

    public function setSaleUnits(array $saleUnits)
    {
        return $this->setData(self::SALE_UNITS, $saleUnits);
    }

    public function setAdditionalAttributes(array $additionalAttributes)
    {
        return $this->setData(self::ADDITIONAL_ATTRIBUTES, $additionalAttributes);
    }

    public function setChannableStatus($channableStatus)
    {
        return $this->setData(self::CHANNABLE_STATUS, $channableStatus);
    }
}
