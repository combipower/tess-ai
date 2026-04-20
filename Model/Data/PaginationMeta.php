<?php
namespace Tess\PricingTool\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use Tess\PricingTool\Api\Data\PaginationMetaInterface;

class PaginationMeta extends AbstractSimpleObject implements PaginationMetaInterface
{
    public function getTotal()
    {
        return $this->_get(self::TOTAL);
    }

    public function getPage()
    {
        return $this->_get(self::PAGE);
    }

    public function getPerPage()
    {
        return $this->_get(self::PER_PAGE);
    }

    public function setTotal($total)
    {
        return $this->setData(self::TOTAL, $total);
    }

    public function setPage($page)
    {
        return $this->setData(self::PAGE, $page);
    }

    public function setPerPage($perPage)
    {
        return $this->setData(self::PER_PAGE, $perPage);
    }
}
