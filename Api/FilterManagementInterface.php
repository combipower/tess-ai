<?php
namespace Combipower\TessAI\Api;

/**
 * Filter API for the pricing tool.
 * @api
 */
interface FilterManagementInterface
{
    /**
     * Return available filter options.
     *
     * @return \Combipower\TessAI\Api\Data\FilterOptionsInterface
     */
    public function getOptions();
}
