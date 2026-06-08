<?php
namespace Combipower\TessAI\Api;

interface PriceUpdateManagementInterface
{
    /**
     * Bulk update product prices by SKU. Each updated product gets
     * `has_tess_price` set to true. Processing is per-item: a failing SKU does
     * not abort the batch, its result row carries the error message instead.
     *
     * @param \Combipower\TessAI\Api\Data\PriceUpdateInterface[] $items
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface[]
     */
    public function updatePrices(array $items);
}
