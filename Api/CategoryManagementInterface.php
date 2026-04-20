<?php
namespace Tess\PricingTool\Api;

/**
 * Category API for the pricing tool.
 * @api
 */
interface CategoryManagementInterface
{
    /**
     * Return the category tree for the current store.
     *
     * @return \Tess\PricingTool\Api\Data\CategoryListInterface
     */
    public function getList();
}
