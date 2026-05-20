<?php
namespace Combipower\TessAI\Block\Adminhtml\Form\Field;

use Combipower\TessAI\Model\Config\Source\ProductAttribute;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

class AdditionalAttributes extends AbstractFieldArray
{
    /**
     * @var AttributeColumn|null
     */
    private $attributeColumnRenderer;

    protected function _prepareToRender()
    {
        $this->addColumn('code', [
            'label' => __('Attribute Code'),
            'renderer' => $this->getAttributeColumnRenderer(),
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Attribute');
    }

    /**
     * @param DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $code = (string) $row->getCode();
        if ($code === '') {
            return;
        }

        $optionExtraAttr = [];
        $renderer = $this->getAttributeColumnRenderer();
        $optionHash = $renderer->calcOptionHash($code);
        $optionExtraAttr['option_' . $optionHash] = 'selected="selected"';

        $row->setData('option_extra_attrs', $optionExtraAttr);
    }

    /**
     * @return AttributeColumn
     */
    private function getAttributeColumnRenderer()
    {
        if ($this->attributeColumnRenderer === null) {
            $this->attributeColumnRenderer = $this->getLayout()->createBlock(
                AttributeColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->attributeColumnRenderer;
    }
}
