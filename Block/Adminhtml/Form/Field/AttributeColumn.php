<?php
namespace Combipower\TessAI\Block\Adminhtml\Form\Field;

use Combipower\TessAI\Model\Config\Source\ProductAttribute;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class AttributeColumn extends Select
{
    /**
     * @var ProductAttribute
     */
    private $productAttributeSource;

    public function __construct(
        Context $context,
        ProductAttribute $productAttributeSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productAttributeSource = $productAttributeSource;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions(
                array_merge(
                    [['value' => '', 'label' => __('-- Select attribute --')]],
                    $this->productAttributeSource->toOptionArray()
                )
            );
        }

        return parent::_toHtml();
    }
}
