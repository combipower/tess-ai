<?php
declare(strict_types=1);

namespace Combipower\TessAI\Model;

use GbiVarpo\StockInfo\Helper\DeliveryMessage as StockInfoDeliveryMessage;
use Magento\Catalog\Model\Product;

class DeliveryTimeResolver
{
    public function __construct(
        private readonly StockInfoDeliveryMessage $deliveryMessage
    ) {
    }

    /**
     * @param Product $product
     * @return string|null
     */
    public function resolve(Product $product)
    {
        if (!$this->deliveryMessage->isEnabled()) {
            return null;
        }

        $info = $this->deliveryMessage->getMessageForProduct($product);
        $header = (string) ($info['header'] ?? '');

        return $header !== '' ? $header : null;
    }
}
