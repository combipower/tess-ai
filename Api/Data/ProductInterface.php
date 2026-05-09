<?php
namespace Combipower\TessAI\Api\Data;

interface ProductInterface
{
    public const ID = 'id';
    public const ARTICLE_NUMBER = 'article_number';
    public const BARCODE = 'barcode';
    public const EAN = 'ean';
    public const MANUFACTURER_NUMBER = 'manufacturer_number';
    public const DESCRIPTION = 'description';
    public const BRAND_DGE = 'brand_dge';
    public const ARTICLE_GROUP = 'article_group';
    public const DELIVERY_TIME = 'delivery_time';
    public const PRODUCT_TYPE = 'product_type';
    public const PRICE = 'price';
    public const SPECIAL_PRICE = 'special_price';
    public const ORDER_NUMBER = 'order_number';
    public const CATEGORY_ID = 'category_id';
    public const SALE_UNITS = 'sale_units';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getArticleNumber();

    /**
     * @return string
     */
    public function getBarcode();

    /**
     * @return string|null
     */
    public function getEan();

    /**
     * @return string|null
     */
    public function getManufacturerNumber();

    /**
     * @return string|null
     */
    public function getDescription();

    /**
     * @return string|null
     */
    public function getBrandDge();

    /**
     * @return string|null
     */
    public function getArticleGroup();

    /**
     * @return string|null
     */
    public function getDeliveryTime();

    /**
     * @return string|null
     */
    public function getProductType();

    /**
     * @return float[]|null
     */
    public function getPrice();

    /**
     * @return float|null
     */
    public function getSpecialPrice();

    /**
     * @return float|null
     */
    public function getOrderNumber();

    /**
     * @return string|null
     */
    public function getCategoryId();

    /**
     * @return \Combipower\TessAI\Api\Data\SaleUnitInterface[]|null
     */
    public function getSaleUnits();

    /**
     * @param string $id
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setId($id);

    /**
     * @param string $articleNumber
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setArticleNumber($articleNumber);

    /**
     * @param string|null $barcode
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setBarcode($barcode);

    /**
     * @param string|null $ean
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setEan($ean);

    /**
     * @param string|null $manufacturerNumber
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setManufacturerNumber($manufacturerNumber);

    /**
     * @param string|null $description
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setDescription($description);

    /**
     * @param string|null $brandDge
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setBrandDge($brandDge);

    /**
     * @param string|null $articleGroup
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setArticleGroup($articleGroup);

    /**
     * @param string|null $deliveryTime
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setDeliveryTime($deliveryTime);

    /**
     * @param string|null $productType
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setProductType($productType);

    /**
     * @param float[] $price
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setPrice(array $price);

    /**
     * @param float|null $specialPrice
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setSpecialPrice($specialPrice);

    /**
     * @param float|null $orderNumber
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setOrderNumber($orderNumber);

    /**
     * @param string|null $categoryId
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setCategoryId($categoryId);

    /**
     * @param \Combipower\TessAI\Api\Data\SaleUnitInterface[] $saleUnits
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setSaleUnits(array $saleUnits);
}
