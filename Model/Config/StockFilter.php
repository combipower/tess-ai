<?php
namespace Combipower\TessAI\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class StockFilter
{
    public const XML_PATH_LEGACY_FALLBACK = 'combipower_tess_ai/stock/legacy_fallback';

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
     * Whether a SKU with no inventory_source_item (MSI) rows should fall back to
     * the legacy cataloginventory_stock_item qty (stock_id=1). Off by default —
     * missing MSI data is treated as out of stock. Only relevant when MSI is
     * enabled; governs both the `stock` filter and the `available_stock` value
     * so the two stay consistent.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLegacyFallbackEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LEGACY_FALLBACK,
            ScopeInterface::SCOPE_STORE,
            $this->resolveStoreId($storeId)
        );
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
