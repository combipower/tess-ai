<?php
namespace Combipower\TessAI\Api;

/**
 * Category API for the pricing tool.
 * @api
 */
interface CategoryManagementInterface
{
    /**
     * Return the category tree for the current store.
     *
     * @return \Combipower\TessAI\Api\Data\CategoryListInterface
     */
    public function getList();
}
