<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\SaleUnitInterface;

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

    public function getCurrentSalesPriceExclVat()
    {
        return $this->_get(self::CURRENT_SALES_PRICE_EXCL_VAT);
    }

    public function getCurrentSalesPriceInclVat()
    {
        return $this->_get(self::CURRENT_SALES_PRICE_INCL_VAT);
    }

    public function getPurchasePrice()
    {
        return $this->_get(self::PURCHASE_PRICE);
    }

    public function getPurchasePriceExclVat()
    {
        return $this->_get(self::PURCHASE_PRICE_EXCL_VAT);
    }

    public function getPurchasePriceInclVat()
    {
        return $this->_get(self::PURCHASE_PRICE_INCL_VAT);
    }

    public function getShippingCost()
    {
        return $this->_get(self::SHIPPING_COST);
    }

    public function getShippingCostExclVat()
    {
        return $this->_get(self::SHIPPING_COST_EXCL_VAT);
    }

    public function getShippingCostInclVat()
    {
        return $this->_get(self::SHIPPING_COST_INCL_VAT);
    }

    public function getAvailableStock()
    {
        return $this->_get(self::AVAILABLE_STOCK);
    }

    public function getExtraFee()
    {
        return $this->_get(self::EXTRA_FEE);
    }

    public function getBolExtraFee()
    {
        return $this->_get(self::BOL_EXTRA_FEE);
    }

    public function getHasTessPrice()
    {
        return $this->_get(self::HAS_TESS_PRICE);
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

    public function setCurrentSalesPriceExclVat($price)
    {
        return $this->setData(self::CURRENT_SALES_PRICE_EXCL_VAT, $price);
    }

    public function setCurrentSalesPriceInclVat($price)
    {
        return $this->setData(self::CURRENT_SALES_PRICE_INCL_VAT, $price);
    }

    public function setPurchasePrice($price)
    {
        return $this->setData(self::PURCHASE_PRICE, $price);
    }

    public function setPurchasePriceExclVat($price)
    {
        return $this->setData(self::PURCHASE_PRICE_EXCL_VAT, $price);
    }

    public function setPurchasePriceInclVat($price)
    {
        return $this->setData(self::PURCHASE_PRICE_INCL_VAT, $price);
    }

    public function setShippingCost($cost)
    {
        return $this->setData(self::SHIPPING_COST, $cost);
    }

    public function setShippingCostExclVat($cost)
    {
        return $this->setData(self::SHIPPING_COST_EXCL_VAT, $cost);
    }

    public function setShippingCostInclVat($cost)
    {
        return $this->setData(self::SHIPPING_COST_INCL_VAT, $cost);
    }

    public function setAvailableStock($stock)
    {
        return $this->setData(self::AVAILABLE_STOCK, $stock);
    }

    public function setExtraFee($extraFee)
    {
        return $this->setData(self::EXTRA_FEE, $extraFee);
    }

    public function setBolExtraFee($bolExtraFee)
    {
        return $this->setData(self::BOL_EXTRA_FEE, $bolExtraFee);
    }

    public function setHasTessPrice($hasTessPrice)
    {
        return $this->setData(self::HAS_TESS_PRICE, $hasTessPrice);
    }
}
