<?php
namespace Combipower\TessAI\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddExtraFeeAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'extra_fee';

    /**
     * Code this attribute shipped under before the typo was corrected.
     */
    private const LEGACY_ATTRIBUTE_CODE = 'extra_free';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Add the `extra_fee` product attribute.
     *
     * Shops that already ran the earlier `AddExtraFreeAttribute` patch carry the
     * misspelled `extra_free` code. Those are renamed in place rather than
     * dropped and recreated, so the attribute id and any stored values survive.
     *
     * @return $this
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if ($eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE)) {
            return $this;
        }

        $legacyAttributeId = $eavSetup->getAttributeId(Product::ENTITY, self::LEGACY_ATTRIBUTE_CODE);
        if ($legacyAttributeId) {
            $eavSetup->updateAttribute(
                Product::ENTITY,
                $legacyAttributeId,
                [
                    'attribute_code' => self::ATTRIBUTE_CODE,
                    'frontend_label' => 'Extra Fee',
                ]
            );

            return $this;
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'decimal',
                'label' => 'Extra Fee',
                'input' => 'text',
                'frontend_class' => 'validate-number',
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => 'TESS AI',
                'visible' => true,
                'user_defined' => true,
                'default' => null,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => '',
            ]
        );

        return $this;
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return [];
    }
}
