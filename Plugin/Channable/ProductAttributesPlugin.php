<?php
namespace Combipower\TessAI\Plugin\Channable;

use Magmodules\Channable\Model\Collection\Products as ChannableProducts;

/**
 * Make sure `bol_price` is selected on the Channable product collections.
 *
 * Channable builds its feed/item-update collections from a hardcoded base
 * attribute list plus whatever the admin configured. Without adding the code
 * here the attribute is simply absent from the loaded products, and
 * PriceDataPlugin would silently read null and never override anything.
 */
class ProductAttributesPlugin
{
    /**
     * @param ChannableProducts $subject
     * @param array $result
     * @return array
     */
    public function afterGetProductAttributes(ChannableProducts $subject, $result)
    {
        if (!is_array($result)) {
            return $result;
        }

        $result[] = PriceDataPlugin::BOL_PRICE_ATTRIBUTE_CODE;

        return $result;
    }
}
