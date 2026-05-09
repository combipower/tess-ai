<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\OptionInterface;

class Option extends AbstractSimpleObject implements OptionInterface
{
    public function getId()
    {
        return $this->_get(self::ID);
    }

    public function getName()
    {
        return $this->_get(self::NAME);
    }

    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }
}
