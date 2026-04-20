# Tess_PricingTool

Base Magento 2 module for the TESS Pricing Tool REST integration.

## Exposed endpoints

- `GET /rest/<store_code>/V1/tess/pricing/categories`
- `GET /rest/<store_code>/V1/tess/pricing/filters`
- `GET /rest/<store_code>/V1/tess/pricing/products`
- `GET /rest/<store_code>/V1/tess/pricing/products/{sku}`

## Authentication

The routes are protected by the custom ACL resources:

- `Tess_PricingTool::read`

Use an integration token or admin token with that permission.

## Activation

After deploying the code, enable the module and register it with Magento:

```bash
php bin/magento module:enable Tess_PricingTool
php bin/magento setup:upgrade
php bin/magento cache:flush
```

If the store runs in production mode, also regenerate DI:

```bash
php bin/magento setup:di:compile
```
