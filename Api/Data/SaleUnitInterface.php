<?php
namespace Tess\PricingTool\Api\Data;

interface SaleUnitInterface
{
    const ID = 'id';
    const SALE_ID = 'sale_id';
    const LABEL = 'label';
    const VALUE = 'value';
    const CURRENCY = 'currency';
    const PURCHASE_PRICE_EXCL_VAT = 'purchase_price_excl_vat';
    const SHIPPING_COST = 'shipping_cost';
    const AVAILABLE_STOCK = 'available_stock';

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
    public function getPurchasePriceExclVat();

    /**
     * @return float|null
     */
    public function getShippingCost();

    /**
     * @return float|null
     */
    public function getAvailableStock();

    /**
     * @param string $id
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setId($id);

    /**
     * @param string $saleId
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setSaleId($saleId);

    /**
     * @param string $label
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setLabel($label);

    /**
     * @param float|null $value
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setValue($value);

    /**
     * @param string|null $currency
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setCurrency($currency);

    /**
     * @param float|null $price
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setPurchasePriceExclVat($price);

    /**
     * @param float|null $cost
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setShippingCost($cost);

    /**
     * @param float|null $stock
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface
     */
    public function setAvailableStock($stock);

}
