<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\CategoryListInterface;

class CategoryList extends AbstractSimpleObject implements CategoryListInterface
{
    public function getItems()
    {
        return $this->_get(self::ITEMS);
    }

    public function setItems(array $items)
    {
        return $this->setData(self::ITEMS, $items);
    }
}
