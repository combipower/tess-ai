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
     * @param string|null $supplier_id
     * @param string|null $brand_id
     * @param string|null $article_number
     * @param string|null $ean
     * @param string|null $stock
     * @param int $page
     * @param int $per_page
     * @param string|null $price_from Lower bound on sales price (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $price_to Upper bound on sales price (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $purchase_price_from Lower bound on `cost` attribute (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $purchase_price_to Upper bound on `cost` attribute (excl. VAT), inclusive. Ignored if < 0.
     * @param string|null $sort_by One of: article_number, description, brand_dge, price, purchase_price, available_stock.
     * @param string|null $sort_order asc or desc. Defaults to desc. Ignored if $sort_by is missing or unknown.
     * @return \Combipower\TessAI\Api\Data\ProductListInterface
     */
    public function getList(
        $category_id = null,
        $supplier_id = null,
        $brand_id = null,
        $article_number = null,
        $ean = null,
        $stock = null,
        $page = 1,
        $per_page = 50,
        $price_from = null,
        $price_to = null,
        $purchase_price_from = null,
        $purchase_price_to = null,
        $sort_by = null,
        $sort_order = null
    );

    /**
     * Return one product row by sku.
     *
     * @param string $sku
     * @return \Combipower\TessAI\Api\Data\ProductInterface
     */
    public function getBySku($sku);
}
