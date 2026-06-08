<?php
namespace Combipower\TessAI\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Combipower\TessAI\Api\Data\PriceUpdateResultInterface;

class PriceUpdateResult extends AbstractSimpleObject implements PriceUpdateResultInterface
{
    public function getSku()
    {
        return $this->_get(self::SKU);
    }

    public function getSuccess()
    {
        return (bool) $this->_get(self::SUCCESS);
    }

    public function getMessage()
    {
        return $this->_get(self::MESSAGE);
    }

    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    public function setSuccess($success)
    {
        return $this->setData(self::SUCCESS, $success);
    }

    public function setMessage($message)
    {
        return $this->setData(self::MESSAGE, $message);
    }
}
