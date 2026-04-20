<?php
namespace Tess\PricingTool\Api\Data;

interface ProductListInterface
{
    const META = 'meta';
    const ITEMS = 'items';

    /**
     * @return \Tess\PricingTool\Api\Data\PaginationMetaInterface|null
     */
    public function getMeta();

    /**
     * @return \Tess\PricingTool\Api\Data\ProductInterface[]|null
     */
    public function getItems();

    /**
     * @param \Tess\PricingTool\Api\Data\PaginationMetaInterface $meta
     * @return \Tess\PricingTool\Api\Data\ProductListInterface
     */
    public function setMeta(\Tess\PricingTool\Api\Data\PaginationMetaInterface $meta);

    /**
     * @param \Tess\PricingTool\Api\Data\ProductInterface[] $items
     * @return \Tess\PricingTool\Api\Data\ProductListInterface
     */
    public function setItems(array $items);
}
