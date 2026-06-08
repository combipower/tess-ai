<?php
namespace Combipower\TessAI\Api\Data;

interface PriceUpdateInterface
{
    public const SKU = 'sku';
    public const PRICE = 'price';
    public const SPECIAL_PRICE = 'special_price';
    public const SPECIAL_FROM_DATE = 'special_from_date';
    public const SPECIAL_TO_DATE = 'special_to_date';

    /**
     * @return string
     */
    public function getSku();

    /**
     * @return float
     */
    public function getPrice();

    /**
     * @return float|null
     */
    public function getSpecialPrice();

    /**
     * @return string|null
     */
    public function getSpecialFromDate();

    /**
     * @return string|null
     */
    public function getSpecialToDate();

    /**
     * @param string $sku
     * @return \Combipower\TessAI\Api\Data\PriceUpdateInterface
     */
    public function setSku($sku);

    /**
     * @param float $price
     * @return \Combipower\TessAI\Api\Data\PriceUpdateInterface
     */
    public function setPrice($price);

    /**
     * @param float|null $specialPrice
     * @return \Combipower\TessAI\Api\Data\PriceUpdateInterface
     */
    public function setSpecialPrice($specialPrice);

    /**
     * @param string|null $specialFromDate
     * @return \Combipower\TessAI\Api\Data\PriceUpdateInterface
     */
    public function setSpecialFromDate($specialFromDate);

    /**
     * @param string|null $specialToDate
     * @return \Combipower\TessAI\Api\Data\PriceUpdateInterface
     */
    public function setSpecialToDate($specialToDate);
}
