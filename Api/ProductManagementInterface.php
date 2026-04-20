<?php
namespace Tess\PricingTool\Api;

/**
 * Product read API for the pricing tool.
 * @api
 */
interface ProductManagementInterface
{
    /**
     * Return the pricing-tool product grid data.
     *
     * @param string|null $category_id
     * @param string|null $supplier_id
     * @param string|null $brand_id
     * @param string|null $article_number
     * @param string|null $ean
     * @param string|null $stock
     * @param int $page
     * @param int $per_page
     * @return \Tess\PricingTool\Api\Data\ProductListInterface
     */
    public function getList(
        $category_id = null,
        $supplier_id = null,
        $brand_id = null,
        $article_number = null,
        $ean = null,
        $stock = null,
        $page = 1,
        $per_page = 50
    );

    /**
     * Return one product row by sku.
     *
     * @param string $sku
     * @return \Tess\PricingTool\Api\Data\ProductInterface
     */
    public function getBySku($sku);
}
