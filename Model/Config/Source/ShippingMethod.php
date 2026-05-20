<?php
namespace Combipower\TessAI\Model\Config\Source;

use Combipower\TessAI\Model\Config\ShippingEstimate;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\Group;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Shipping\Model\Config\Source\Allmethods;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class ShippingMethod implements OptionSourceInterface
{
    /**
     * @var Allmethods
     */
    private $allMethods;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var ShippingEstimate
     */
    private $shippingEstimate;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var array|null
     */
    private $options;

    public function __construct(
        Allmethods $allMethods,
        QuoteFactory $quoteFactory,
        DataObjectFactory $dataObjectFactory,
        ProductCollectionFactory $productCollectionFactory,
        ShippingEstimate $shippingEstimate,
        StoreManagerInterface $storeManager
    ) {
        $this->allMethods = $allMethods;
        $this->quoteFactory = $quoteFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->shippingEstimate = $shippingEstimate;
        $this->storeManager = $storeManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $options = $this->buildFromQuoteCollection();
        if (empty($options)) {
            // Destination unconfigured, no sample product, or carriers
            // returned no usable rate → fall back to Magento's static
            // registry so admins always get a workable dropdown.
            $options = $this->allMethods->toOptionArray(true);
        }

        array_unshift($options, [
            'value' => '',
            'label' => __('-- Use cheapest available rate --'),
        ]);

        return $this->options = $options;
    }

    /**
     * Spin up a temp quote with a real sample product and collect rates
     * against the configured destination. Mirrors the runtime flow used by
     * ShippingCostResolver so carriers that iterate items (Amasty Shipping
     * Rates, custom carriers, etc.) actually return their methods —
     * RateRequest with an empty items list would make those carriers skip.
     *
     * @return array
     */
    private function buildFromQuoteCollection()
    {
        if ($this->shippingEstimate->getCountryId() === null) {
            return [];
        }

        $store = $this->resolveFrontendStore();
        if (!$store) {
            return [];
        }

        $product = $this->loadSampleProduct($store);
        if (!$product) {
            return [];
        }

        try {
            $quote = $this->quoteFactory->create();
            $quote->setStore($store);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
            $quote->setIsActive(false);

            $request = $this->dataObjectFactory->create(['data' => ['qty' => 1]]);
            $item = $quote->addProduct($product, $request);
            if (is_string($item)) {
                return [];
            }

            $address = $quote->getShippingAddress();
            $address->setCountryId($this->shippingEstimate->getCountryId());
            $address->setRegionId($this->shippingEstimate->getRegionId());
            $address->setRegion($this->shippingEstimate->getRegion());
            $address->setPostcode($this->shippingEstimate->getPostcode());
            $address->setCity($this->shippingEstimate->getCity());
            $address->setStreet($this->shippingEstimate->getStreet());
            $address->setCollectShippingRates(true);

            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();

            $rates = $address->getAllShippingRates();
        } catch (\Throwable $exception) {
            return [];
        }

        if (empty($rates)) {
            return [];
        }

        $byCarrier = [];
        foreach ($rates as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }

            $carrier = (string) $rate->getCarrier();
            $method = (string) $rate->getMethod();
            if ($carrier === '' || $method === '') {
                continue;
            }

            $carrierTitle = (string) ($rate->getCarrierTitle() ?: $carrier);
            $byCarrier[$carrierTitle][] = [
                'value' => $carrier . '_' . $method,
                'label' => (string) ($rate->getMethodTitle() ?: $method),
            ];
        }

        $options = [];
        foreach ($byCarrier as $carrierTitle => $methods) {
            $options[] = [
                'label' => $carrierTitle,
                'value' => $methods,
            ];
        }

        return $options;
    }

    /**
     * Admin context defaults to store_id=0; carrier configs are
     * store-scoped, so pick a real frontend store before collecting rates.
     *
     * @return StoreInterface|null
     */
    private function resolveFrontendStore()
    {
        try {
            $current = $this->storeManager->getStore();
            if ($current && (int) $current->getId() > 0) {
                return $current;
            }

            $default = $this->storeManager->getDefaultStoreView();
            if ($default && (int) $default->getId() > 0) {
                return $default;
            }

            foreach ($this->storeManager->getStores() as $store) {
                if ($store->getIsActive() && (int) $store->getId() > 0) {
                    return $store;
                }
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    /**
     * @param StoreInterface $store
     * @return \Magento\Catalog\Model\Product|null
     */
    private function loadSampleProduct(StoreInterface $store)
    {
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId((int) $store->getId());
            $collection->addAttributeToFilter('status', 1);
            $collection->addAttributeToFilter('type_id', ['in' => ['simple', 'virtual']]);
            $collection->joinField(
                'is_in_stock',
                'cataloginventory_stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );
            $collection->addFieldToFilter('is_in_stock', 1);
            $collection->setPageSize(1);
            $items = $collection->getItems();

            return $items ? reset($items) : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
