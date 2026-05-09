<?php
namespace Combipower\TessAI\Api\Data;

interface CategoryListInterface
{
    const ITEMS = 'items';

    /**
     * @return \Combipower\TessAI\Api\Data\CategoryInterface[]|null
     */
    public function getItems();

    /**
     * @param \Combipower\TessAI\Api\Data\CategoryInterface[] $items
     * @return \Combipower\TessAI\Api\Data\CategoryListInterface
     */
    public function setItems(array $items);
}
