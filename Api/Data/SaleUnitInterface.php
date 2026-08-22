<?php
namespace Combipower\TessAI\Api\Data;

interface SaleUnitInterface
{
    public const ID = 'id';
    public const SALE_ID = 'sale_id';
    public const LABEL = 'label';
    public const VALUE = 'value';
    public const CURRENCY = 'currency';
    public const CURRENT_SALES_PRICE_EXCL_VAT = 'current_sales_price_excl_vat';
    public const CURRENT_SALES_PRICE_INCL_VAT = 'current_sales_price_incl_vat';
    public const PURCHASE_PRICE = 'purchase_price';
    public const PURCHASE_PRICE_EXCL_VAT = 'purchase_price_excl_vat';
    public const PURCHASE_PRICE_INCL_VAT = 'purchase_price_incl_vat';
    public const SHIPPING_COST = 'shipping_cost';
    public const SHIPPING_COST_EXCL_VAT = 'shipping_cost_excl_vat';
    public const SHIPPING_COST_INCL_VAT = 'shipping_cost_incl_vat';
    public const AVAILABLE_STOCK = 'available_stock';
    public const EXTRA_FEE = 'extra_fee';
    public const BOL_EXTRA_FEE = 'bol_extra_fee';
    public const HAS_TESS_PRICE = 'has_tess_price';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getSaleId();

    /**
     * @return string|null
     */
    public function getLabel();

    /**
     * @return float|null
     */
    public function getValue();

    /**
     * @return string|null
     */
    public function getCurrency();

    /**
     * @return float|null
     */
    public function getCurrentSalesPriceExclVat();

    /**
     * @return float|null
     */
    public function getCurrentSalesPriceInclVat();

    /**
     * @return float
     */
    public function getPurchasePrice();

    /**
     * @return float|null
     */
    public function getPurchasePriceExclVat();

    /**
     * @return float|null
     */
    public function getPurchasePriceInclVat();

    /**
     * Legacy alias of `getShippingCostInclVat()`. Kept for BC; new clients
     * should read the explicit excl/incl fields below.
     *
     * @return float|null
     */
    public function getShippingCost();

    /**
     * @return float|null
     */
    public function getShippingCostExclVat();

    /**
     * @return float|null
     */
    public function getShippingCostInclVat();

    /**
     * @return float|null
     */
    public function getAvailableStock();

    /**
     * @return float|null
     */
    public function getExtraFee();

    /**
     * @return float|null
     */
    public function getBolExtraFee();

    /**
     * @return bool
     */
    public function getHasTessPrice();

    /**
     * @param string $id
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setId($id);

    /**
     * @param string $saleId
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setSaleId($saleId);

    /**
     * @param string $label
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setLabel($label);

    /**
     * @param float|null $value
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setValue($value);

    /**
     * @param string|null $currency
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setCurrency($currency);

    /**
     * @param float|null $price
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setCurrentSalesPriceExclVat($price);

    /**
     * @param float|null $price
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setCurrentSalesPriceInclVat($price);

    /**
     * @param float|null $price
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setPurchasePrice($price);

    /**
     * @param float|null $price
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setPurchasePriceExclVat($price);

    /**
     * @param float|null $price
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setPurchasePriceInclVat($price);

    /**
     * @param float|null $cost
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setShippingCost($cost);

    /**
     * @param float|null $cost
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setShippingCostExclVat($cost);

    /**
     * @param float|null $cost
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setShippingCostInclVat($cost);

    /**
     * @param float|null $stock
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setAvailableStock($stock);

    /**
     * @param float|null $extraFee
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setExtraFee($extraFee);

    /**
     * @param float|null $bolExtraFee
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setBolExtraFee($bolExtraFee);

    /**
     * @param bool $hasTessPrice
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface
     */
    public function setHasTessPrice($hasTessPrice);
}
