<?php
namespace Combipower\TessAI\Api\Data;

interface ProductInterface
{
    public const ID = 'id';
    public const SKU = 'sku';
    public const BARCODE = 'barcode';
    public const EAN = 'ean';
    public const MANUFACTURER_NUMBER = 'manufacturer_number';
    public const SUPPLIER = 'supplier';
    public const UNIT = 'unit';
    public const NAME = 'name';
    public const BRAND = 'brand';
    public const DELIVERY_TIME = 'delivery_time';
    public const PRODUCT_TYPE = 'product_type';
    public const PRICE = 'price';
    public const SPECIAL_PRICE = 'special_price';
    public const ORDER_NUMBER = 'order_number';
    public const CATEGORY_ID = 'category_id';
    public const SALE_UNITS = 'sale_units';
    public const ADDITIONAL_ATTRIBUTES = 'additional_attributes';

    /**
     * @return string|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getSku();

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
     * Value of the configured supplier attribute (Admin → Attribute Mapping).
     *
     * @return string|null
     */
    public function getSupplier();

    /**
     * Raw value of the configured unit attribute (Admin → Attribute Mapping).
     * The same value powers `sale_units[].label`; this field exposes it once
     * at the product level for clients that don't iterate sale_units.
     *
     * @return string|null
     */
    public function getUnit();

    /**
     * @return string|null
     */
    public function getName();

    /**
     * @return string|null
     */
    public function getBrand();

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
     * Extra attribute values configured via Admin → Combipower → TESS AI →
     * Additional Attributes. Returned as a list of {code, value} pairs so
     * Magento's webapi marshaller preserves both halves.
     *
     * @return \Combipower\TessAI\Api\Data\AdditionalAttributeInterface[]
     */
    public function getAdditionalAttributes();

    /**
     * @param string $id
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setId($id);

    /**
     * @param string $sku
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setSku($sku);

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
     * @param string|null $supplier
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setSupplier($supplier);

    /**
     * @param string|null $unit
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setUnit($unit);

    /**
     * @param string|null $name
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setName($name);

    /**
     * @param string|null $brand
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setBrand($brand);

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

    /**
     * @param \Combipower\TessAI\Api\Data\AdditionalAttributeInterface[] $additionalAttributes
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function setAdditionalAttributes(array $additionalAttributes);
}
