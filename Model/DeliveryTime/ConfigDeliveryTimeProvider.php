<?php
declare(strict_types=1);

namespace Combipower\TessAI\Model\DeliveryTime;

use Combipower\TessAI\Api\DeliveryTimeProviderInterface;
use Combipower\TessAI\Model\Config\DeliveryTime as DeliveryTimeConfig;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ConfigDeliveryTimeProvider implements DeliveryTimeProviderInterface
{
    private const DATE_PLACEHOLDER = '[DATE]';
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @var DeliveryTimeConfig
     */
    private $config;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    public function __construct(
        DeliveryTimeConfig $config,
        TimezoneInterface $timezone
    ) {
        $this->config = $config;
        $this->timezone = $timezone;
    }

    /**
     * @param ProductInterface $product
     * @return string|null
     */
    public function resolve(ProductInterface $product)
    {
        $template = $this->config->getMessageTemplate();
        if ($template === '') {
            return null;
        }

        if (strpos($template, self::DATE_PLACEHOLDER) === false) {
            return $template;
        }

        try {
            $date = $this->timezone->scopeDate(null, null, true);
            $date = $date->modify(sprintf('+%d days', $this->config->getDays()));
        } catch (\Throwable $exception) {
            return null;
        }

        return str_replace(self::DATE_PLACEHOLDER, $date->format(self::DATE_FORMAT), $template);
    }
}
