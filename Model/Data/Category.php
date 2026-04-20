<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\CategoryInterface;

class Category extends AbstractSimpleObject implements CategoryInterface
{
    public function getId()
    {
        return $this->_get(self::ID);
    }

    public function getName()
    {
        return $this->_get(self::NAME);
    }

    public function getParentId()
    {
        return $this->_get(self::PARENT_ID);
    }

    public function getDepth()
    {
        return $this->_get(self::DEPTH);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    public function setParentId($parentId)
    {
        return $this->setData(self::PARENT_ID, $parentId);
    }

    public function setDepth($depth)
    {
        return $this->setData(self::DEPTH, $depth);
    }
}
