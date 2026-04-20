<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\ProductListInterface;

class ProductList extends AbstractSimpleObject implements ProductListInterface
{
    public function getMeta()
    {
        return $this->_get(self::META);
    }

    public function getItems()
    {
        return $this->_get(self::ITEMS);
    }

    public function setMeta(\Tess\PricingTool\Api\Data\PaginationMetaInterface $meta)
    {
        return $this->setData(self::META, $meta);
    }

    public function setItems(array $items)
    {
        return $this->setData(self::ITEMS, $items);
    }
}
