# Combipower_TessAI

Base Magento 2 module for the Combipower TESS AI REST integration.

## Exposed endpoints

- `GET /rest/<store_code>/V1/tessAi/categories`
- `GET /rest/<store_code>/V1/tessAi/filters`
- `GET /rest/<store_code>/V1/tessAi/products`
- `GET /rest/<store_code>/V1/tessAi/products/{sku}`
- `POST /rest/<store_code>/V1/tessAi/prices` — bulk update prices by SKU (sets `has_tess_price`)

## Authentication

The API routes are protected by custom ACL resources:

- `Combipower_TessAI::read` — all `GET` endpoints
- `Combipower_TessAI::write` — the `POST /V1/tessAi/prices` bulk price update

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

### Shipping Quote Attributes

`shipping_cost` is estimated by building a temporary quote. Carrier table rates and shipping restriction rules often depend on product **dimensions/weight** (e.g. `length`, `width`, `height`, `weight`). If those attributes are not loaded onto the quote product, the estimate evaluates the rules against empty values and may return a cheaper method than the real checkout.

Pick the attributes to load in Admin:

- `Stores > Configuration > Combipower > TESS AI > Shipping Estimate > Shipping Quote Attributes`

Default: `weight`, `length`, `width`, `height`. Add any extra attribute your shipping rules depend on. Non-existing codes are ignored.

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
