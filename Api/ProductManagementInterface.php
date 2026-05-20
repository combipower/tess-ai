<?php
namespace Combipower\TessAI\Api;

/**
 * Product read API for the pricing tool.
 * @api
 */
interface ProductManagementInterface
{
    /**
     * Return the pricing-tool product grid data.
     *
     * @param mixed|null $category_id Category ID, comma-separated IDs, or array of IDs.
     * @param mixed|null $supplier_id Single value or array of values (OR).
     * @param mixed|null $brand_id Single value or array of values (OR).
     * @param mixed|null $sku Single value or array of values (OR-LIKE on SKU).
     * @param mixed|null $ean Single value or array of values (OR-LIKE).
     * @param string|null $stock In-stock filter. Reads `cataloginventory_stock_item.is_in_stock` (legacy default-stock column). On shops with `Magento_InventoryApi` (MSI) the legacy table can drift from per-source quantities — this filter mirrors that legacy view, not the MSI-aware `available_stock` shown in the response.
     * @param int $page
     * @param int $per_page
     * @param string|null $price_from Lower bound on sales price (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $price_to Upper bound on sales price (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $purchase_price_from Lower bound on `cost` attribute (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $purchase_price_to Upper bound on `cost` attribute (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $sort_by One of: sku, name, brand, price, purchase_price, available_stock. Note: `available_stock` sorts on `cataloginventory_stock_item.qty` (legacy default-stock column); on MSI shops the ordering may not match the MSI-aware `available_stock` value returned in the response if the legacy table is out of sync with the per-source quantities.
     * @param string|null $sort_order asc or desc. Defaults to desc. Ignored if $sort_by is missing or unknown.
     * @param string[] $attr Map of additional attribute filters keyed by attribute code. Each value can be a scalar or array; operator is auto (varchar/text → LIKE, others → exact). Only codes configured in Admin → Additional Attributes are honored.
     * @return \Combipower\TessAI\Api\Data\ProductListInterface
     */
    public function getList(
        $category_id = null,
        $supplier_id = null,
        $brand_id = null,
        $sku = null,
        $ean = null,
        $stock = null,
        $page = 1,
        $per_page = 50,
        $price_from = null,
        $price_to = null,
        $purchase_price_from = null,
        $purchase_price_to = null,
        $sort_by = null,
        $sort_order = null,
        $attr = []
    );

    /**
     * Return one product row by sku.
     *
     * @param string $sku
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function getBySku($sku);
}
