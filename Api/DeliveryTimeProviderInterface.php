<?php
declare(strict_types=1);

namespace Combipower\TessAI\Api;

use Magento\Catalog\Api\Data\ProductInterface;

interface DeliveryTimeProviderInterface
{
    /**
     * @param ProductInterface $product
     * @return string|null
     */
    public function resolve(ProductInterface $product);
}
