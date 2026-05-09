<?php
namespace Combipower\TessAI\Api\Data;

interface ProductListInterface
{
    const META = 'meta';
    const ITEMS = 'items';

    /**
     * @return \Combipower\TessAI\Api\Data\PaginationMetaInterface|null
     */
    public function getMeta();

    /**
     * @return \Combipower\TessAI\Api\Data\ProductInterface[]|null
     */
    public function getItems();

    /**
     * @param \Combipower\TessAI\Api\Data\PaginationMetaInterface $meta
     * @return \Combipower\TessAI\Api\Data\ProductListInterface
     */
    public function setMeta(\Combipower\TessAI\Api\Data\PaginationMetaInterface $meta);

    /**
     * @param \Combipower\TessAI\Api\Data\ProductInterface[] $items
     * @return \Combipower\TessAI\Api\Data\ProductListInterface
     */
    public function setItems(array $items);
}
