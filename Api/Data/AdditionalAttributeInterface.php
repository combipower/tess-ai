<?php
namespace Combipower\TessAI\Api\Data;

interface AdditionalAttributeInterface
{
    public const CODE = 'code';
    public const VALUE = 'value';

    /**
     * @return string|null
     */
    public function getCode();

    /**
     * @return string|null
     */
    public function getValue();

    /**
     * @param string $code
     * @return \Combipower\TessAI\Api\Data\AdditionalAttributeInterface
     */
    public function setCode($code);

    /**
     * @param string|null $value
     * @return \Combipower\TessAI\Api\Data\AdditionalAttributeInterface
     */
    public function setValue($value);
}
