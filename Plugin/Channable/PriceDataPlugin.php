<?php
namespace Combipower\TessAI\Plugin\Channable;

use Magento\Catalog\Model\Product;
use Magmodules\Channable\Service\Product\PriceData;

/**
 * Override the price Channable exports with the `bol_price` product attribute.
 *
 * Hooking PriceData::execute() covers both Channable channels at once: the feed
 * (Channable pulls it, Item::add() stores the row) and the incremental item
 * update webhook (Item::getPostData() reads the very same `price` key). The
 * value is stored excluding VAT, matching the regular `price` attribute, so it
 * is injected through Channable's own processPrice() to keep the exchange rate
 * and formatting identical to a non-overridden product.
 */
class PriceDataPlugin
{
    public const BOL_PRICE_ATTRIBUTE_CODE = 'bol_price';

    /**
     * Keys of `price_config` holding the min/max range fields.
     */
    private const RANGE_CONFIG_KEYS = [
        'min_price',
        'max_price',
    ];

    /**
     * Keys of `price_config` holding discount-related fields. A bol price is a
     * final price, so leaving a sale price derived from the catalog price would
     * make Channable render a discount against a different base.
     */
    private const DISCOUNT_CONFIG_KEYS = [
        'sales_price',
        'sales_price_excl',
        'sales_price_incl',
        'sales_date_range',
        'discount_perc',
    ];

    /**
     * @param PriceData $subject
     * @param array $result
     * @param array $config
     * @param Product $product
     * @return array
     */
    public function afterExecute(PriceData $subject, $result, array $config, Product $product)
    {
        $bolPrice = $this->resolveBolPrice($product);
        if ($bolPrice === null) {
            return $result;
        }

        $priceConfig = isset($config['price_config']) && is_array($config['price_config'])
            ? $config['price_config']
            : [];
        if (empty($priceConfig['price'])) {
            return $result;
        }

        $formatted = $subject->processPrice($product, $bolPrice, $priceConfig);
        $result[$priceConfig['price']] = $formatted;

        if (!empty($priceConfig['tax_include_both'])) {
            if (!empty($priceConfig['price_excl'])) {
                $result[$priceConfig['price_excl']] = $subject->processPrice(
                    $product,
                    $bolPrice,
                    $priceConfig,
                    false
                );
            }
            if (!empty($priceConfig['price_incl'])) {
                $result[$priceConfig['price_incl']] = $subject->processPrice(
                    $product,
                    $bolPrice,
                    $priceConfig,
                    true
                );
            }
        }

        // A bol price collapses the range to a single value. Only rewrite the
        // fields Channable already emitted — adding new ones would change the
        // feed shape for products that never had a range.
        foreach (self::RANGE_CONFIG_KEYS as $configKey) {
            if (!empty($priceConfig[$configKey]) && array_key_exists($priceConfig[$configKey], $result)) {
                $result[$priceConfig[$configKey]] = $formatted;
            }
        }

        foreach (self::DISCOUNT_CONFIG_KEYS as $configKey) {
            if (!empty($priceConfig[$configKey])) {
                unset($result[$priceConfig[$configKey]]);
            }
        }

        return $result;
    }

    /**
     * Return the override price, or null when the product has none. A zero or
     * negative value counts as "no override" so a stray 0 cannot wipe out the
     * exported price.
     *
     * @param Product $product
     * @return float|null
     */
    private function resolveBolPrice(Product $product)
    {
        $value = $product->getData(self::BOL_PRICE_ATTRIBUTE_CODE);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $price = (float) $value;

        return $price > 0.0 ? $price : null;
    }
}
