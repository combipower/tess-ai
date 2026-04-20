<?php
namespace Tess\PricingTool\Api;

/**
 * Filter API for the pricing tool.
 * @api
 */
interface FilterManagementInterface
{
    /**
     * Return available filter options.
     *
     * @return \Tess\PricingTool\Api\Data\FilterOptionsInterface
     */
    public function getOptions();
}
