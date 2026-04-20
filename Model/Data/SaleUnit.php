<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\SaleUnitInterface;

class SaleUnit extends AbstractSimpleObject implements SaleUnitInterface
{
    public function getId()
    {
        return $this->_get(self::ID);
    }

    public function getSaleId()
    {
        return $this->_get(self::SALE_ID);
    }

    public function getLabel()
    {
        return $this->_get(self::LABEL);
    }

    public function getValue()
    {
        return $this->_get(self::VALUE);
    }

    public function getCurrency()
    {
        return $this->_get(self::CURRENCY);
    }

    public function getPurchasePriceExclVat()
    {
        return $this->_get(self::PURCHASE_PRICE_EXCL_VAT);
    }

    public function getShippingCost()
    {
        return $this->_get(self::SHIPPING_COST);
    }

    public function getAvailableStock()
    {
        return $this->_get(self::AVAILABLE_STOCK);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setSaleId($saleId)
    {
        return $this->setData(self::SALE_ID, $saleId);
    }

    public function setLabel($label)
    {
        return $this->setData(self::LABEL, $label);
    }

    public function setValue($value)
    {
        return $this->setData(self::VALUE, $value);
    }

    public function setCurrency($currency)
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    public function setPurchasePriceExclVat($price)
    {
        return $this->setData(self::PURCHASE_PRICE_EXCL_VAT, $price);
    }

    public function setShippingCost($cost)
    {
        return $this->setData(self::SHIPPING_COST, $cost);
    }

    public function setAvailableStock($stock)
    {
        return $this->setData(self::AVAILABLE_STOCK, $stock);
    }
}
