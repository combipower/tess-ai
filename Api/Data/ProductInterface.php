<?php
namespace Tess\PricingTool\Api\Data;

interface ProductInterface
{
    const ID = 'id';
    const ARTICLE_NUMBER = 'article_number';
    const BARCODE = 'barcode';
    const MANUFACTURER_NUMBER = 'manufacturer_number';
    const DESCRIPTION = 'description';
    const BRAND_DGE = 'brand_dge';
    const DELIVERY_TIME = 'delivery_time';
    const PRODUCT_TYPE = 'product_type';
    const PRICE = 'price';
    const CATEGORY_ID = 'category_id';
    const SALE_UNITS = 'sale_units';

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
     * @return string|null
     */
    public function getCategoryId();

    /**
     * @return \Tess\PricingTool\Api\Data\SaleUnitInterface[]|null
     */
    public function getSaleUnits();

    /**
     * @param string $id
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setId($id);

    /**
     * @param string $articleNumber
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setArticleNumber($articleNumber);

    /**
     * @param string|null $barcode
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setBarcode($barcode);

    /**
     * @param string|null $manufacturerNumber
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setManufacturerNumber($manufacturerNumber);

    /**
     * @param string|null $description
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setDescription($description);

    /**
     * @param string|null $brandDge
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setBrandDge($brandDge);

    /**
     * @param string|null $deliveryTime
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setDeliveryTime($deliveryTime);

    /**
     * @param string|null $productType
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setProductType($productType);

    /**
     * @param float[] $price
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setPrice(array $price);

    /**
     * @param string|null $categoryId
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setCategoryId($categoryId);

    /**
     * @param \Tess\PricingTool\Api\Data\SaleUnitInterface[] $saleUnits
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function setSaleUnits(array $saleUnits);
}
