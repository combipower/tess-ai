<?php
namespace Combipower\TessAI\Model;

use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;
use Psr\Log\LoggerInterface;

/**
 * Resolve / create brand attribute options for Amasty Shop by Brand.
 *
 * Tess sends brand as free text. When Amasty_ShopbyBrand is installed, the
 * brand is an option of the configured select attribute (default
 * `manufacture_brand`). This service finds the option by label
 * (case-insensitive, trimmed) or creates it, returning the option id so the
 * product can be assigned to the brand.
 */
class ShopByBrandResolver
{
    private const MODULE = 'Amasty_ShopbyBrand';
    private const XML_PATH_BRAND_ATTRIBUTE = 'amshopby_brand/general/attribute_code';
    private const PRODUCT_ENTITY = 'catalog_product';
    private const ADMIN_STORE_ID = 0;

    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AttributeOptionManagementInterface
     */
    private $attributeOptionManagement;

    /**
     * @var AttributeOptionInterfaceFactory
     */
    private $optionFactory;

    /**
     * @var AttributeOptionLabelInterfaceFactory
     */
    private $optionLabelFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool|null
     */
    private $enabled = null;

    /**
     * @var string|null|false
     */
    private $brandAttributeCode = false;

    /**
     * @var array<string, int|null>
     */
    private $optionCache = [];

    public function __construct(
        ModuleManager $moduleManager,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionInterfaceFactory $optionFactory,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        LoggerInterface $logger
    ) {
        $this->moduleManager = $moduleManager;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->optionFactory = $optionFactory;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->enabled === null) {
            $this->enabled = $this->moduleManager->isEnabled(self::MODULE);
        }

        return $this->enabled;
    }

    /**
     * The product attribute code Amasty Shop by Brand uses for brands.
     *
     * @return string|null
     */
    public function getBrandAttributeCode()
    {
        if ($this->brandAttributeCode === false) {
            $value = trim((string) $this->scopeConfig->getValue(self::XML_PATH_BRAND_ATTRIBUTE));
            $this->brandAttributeCode = $value !== '' ? $value : null;
        }

        return $this->brandAttributeCode;
    }

    /**
     * Find the brand option id by label (case-insensitive, trimmed) or create
     * a new option. Returns null when the module/attribute is unavailable or on
     * failure. Cached per request.
     *
     * @param string $label
     * @return int|null
     */
    public function getOrCreateOptionId($label)
    {
        $label = trim((string) $label);
        if ($label === '' || !$this->isEnabled()) {
            return null;
        }

        $attributeCode = $this->getBrandAttributeCode();
        if ($attributeCode === null) {
            return null;
        }

        $cacheKey = $attributeCode . '|' . mb_strtolower($label);
        if (array_key_exists($cacheKey, $this->optionCache)) {
            return $this->optionCache[$cacheKey];
        }

        $optionId = $this->findOptionId($attributeCode, $label);
        if ($optionId === null) {
            $optionId = $this->createOption($attributeCode, $label);
        }

        $this->optionCache[$cacheKey] = $optionId;

        return $optionId;
    }

    /**
     * @param string $attributeCode
     * @param string $label
     * @return int|null
     */
    private function findOptionId($attributeCode, $label)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from(['o' => $connection->getTableName('eav_attribute_option')], ['option_id'])
                ->join(
                    ['v' => $connection->getTableName('eav_attribute_option_value')],
                    'v.option_id = o.option_id AND v.store_id = ' . self::ADMIN_STORE_ID,
                    []
                )
                ->join(
                    ['a' => $connection->getTableName('eav_attribute')],
                    'a.attribute_id = o.attribute_id',
                    []
                )
                ->join(
                    ['t' => $connection->getTableName('eav_entity_type')],
                    't.entity_type_id = a.entity_type_id',
                    []
                )
                ->where('t.entity_type_code = ?', self::PRODUCT_ENTITY)
                ->where('a.attribute_code = ?', $attributeCode)
                ->where('LOWER(TRIM(v.value)) = ?', mb_strtolower($label))
                ->limit(1);

            $optionId = $connection->fetchOne($select);
        } catch (\Throwable $exception) {
            $this->logger->error('Combipower_TessAI: brand option lookup failed', [
                'label' => $label,
                'exception' => $exception,
            ]);

            return null;
        }

        return $optionId !== false && $optionId !== null ? (int) $optionId : null;
    }

    /**
     * @param string $attributeCode
     * @param string $label
     * @return int|null
     */
    private function createOption($attributeCode, $label)
    {
        try {
            $optionLabel = $this->optionLabelFactory->create();
            $optionLabel->setStoreId(self::ADMIN_STORE_ID);
            $optionLabel->setLabel($label);

            $option = $this->optionFactory->create();
            $option->setLabel($label);
            $option->setStoreLabels([$optionLabel]);
            $option->setSortOrder(0);
            $option->setIsDefault(false);

            $this->attributeOptionManagement->add(self::PRODUCT_ENTITY, $attributeCode, $option);
        } catch (\Throwable $exception) {
            $this->logger->error('Combipower_TessAI: brand option create failed', [
                'label' => $label,
                'exception' => $exception,
            ]);

            return null;
        }

        // add() does not reliably return the new id across versions — re-read it.
        return $this->findOptionId($attributeCode, $label);
    }
}
