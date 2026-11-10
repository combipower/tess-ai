<?php
declare(strict_types=1);

namespace Combipower\TessAI\Plugin\Webapi;

use Magento\Framework\Reflection\TypeProcessor;

/**
 * Keeps the REST schema (/rest/<store>/schema) working for GET parameters declared as "mixed".
 *
 * ProductManagementInterface::getList() accepts a single value, a comma-separated list or an array
 * for several filters, so those parameters are declared "mixed" (normalized to "anyType"). The Swagger
 * generator treats every non-simple GET parameter as a data object and asks for its type data; "anyType"
 * has none, so TypeProcessor::getTypeData() throws and the whole schema request fails with HTTP 500.
 *
 * Returning empty type data leaves those parameters out of the generated schema. Only the call that
 * would otherwise throw is changed; request handling never asks for the type data of "anyType".
 */
class AnyTypeSchemaPlugin
{
    /**
     * @param TypeProcessor $subject
     * @param callable $proceed
     * @param string $typeName
     * @return array
     */
    public function aroundGetTypeData(TypeProcessor $subject, callable $proceed, $typeName)
    {
        if ($typeName === TypeProcessor::NORMALIZED_ANY_TYPE) {
            return ['documentation' => '', 'parameters' => []];
        }

        return $proceed($typeName);
    }
}
