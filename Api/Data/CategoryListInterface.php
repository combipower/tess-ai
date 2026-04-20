<?php
namespace Tess\PricingTool\Api\Data;

interface CategoryListInterface
{
    const ITEMS = 'items';

    /**
     * @return \Tess\PricingTool\Api\Data\CategoryInterface[]|null
     */
    public function getItems();

    /**
     * @param \Tess\PricingTool\Api\Data\CategoryInterface[] $items
     * @return \Tess\PricingTool\Api\Data\CategoryListInterface
     */
    public function setItems(array $items);
}
