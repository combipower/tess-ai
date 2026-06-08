<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;
use Combipower\TessAI\Api\PriceUpdateManagementInterface;
use Combipower\TessAI\Api\Data\PriceUpdateResultInterfaceFactory;

class PriceUpdateManagement implements PriceUpdateManagementInterface
{
    private const HAS_TESS_PRICE_ATTRIBUTE_CODE = 'has_tess_price';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var PriceUpdateResultInterfaceFactory
     */
    private $resultFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        PriceUpdateResultInterfaceFactory $resultFactory,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
    }

    /**
     * @param \Combipower\TessAI\Api\Data\PriceUpdateInterface[] $items
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface[]
     */
    public function updatePrices(array $items)
    {
        $results = [];
        foreach ($items as $item) {
            $sku = trim((string) $item->getSku());
            $result = $this->resultFactory->create()->setSku($sku);

            try {
                $this->applyUpdate($sku, $item->getPrice(), $item->getSpecialPrice());
                $result->setSuccess(true)->setMessage(null);
            } catch (NoSuchEntityException $exception) {
                $result->setSuccess(false)->setMessage((string) $exception->getMessage());
            } catch (\Throwable $exception) {
                $this->logger->error('Combipower_TessAI price update failed', [
                    'sku' => $sku,
                    'exception' => $exception,
                ]);
                $result->setSuccess(false)->setMessage((string) $exception->getMessage());
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Load the product in default scope, apply the new price (+ optional
     * special price), flag it as TESS-priced and persist.
     *
     * @param string $sku
     * @param mixed $price
     * @param mixed $specialPrice
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function applyUpdate($sku, $price, $specialPrice)
    {
        if ($sku === '') {
            throw new \Magento\Framework\Exception\LocalizedException(__('SKU is required.'));
        }

        $normalizedPrice = $this->normalizePrice($price);
        if ($normalizedPrice === null) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('A valid non-negative price is required for SKU "%1".', $sku)
            );
        }

        // editMode=true returns an editable product, default store scope so the
        // price is written to the admin/default value (global price scope).
        $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
        $product->setStoreId(Store::DEFAULT_STORE_ID);
        $product->setPrice($normalizedPrice);

        if ($specialPrice !== null && $specialPrice !== '') {
            $normalizedSpecial = $this->normalizePrice($specialPrice);
            if ($normalizedSpecial === null) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Invalid special price for SKU "%1".', $sku)
                );
            }
            $product->setSpecialPrice($normalizedSpecial);
        }

        $product->setData(self::HAS_TESS_PRICE_ATTRIBUTE_CODE, 1);

        $this->productRepository->save($product);
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function normalizePrice($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < 0) {
            return null;
        }

        return $float;
    }
}
