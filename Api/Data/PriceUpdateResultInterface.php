<?php
namespace Combipower\TessAI\Api\Data;

interface PriceUpdateResultInterface
{
    public const SKU = 'sku';
    public const SUCCESS = 'success';
    public const MESSAGE = 'message';

    /**
     * @return string
     */
    public function getSku();

    /**
     * @return bool
     */
    public function getSuccess();

    /**
     * @return string|null
     */
    public function getMessage();

    /**
     * @param string $sku
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface
     */
    public function setSku($sku);

    /**
     * @param bool $success
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface
     */
    public function setSuccess($success);

    /**
     * @param string|null $message
     * @return \Combipower\TessAI\Api\Data\PriceUpdateResultInterface
     */
    public function setMessage($message);
}
