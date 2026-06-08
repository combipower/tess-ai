<?php
namespace Combipower\TessAI\Api\Data;

interface PriceUpdateInterface
{
    public const SKU = 'sku';
    public const PRICE = 'price';
    public const SPECIAL_PRICE = 'special_price';

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
}
