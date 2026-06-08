# TESS AI REST API

Module: `Combipower_TessAI`

Base URL: `https://<your-domain>/rest/<store_code>/V1/tessAi/...`

`<store_code>` is optional — omit to use the default store: `https://<your-domain>/rest/V1/tessAi/...`

---

## 1. Authentication

All endpoints require a Bearer token with ACL resource `Combipower_TessAI::read`.

```
Authorization: Bearer <access_token>
```

### Production: Integration token (recommended)

In Magento Admin:

1. `System > Extensions > Integrations > Add New Integration`
2. Fill **Name** + **Email**
3. Tab **API > Resources** → tick `TESS Pricing Tool API > Read Pricing Tool Data`
4. Save → click **Activate** → copy the **Access Token**

Integration tokens do not expire until revoked.

---

## 2. Endpoint Summary

| Method | Path | Description | Paging |
|---|---|---|---|
| GET | `/V1/tessAi/categories` | Category tree of the active store | No |
| GET | `/V1/tessAi/filters` | Available filter options (suppliers, brands, article numbers) | No |
| GET | `/V1/tessAi/products` | List products with filters | Yes |
| GET | `/V1/tessAi/products/{sku}` | Get one product by SKU | — |
| POST | `/V1/tessAi/prices` | Bulk update prices by SKU (sets `has_tess_price`) | — |

> **Note:** the read endpoints require ACL `Combipower_TessAI::read`. The write endpoint `POST /V1/tessAi/prices` requires `Combipower_TessAI::write` — grant it under *API > Resources > TESS Pricing Tool API > Update Pricing Tool Data*.

---

## 3. `GET /V1/tessAi/categories`

Returns active categories under the current store's root, **nested as a tree** via the `children` array. Only top-level categories are at the root of `items`; their descendants live recursively in `children`. Leaves have `children: []`.

### Response

```json
{
  "items": [
    {
      "id": "1745",
      "name": "Tools",
      "parent_id": null,
      "depth": 0,
      "children": [
        {
          "id": "1746",
          "name": "Hand tools",
          "parent_id": "1745",
          "depth": 1,
          "children": [
            {
              "id": "1750",
              "name": "Wrenches",
              "parent_id": "1746",
              "depth": 2,
              "children": []
            }
          ]
        },
        {
          "id": "1747",
          "name": "Power tools",
          "parent_id": "1745",
          "depth": 1,
          "children": []
        }
      ]
    },
    {
      "id": "1900",
      "name": "Accessories",
      "parent_id": null,
      "depth": 0,
      "children": []
    }
  ]
}
```

| Field | Type | Notes |
|---|---|---|
| `id` | string | Category entity_id |
| `name` | string | Localized name (per store) |
| `parent_id` | string \| null | `null` for top-level under store root |
| `depth` | int | 0 = top-level, 1 = sub, 2 = sub-sub, ... |
| `children` | array | Nested children. Empty array `[]` if leaf |

### Example

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/categories"
```

### Walking the tree (JS)

```js
function walk(nodes, fn, depth = 0) {
  nodes.forEach(n => { fn(n, depth); walk(n.children || [], fn, depth + 1); });
}
walk(response.items, (n, d) => console.log('  '.repeat(d) + n.name));
```

---

## 4. `GET /V1/tessAi/filters`

Returns available options for the three main filters used by `/products`.

### Response

```json
{
  "suppliers":       [ { "id": "12",       "name": "Acme NL" } ],
  "brands":          [ { "id": "5",        "name": "BOSCH"  } ],
  "skus":            [ { "id": "SKU-001",  "name": "SKU-001" } ]
}
```

| Group | Source | Notes |
|---|---|---|
| `suppliers` | Options of the configured supplier attribute | `id` = option_id (or text value if attribute is text) |
| `brands` | Options of the configured brand attribute | Same as above |
| `skus` | All SKUs in the catalog | `id` = `name` = SKU |

### Example

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/filters"
```

---

## 5. `GET /V1/tessAi/products`

List products with optional filters and pagination.

### Default filters (always applied)

The following filters are applied implicitly — disabled / hidden / unsupported products are never returned:

| Filter | Value |
|---|---|
| Status | `enabled` only (excludes disabled) |
| Visibility | `Catalog`, `Search`, `Catalog & Search` (excludes "Not Visible Individually" — typically configurable child variants) |
| Product type | `simple`, `virtual`, `configurable`, `downloadable` only (excludes `bundle`, `grouped`, etc.) |
| Store website | Current store's website (via `store_code` in the URL) |

### Query parameters

| Param | Type | Default | Description |
|---|---|---|---|
| `category_id` | int / CSV / array | — | One ID, comma-list `1,2,3`, or repeated `category_id[]=1&category_id[]=2`. Filters via `category_product` table |
| `supplier_id` | string | — | Exact match on the supplier attribute value |
| `brand_id` | string | — | Exact match on the brand attribute value |
| `sku` | string / string[] | — | Partial match: `LIKE %value%` on SKU. Multi-value → OR of LIKEs. |
| `ean` | string | — | Partial match: `LIKE %value%` on barcode attribute |
| `stock` | string | — | `1` / `true` / `in_stock` / `in-stock` → in-stock only. `0` / `false` / `out_of_stock` / `out-of-stock` → out-of-stock only. Empty = no filter. Reads `cataloginventory_stock_item.is_in_stock` (legacy default-stock column) — on MSI shops the filter may admit/reject rows that the MSI-aware `available_stock` value in the response would not agree with, if the legacy table is out of sync. |
| `price_from` | float | — | Lower bound on sales price (excl. VAT), inclusive. Compared against `catalog_product_index_price.min_price`, so configurable parents are included by their cheapest variant. Negative values are ignored. |
| `price_to` | float | — | Upper bound on sales price (excl. VAT), inclusive. Same semantics as `price_from`. |
| `purchase_price_from` | float | — | Lower bound on the `cost` attribute (excl. VAT), inclusive. Filtered via EAV — configurable parents (which store `cost` only on children) are excluded when set. Negative values are ignored. |
| `purchase_price_to` | float | — | Upper bound on the `cost` attribute (excl. VAT), inclusive. Same semantics as `purchase_price_from`. |
| `sort_by` | string | — | One of `sku`, `name`, `brand`, `price`, `purchase_price`, `available_stock`. Unknown / missing values fall back to default order. `order_number` is **not** sortable in this phase (computed field). `available_stock` sorts on the legacy `cataloginventory_stock_item.qty` column — may differ from the MSI-aware `available_stock` value in the response if your shop's legacy table is out of sync with MSI sources. |
| `sort_order` | string | `desc` | `asc` or `desc` (case-insensitive). Ignored when `sort_by` is missing or unknown. Any other value falls back to `desc`. |
| `page` | int | `1` | Min 1 |
| `per_page` | int | `50` | Min 1, max 200 (clamped) |
| `attr[code]` | string / string[] | — | Filter by any extra attribute configured in Admin → Combipower → TESS AI → Additional Attributes. Operator is auto-selected: `varchar` / `text` → `LIKE %v%`, all other backends and source-using attributes (select/multiselect) → exact match. Codes not in the Additional Attributes list are ignored. |

**Multi-value filters.** `supplier_id`, `brand_id`, `sku`, `ean`, and every `attr[code]` accept either a single scalar or a repeated/array syntax. Multiple values are OR-joined (exact attributes → SQL `IN`; LIKE attributes → `OR LIKE`). Single-value syntax stays backwards-compatible.

```
# single
?supplier_id=10
?attr[color]=red

# multi (OR)
?supplier_id[]=10&supplier_id[]=11
?ean[]=871&ean[]=872
?attr[color][]=red&attr[color][]=blue
```

**Pagination stability:** results are always tie-broken by `entity_id ASC` as a secondary sort, so paging is deterministic even when many rows share the same `sort_by` value.

### Response

```json
{
  "meta": {
    "total": 1234,
    "page": 1,
    "per_page": 50
  },
  "items": [
    {
      "id": "67250",
      "sku": "10116",
      "barcode": "8720174265884",
      "ean": "8720174265884",
      "manufacturer_number": "500.600.30.002",
      "supplier": "Acme NL",
      "unit": "stuks",
      "name": "D4E LEDEREN RIEM 50MM IN OLIEBRUIN TOPNERFLEER",
      "brand": "D4E",
      "delivery_time": "1-2 days",
      "product_type": "configurable",
      "price": [23.16],
      "special_price": null,
      "order_number": 12,
      "category_id": "1745",
      "sale_units": [
        {
          "id": "10116-stuks",
          "sale_id": "10116-stuks",
          "label": "stuks",
          "value": 23.16,
          "currency": "EUR",
          "current_sales_price_excl_vat": 23.16,
          "current_sales_price_incl_vat": 28.02,
          "purchase_price": 18.0,
          "purchase_price_excl_vat": 18.0,
          "purchase_price_incl_vat": 21.78,
          "shipping_cost": 5.99,
          "shipping_cost_excl_vat": 4.95,
          "shipping_cost_incl_vat": 5.99,
          "available_stock": 1,
          "extra_free": 2.0,
          "has_tess_price": true
        }
      ],
      "additional_attributes": [
        { "code": "color",    "value": "Black"   },
        { "code": "material", "value": "Leather" },
        { "code": "weight",   "value": "0.450"   }
      ]
    }
  ]
}
```

### Product fields

| Field | Type | Description |
|---|---|---|
| `id` | string | Product entity_id |
| `sku` | string | SKU |
| `barcode` | string \| null | Barcode attribute |
| `ean` | string \| null | Alias of `barcode` |
| `manufacturer_number` | string \| null | Manufacturer/supplier product number |
| `supplier` | string \| null | Value of the configured supplier attribute (Admin → Attribute Mapping) |
| `unit` | string \| null | Raw value of the configured unit attribute. Same value is used to build `sale_units[].label`. |
| `name` | string \| null | Product name (HTML stripped) |
| `brand` | string \| null | Brand attribute value |
| `delivery_time` | string \| null | Delivery time label |
| `product_type` | string | `simple`, `configurable`, ... |
| `price` | float[] | Simple: 1 element. Configurable: distinct child prices, ascending |
| `special_price` | float \| null | Raw `special_price` attribute (date validity not enforced) |
| `order_number` | float | Total ordered qty across non-canceled orders (`qty_ordered − qty_canceled − qty_refunded`) |
| `category_id` | string \| null | Matches the requested `category_id`, otherwise the product's first category |
| `sale_units` | array | See below |
| `additional_attributes` | array | List of `{code, value}` pairs for extra attributes configured in Admin → Combipower → TESS AI → Additional Attributes. Values are string-cast (numbers / multi-select labels rendered as strings) for stable JSON marshalling. Empty array `[]` when nothing is configured or all values are blank. |

### SaleUnit fields

| Field | Type | Description |
|---|---|---|
| `id` | string | Simple: tier qty (`1`, `5`, ...). Configurable: child SKU |
| `sale_id` | string | Same as `id` |
| `label` | string | Unit label, e.g. `stuks`, `5 x stuks`, or child variant label |
| `value` | float \| null | Sales price excl VAT (legacy alias of `current_sales_price_excl_vat`) |
| `currency` | string | ISO currency code (e.g. `EUR`) |
| `current_sales_price_excl_vat` | float \| null | Sales price excluding VAT |
| `current_sales_price_incl_vat` | float \| null | Sales price including VAT (per product tax class + store) |
| `purchase_price` | float \| null | Cost (legacy alias of `purchase_price_excl_vat`) |
| `purchase_price_excl_vat` | float \| null | Product cost |
| `purchase_price_incl_vat` | float \| null | `cost × (1 + tax)`, null when cost is null |
| `shipping_cost` | float \| null | **Legacy alias** of `shipping_cost_incl_vat`. Always returns the tax-inclusive value regardless of `Stores → Tax → Calculation Settings → Shipping Prices` config. |
| `shipping_cost_excl_vat` | float \| null | Estimated shipping for the configured destination, **excluding** VAT. Computed via `TaxHelper::getShippingPrice` against the configured shipping tax class. |
| `shipping_cost_incl_vat` | float \| null | Estimated shipping for the configured destination, **including** VAT. |
| `available_stock` | float \| null | Raw physical stock qty (**not** salable qty). When `Magento_InventoryApi` is enabled, this is `SUM(inventory_source_item.quantity)` across all sources where `status=1`. Otherwise it falls back to `cataloginventory_stock_item.qty` (stock_id=1). Reservations are NOT subtracted. |
| `extra_free` | float \| null | Raw value of the `extra_free` decimal product attribute. Simple/virtual: taken from the product itself (same value on every sale unit). Configurable: taken from each child variant. `null` when unset/empty. |
| `has_tess_price` | bool | Value of the `has_tess_price` Yes/No product attribute. Simple/virtual: taken from the product itself. Configurable: taken from each child variant. Defaults to `false` when unset. |

### Examples

```bash
# Basic list
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?page=1&per_page=20"

# Filter by category + brand + in stock
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?category_id=15,22&brand_id=BOSCH&stock=in_stock&per_page=50"

# Multiple categories via array syntax (URL-encoded [])
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?category_id%5B%5D=15&category_id%5B%5D=22"

# Search by EAN / article number
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?ean=8710103&sku=ABC"

# Filter by sales-price range (excl. VAT)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?price_from=10&price_to=100"

# Filter by purchase-price (cost) range
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?purchase_price_from=5&purchase_price_to=50"

# Combine multiple filters + sort
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?supplier_id=10000&brand_id=409&price_from=5&price_to=50&sort_by=sku&sort_order=desc"

# Sort by sales price ascending (configurable products use their cheapest variant)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?sort_by=price&sort_order=asc&per_page=5"

# Multi-value filters on built-in fields (OR)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?supplier_id%5B%5D=10&supplier_id%5B%5D=11"

# Filter by an extra (additional) attribute
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?attr%5Bcolor%5D=red&attr%5Bmaterial%5D=steel"

# Multi-value on additional attribute (OR)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products?attr%5Bcolor%5D%5B%5D=red&attr%5Bcolor%5D%5B%5D=blue"
```

### Paginate all results

```bash
PER_PAGE=100
PAGE=1
while :; do
  RES=$(curl -s -H "Authorization: Bearer $TOKEN" \
    "$BASE/rest/V1/tessAi/products?per_page=$PER_PAGE&page=$PAGE")
  TOTAL=$(echo "$RES" | jq '.meta.total')
  COUNT=$(echo "$RES" | jq '.items | length')
  echo "page=$PAGE got=$COUNT total=$TOTAL"
  [ "$((PAGE * PER_PAGE))" -ge "$TOTAL" ] && break
  PAGE=$((PAGE + 1))
done
```

---

## 6. `GET /V1/tessAi/products/{sku}`

Returns a single product by SKU. Response is the **product object** directly (no `meta`/`items` wrapper).

### Example

```bash
SKU="10116"
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/products/$(printf '%s' "$SKU" | jq -sRr @uri)"
```

### Response

```json
{
  "id": "67250",
  "sku": "10116",
  "barcode": "8720174265884",
  "...": "...",
  "sale_units": [ /* ... */ ]
}
```

Field reference is identical to items in `/products` — see section 5.

---

## 7. `POST /V1/tessAi/prices`

Bulk update product prices by SKU. **Every successfully updated product gets `has_tess_price` set to `true`.**

Requires ACL `Combipower_TessAI::write`.

Prices are written in the **default (admin) scope** (store 0). On a global price-scope shop this updates the price everywhere; on website/store scope it updates the default value.

### Request

```json
{
  "items": [
    { "sku": "404634",     "price": 99.95 },
    { "sku": "10116-stuks", "price": 23.16, "special_price": 19.99 }
  ]
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `items[].sku` | string | yes | Product SKU to update |
| `items[].price` | float | yes | New base price (excl. VAT), must be ≥ 0 |
| `items[].special_price` | float | no | New special price (excl. VAT), must be ≥ 0 when present |

### Behaviour

- **Per-item processing:** a failing SKU does not abort the batch — its result row carries `success: false` and a `message`.
- Setting `price` flags the product with `has_tess_price = true`.
- `special_price` is only changed when provided; omit it to leave it untouched.
- For configurable parents, `price` has no catalog effect (price comes from children) but the flag is still set — send child SKUs to change actual prices.

### Response

Array of per-item results, in the same order as the request:

```json
[
  { "sku": "404634",      "success": true,  "message": null },
  { "sku": "10116-stuks", "success": true,  "message": null },
  { "sku": "BAD-SKU",     "success": false, "message": "The product that was requested doesn't exist. Verify the product and try again." }
]
```

| Field | Type | Description |
|---|---|---|
| `sku` | string | Echoed SKU |
| `success` | bool | `true` when the product was updated and saved |
| `message` | string \| null | Error detail when `success` is `false`, otherwise `null` |

### Example

```bash
curl -s -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "$BASE/rest/V1/tessAi/prices" \
  -d '{"items":[{"sku":"404634","price":99.95},{"sku":"10116-stuks","price":23.16,"special_price":19.99}]}'
```

---

## 8. Multi-store

Pass `store_code` in the URL to switch store context (changes category tree, attribute values, currency, tax rules):

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE/rest/nl_nl/V1/tessAi/products"
curl -H "Authorization: Bearer $TOKEN" "$BASE/rest/en_us/V1/tessAi/products"
```

---

## 9. HTTP status codes

| Status | Cause | Body |
|---|---|---|
| `200` | Success | JSON response |
| `401` | Missing/expired token | `{"message":"Consumer is not authorized..."}` |
| `403` | Token lacks `Combipower_TessAI::read` | `{"message":"The consumer isn't authorized..."}` |
| `404` | SKU not found (single endpoint) | `{"message":"The product that was requested doesn't exist..."}` |
| `500` | Server error | Generic message; check Magento `var/log/exception.log` |

---

## 10. Notes for integrators

- **Currency:** all monetary fields (`value`, `*_excl_vat`, `*_incl_vat`, `shipping_cost`) are in `sale_units[].currency`.
- **Tax:** `*_incl_vat` follows the product's tax class plus the store's tax rule. Values may differ per store.
- **Caching:** `/categories` and `/filters` change rarely — cache 10–30 minutes on the client.
- **Throughput:** for full sync, prefer `per_page=100..200` and parallelize page fetches.
- **Timeouts:** `shipping_cost` is computed via a temporary Magento quote, which can be slow. Use a client timeout of ≥ 30s.
- **No `updated_at` filter** today. If incremental sync is needed, request the field/filter to be added.
