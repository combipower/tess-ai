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
  "article_numbers": [ { "id": "SKU-001",  "name": "SKU-001" } ]
}
```

| Group | Source | Notes |
|---|---|---|
| `suppliers` | Options of the configured supplier attribute | `id` = option_id (or text value if attribute is text) |
| `brands` | Options of the configured brand attribute | Same as above |
| `article_numbers` | All SKUs in the catalog | `id` = `name` = SKU |

### Example

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/rest/V1/tessAi/filters"
```

---

## 5. `GET /V1/tessAi/products`

List products with optional filters and pagination.

### Query parameters

| Param | Type | Default | Description |
|---|---|---|---|
| `category_id` | int / CSV / array | — | One ID, comma-list `1,2,3`, or repeated `category_id[]=1&category_id[]=2`. Filters via `category_product` table |
| `supplier_id` | string | — | Exact match on the supplier attribute value |
| `brand_id` | string | — | Exact match on the brand attribute value |
| `article_number` | string | — | Partial match: `LIKE %value%` on SKU |
| `ean` | string | — | Partial match: `LIKE %value%` on barcode attribute |
| `stock` | string | — | `1` / `true` / `in_stock` / `in-stock` → in-stock only. `0` / `false` / `out_of_stock` / `out-of-stock` → out-of-stock only. Empty = no filter |
| `page` | int | `1` | Min 1 |
| `per_page` | int | `50` | Min 1, max 200 (clamped) |

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
      "article_number": "10116",
      "barcode": "8720174265884",
      "ean": "8720174265884",
      "manufacturer_number": "500.600.30.002",
      "description": "D4E LEDEREN RIEM 50MM IN OLIEBRUIN TOPNERFLEER",
      "brand_dge": "D4E",
      "article_group": null,
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
          "shipping_cost": 4.95,
          "available_stock": 1
        }
      ]
    }
  ]
}
```

### Product fields

| Field | Type | Description |
|---|---|---|
| `id` | string | Product entity_id |
| `article_number` | string | SKU |
| `barcode` | string \| null | Barcode attribute |
| `ean` | string \| null | Alias of `barcode` |
| `manufacturer_number` | string \| null | Manufacturer/supplier product number |
| `description` | string \| null | Product name (HTML stripped) |
| `brand_dge` | string \| null | Brand attribute value |
| `article_group` | null | Reserved (always null for now) |
| `delivery_time` | string \| null | Delivery time label |
| `product_type` | string | `simple`, `configurable`, ... |
| `price` | float[] | Simple: 1 element. Configurable: distinct child prices, ascending |
| `special_price` | float \| null | Raw `special_price` attribute (date validity not enforced) |
| `order_number` | float | Total ordered qty across non-canceled orders (`qty_ordered − qty_canceled − qty_refunded`) |
| `category_id` | string \| null | Matches the requested `category_id`, otherwise the product's first category |
| `sale_units` | array | See below |

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
| `shipping_cost` | float \| null | Estimated shipping for the configured destination |
| `available_stock` | float \| null | Stock qty |

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
  "$BASE/rest/V1/tessAi/products?ean=8710103&article_number=ABC"
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
  "article_number": "10116",
  "barcode": "8720174265884",
  "...": "...",
  "sale_units": [ /* ... */ ]
}
```

Field reference is identical to items in `/products` — see section 5.

---

## 7. Multi-store

Pass `store_code` in the URL to switch store context (changes category tree, attribute values, currency, tax rules):

```bash
curl -H "Authorization: Bearer $TOKEN" "$BASE/rest/nl_nl/V1/tessAi/products"
curl -H "Authorization: Bearer $TOKEN" "$BASE/rest/en_us/V1/tessAi/products"
```

---

## 8. HTTP status codes

| Status | Cause | Body |
|---|---|---|
| `200` | Success | JSON response |
| `401` | Missing/expired token | `{"message":"Consumer is not authorized..."}` |
| `403` | Token lacks `Combipower_TessAI::read` | `{"message":"The consumer isn't authorized..."}` |
| `404` | SKU not found (single endpoint) | `{"message":"The product that was requested doesn't exist..."}` |
| `500` | Server error | Generic message; check Magento `var/log/exception.log` |

---

## 9. Notes for integrators

- **Currency:** all monetary fields (`value`, `*_excl_vat`, `*_incl_vat`, `shipping_cost`) are in `sale_units[].currency`.
- **Tax:** `*_incl_vat` follows the product's tax class plus the store's tax rule. Values may differ per store.
- **Caching:** `/categories` and `/filters` change rarely — cache 10–30 minutes on the client.
- **Throughput:** for full sync, prefer `per_page=100..200` and parallelize page fetches.
- **Timeouts:** `shipping_cost` is computed via a temporary Magento quote, which can be slow. Use a client timeout of ≥ 30s.
- **No `updated_at` filter** today. If incremental sync is needed, request the field/filter to be added.
