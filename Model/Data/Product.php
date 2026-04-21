<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\ProductInterface;

class Product extends AbstractSimpleObject implements ProductInterface
{
    public function getId()
    {
        return $this->_get(self::ID);
    }

    public function getArticleNumber()
    {
        return $this->_get(self::ARTICLE_NUMBER);
    }

    public function getBarcode()
    {
        return $this->_get(self::BARCODE);
    }

    public function getManufacturerNumber()
    {
        return $this->_get(self::MANUFACTURER_NUMBER);
    }

    public function getDescription()
    {
        return $this->_get(self::DESCRIPTION);
    }

    public function getBrandDge()
    {
        return $this->_get(self::BRAND_DGE);
    }

    public function getDeliveryTime()
    {
        return $this->_get(self::DELIVERY_TIME);
    }

    public function getProductType()
    {
        return $this->_get(self::PRODUCT_TYPE);
    }

    public function getPrice()
    {
        return $this->_get(self::PRICE);
    }

    public function getCategoryId()
    {
        return $this->_get(self::CATEGORY_ID);
    }

    public function getSaleUnits()
    {
        return $this->_get(self::SALE_UNITS);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setArticleNumber($articleNumber)
    {
        return $this->setData(self::ARTICLE_NUMBER, $articleNumber);
    }

    public function setBarcode($barcode)
    {
        return $this->setData(self::BARCODE, $barcode);
    }

    public function setManufacturerNumber($manufacturerNumber)
    {
        return $this->setData(self::MANUFACTURER_NUMBER, $manufacturerNumber);
    }

    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function setBrandDge($brandDge)
    {
        return $this->setData(self::BRAND_DGE, $brandDge);
    }

    public function setDeliveryTime($deliveryTime)
    {
        return $this->setData(self::DELIVERY_TIME, $deliveryTime);
    }

    public function setProductType($productType)
    {
        return $this->setData(self::PRODUCT_TYPE, $productType);
    }

    public function setPrice(array $price)
    {
        return $this->setData(self::PRICE, $price);
    }

    public function setCategoryId($categoryId)
    {
        return $this->setData(self::CATEGORY_ID, $categoryId);
    }

    public function setSaleUnits(array $saleUnits)
    {
        return $this->setData(self::SALE_UNITS, $saleUnits);
    }
}
