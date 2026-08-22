# Combipower_TessAI

Base Magento 2 module for the Combipower TESS AI REST integration.

Full endpoint/field reference lives in [API.md](API.md); this file covers installation and configuration.

## Requirements

- `Magmodules_Channable` (`magmodules/magento2-channable`) — required. The module plugs into Channable to override the exported price (`bol_price`) and to report the push status (`channable_status`).

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

## Product attributes

Created by data patches under `Setup/Patch/Data`, all global scope and grouped under **TESS AI** on the product form:

| Attribute | Type | Purpose |
|---|---|---|
| `has_tess_price` | boolean | Set to `1` whenever `POST /prices` writes a `price` |
| `extra_fee` | decimal | Extra fee / surcharge exposed per sale unit. Renamed from the misspelled `extra_free`; the data patch renames the attribute in place on shops that already have it |
| `tess_brand` | varchar | Brand text from TESS; also resolves/creates the Amasty Shop by Brand option when that module is installed |
| `tess_delivery_time` | varchar | Delivery time text from TESS |
| `bol_price` | decimal | Overrides the price exported to Channable — see below |
| `bol_extra_fee` | decimal | Bol counterpart of `extra_fee`, stored separately. Exposed per sale unit; not part of the Channable export |

All five are writable through `POST /V1/tessAi/prices` and readable through the product endpoints.

## Attribute Mapping Configuration

Product attribute codes used by the API are configurable in Admin:

- `Stores > Configuration > Combipower > TESS AI > Attribute Mapping`

The module reads attribute codes from this configuration per store scope instead of hardcoded codes.

## Channable Integration

### `bol_price` — price override

Marketplace prices differ from the Magento catalog price. Setting `bol_price` on a product replaces the price Channable exports, without touching the catalog price customers see on the storefront.

- Stored **excluding VAT**, the same basis as the regular `price` attribute.
- Active only when the value is `> 0`. Empty, `0` or negative means "no override" and the catalog price is exported.
- Applies to **both** Channable channels: the pull feed and the incremental item-update webhook. Both read the same `price` key, which is rewritten by `Plugin\Channable\PriceDataPlugin` (an `after` plugin on `Magmodules\Channable\Service\Product\PriceData::execute`).
- Discount fields (`sale_price`, `sale_price_effective_date`, discount percentage) are dropped from the export, because a bol price is treated as final. Leaving them would make Channable render a discount against a different base.
- The override applies to **every** Channable channel, not just Bol — the item-update webhook has a fixed payload schema with a single `price` field, so a per-channel override is not possible through that path.

`Plugin\Channable\ProductAttributesPlugin` appends `bol_price` to the attribute list Channable selects on its collections. Without it the attribute would be absent from the loaded products and the override would silently never fire.

**Configurable products:** Channable exports configurables through their child variants (`magmodules_channable/types/configurable = simple`), so `bol_price` must be set on the **child SKU**. Setting it on the parent has no effect on what is pushed.

### `channable_status` — push status

The product endpoints expose a read-only `channable_status` field. Nothing is persisted: `Model\ChannableStatusResolver` derives it per request from Channable itself, so the value never goes stale.

| Value | Meaning |
|---|---|
| `not_listed` | Does not match the Channable feed filters (category / type / status) |
| `excluded` | Listed, but flagged `exclude_for_update` in Channable's item grid |
| `failed` | Last push was rejected by Channable or failed at transport level |
| `pending` | Listed and waiting — never pulled yet, or `needs_update = 1` |
| `synced` | Listed and up to date |

Evaluated top-down, first match wins. The field is omitted entirely when `Magmodules_Channable` is disabled.

Two queries per response page:

1. `Magmodules\Channable\Model\Collection\Products::getCollection()` narrowed to the page's ids, read through `getAllIds()` — reusing Channable's own filters instead of re-implementing them keeps this in step with Channable upgrades.
2. A primary-key lookup on `channable_items`. That table has no index on `id`, but Channable derives `item_id` as the store id concatenated with the zero-padded product id, so the ids are rebuilt and matched on the primary key.

### Known limitation

`Magmodules\Channable\Model\Collection\Products::joinPriceIndexLeft()` hardcodes the `catalog_product_index_price` table name. On a shop running `indexer/catalog_product_price/dimensions_mode = website` the real data lives in `catalog_product_index_price_ws<id>`, so `final_price` / `min_price` / `max_price` come back `NULL`.

Simple products are unaffected — Channable falls back to `Product::getFinalPrice()`. Configurable products are exported with price `0.00`, because `PriceData::execute()` zeroes the price when `final_price` is null. Setting `bol_price` masks the problem for that product; configurables without an override remain affected.

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

Run `setup:upgrade` **before** `setup:di:compile`: the Channable plugin selects `bol_price` on the product collection, and the attribute has to exist by then.
