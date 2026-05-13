<?php
declare(strict_types=1);

namespace Combipower\TessAI\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class DeliveryTime
{
    public const XML_PATH_MESSAGE_TEMPLATE = 'combipower_tess_ai/delivery_time/message_template';
    public const XML_PATH_DAYS = 'combipower_tess_ai/delivery_time/days';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getMessageTemplate($storeId = null)
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_MESSAGE_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $this->resolveStoreId($storeId)
        );

        return trim((string) $value);
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getDays($storeId = null)
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_DAYS,
            ScopeInterface::SCOPE_STORE,
            $this->resolveStoreId($storeId)
        );

        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0;
        }

        $days = (int) $value;

        return $days < 0 ? 0 : $days;
    }

    /**
     * @param int|null $storeId
     * @return int|null
     */
    private function resolveStoreId($storeId)
    {
        if ($storeId !== null) {
            return (int) $storeId;
        }

        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
