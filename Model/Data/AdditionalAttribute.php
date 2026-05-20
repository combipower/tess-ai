<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\AdditionalAttributeInterface;

class AdditionalAttribute extends AbstractSimpleObject implements AdditionalAttributeInterface
{
    public function getCode()
    {
        return $this->_get(self::CODE);
    }

    public function getValue()
    {
        return $this->_get(self::VALUE);
    }

    public function setCode($code)
    {
        return $this->setData(self::CODE, $code);
    }

    public function setValue($value)
    {
        return $this->setData(self::VALUE, $value);
    }
}
