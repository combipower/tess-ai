<?php
namespace Combipower\TessAI\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddBolPriceAttribute implements DataPatchInterface
{
    public const ATTRIBUTE_CODE = 'bol_price';

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
     * Add the `bol_price` product attribute used to override the price pushed
     * to Channable. Scope is GLOBAL to match `catalog/price/scope = 0`, and the
     * default stays null so "no override" is distinguishable from a real 0.
     *
     * @return $this
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Never drop an existing attribute here: removing it would delete every
        // stored value on every product.
        if ($eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE)) {
            return $this;
        }

        $eavSetup->addAttribute(
            Product::ENTITY,
            self::ATTRIBUTE_CODE,
            [
                'type' => 'decimal',
                'label' => 'Bol Price',
                'input' => 'price',
                'required' => false,
                'sort_order' => 120,
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
                'note' => 'Overrides the price pushed to Channable. Excluding VAT,'
                    . ' same basis as the regular price. Leave empty to use the catalog price.',
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
