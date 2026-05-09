# Combipower_TessAI

Base Magento 2 module for the Combipower TESS AI REST integration.

## Exposed endpoints

- `GET /rest/<store_code>/V1/tessAi/categories`
- `GET /rest/<store_code>/V1/tessAi/filters`
- `GET /rest/<store_code>/V1/tessAi/products`
- `GET /rest/<store_code>/V1/tessAi/products/{sku}`

## Authentication

The API routes are protected by the custom ACL resource:

- `Combipower_TessAI::read`

The admin configuration section uses:

- `Combipower_TessAI::config`

Use an integration token or admin token with the required permission.

## Attribute Mapping Configuration

Product attribute codes used by the API are configurable in Admin:

- `Stores > Configuration > Combipower > TESS AI > Attribute Mapping`

The module reads attribute codes from this configuration per store scope instead of hardcoded codes.

## Shipping Cost Configuration

The API field `shipping_cost` is calculated by creating a temporary quote with the product quantity and collecting Magento shipping rates for the configured destination.

Configure the destination in Admin:

- `Stores > Configuration > Combipower > TESS AI > Shipping Estimate`

Defaults are set to Netherlands:

- Country: `NL`
- Postcode: `1011AC`
- City: `Amsterdam`
- Street: `Dam 1`

If `Shipping Method Code` is empty, the API uses the cheapest available shipping rate. To force one method, enter its Magento rate code, for example `flatrate_flatrate`.

## Activation

Install module via Composer:

```bash
composer config repositories.combipower/tess-ai vcs git@github.com:combipower/tess-ai.git
composer require combipower/tess-ai:dev-main
```

Then enable the module and register it with Magento:

```bash
php bin/magento module:enable Combipower_TessAI
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```
