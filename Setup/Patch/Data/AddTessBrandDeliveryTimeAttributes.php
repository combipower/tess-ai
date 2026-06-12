<?php
namespace Combipower\TessAI\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddTessBrandDeliveryTimeAttributes implements DataPatchInterface
{
    private const ATTRIBUTES = [
        'tess_brand' => [
            'label' => 'TESS Brand',
            'sort_order' => 120,
        ],
        'tess_delivery_time' => [
            'label' => 'TESS Delivery Time',
            'sort_order' => 130,
        ],
    ];

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
     * @return $this
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::ATTRIBUTES as $code => $meta) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }

            $eavSetup->addAttribute(
                Product::ENTITY,
                $code,
                [
                    'type' => 'varchar',
                    'label' => $meta['label'],
                    'input' => 'text',
                    'required' => false,
                    'sort_order' => $meta['sort_order'],
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
        }

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
