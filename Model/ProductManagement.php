<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Api\ProductRepositoryInterface as CatalogProductRepositoryInterface;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Magento\Store\Model\StoreManagerInterface;
use Combipower\TessAI\Api\ProductManagementInterface;
use Combipower\TessAI\Model\Data\PaginationMetaFactory;
use Combipower\TessAI\Model\Data\ProductListFactory;

class ProductManagement implements ProductManagementInterface
{
    private const ALLOWED_PRODUCT_TYPES = [
        ProductType::TYPE_SIMPLE,
        ProductType::TYPE_VIRTUAL,
        'configurable',
        'downloadable',
    ];

    private const COST_ATTRIBUTE_CODE = 'cost';

    private const PRICE_INDEX_ALIAS = 'price_index';

    private const SORT_DEFAULT_DIRECTION = 'DESC';

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var CatalogProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AttributeProvider
     */
    private $attributeProvider;

    /**
     * @var ProductMapper
     */
    private $productMapper;

    /**
     * @var ProductListFactory
     */
    private $productListFactory;

    /**
     * @var PaginationMetaFactory
     */
    private $paginationMetaFactory;

    /**
     * @var PriceTableResolver
     */
    private $priceTableResolver;

    /**
     * @var DimensionFactory
     */
    private $dimensionFactory;

    /**
     * @var StockQtyResolver
     */
    private $stockQtyResolver;

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        CatalogProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        AttributeProvider $attributeProvider,
        ProductMapper $productMapper,
        ProductListFactory $productListFactory,
        PaginationMetaFactory $paginationMetaFactory,
        PriceTableResolver $priceTableResolver,
        DimensionFactory $dimensionFactory,
        StockQtyResolver $stockQtyResolver
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->attributeProvider = $attributeProvider;
        $this->productMapper = $productMapper;
        $this->productListFactory = $productListFactory;
        $this->paginationMetaFactory = $paginationMetaFactory;
        $this->priceTableResolver = $priceTableResolver;
        $this->dimensionFactory = $dimensionFactory;
        $this->stockQtyResolver = $stockQtyResolver;
    }

    public function getList(
        $category_id = null,
        $supplier_id = null,
        $brand_id = null,
        $sku = null,
        $ean = null,
        $stock = null,
        $page = 1,
        $per_page = 50,
        $price_from = null,
        $price_to = null,
        $purchase_price_from = null,
        $purchase_price_to = null,
        $sort_by = null,
        $sort_order = null,
        $attr = []
    ) {
        $page = max(1, (int) $page);
        $perPage = min(max(1, (int) $per_page), 200);
        $store = $this->storeManager->getStore();
        $barcodeAttributeCode = $this->attributeProvider->getBarcodeAttributeCode();
        $manufacturerNumberAttributeCode = $this->attributeProvider->getManufacturerNumberAttributeCode();
        $brandAttributeCode = $this->attributeProvider->getBrandAttributeCode();
        $unitAttributeCode = $this->attributeProvider->getUnitAttributeCode();
        $supplierAttributeCode = $this->attributeProvider->getSupplierAttributeCode();

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($store->getId());
        $collection->addStoreFilter($store);
        $collection->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED);
        $collection->setVisibility([
            ProductVisibility::VISIBILITY_IN_CATALOG,
            ProductVisibility::VISIBILITY_IN_SEARCH,
            ProductVisibility::VISIBILITY_BOTH,
        ]);
        $collection->addAttributeToFilter('type_id', ['in' => self::ALLOWED_PRODUCT_TYPES]);
        $collection->addAttributeToSelect([
            'name',
            'price',
            'cost',
            'tax_class_id',
            'type_id',
            'extra_free',
            'has_tess_price',
            'tess_brand',
            'tess_delivery_time',
        ]);
        $collection->addAttributeToSelect(
            $this->attributeProvider->getExistingProductAttributes(array_merge(
                [
                    $barcodeAttributeCode,
                    $manufacturerNumberAttributeCode,
                    $brandAttributeCode,
                    $supplierAttributeCode,
                    $unitAttributeCode,
                ],
                $this->attributeProvider->getAdditionalAttributeCodes()
            ))
        );
        $collection->addAttributeToSelect($this->attributeProvider->getShippingQuoteAttributeCodes());
        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        $categoryIds = $this->normalizeCategoryIds($category_id);
        if (!empty($categoryIds)) {
            $collection->addCategoriesFilter(['in' => $categoryIds]);
        }

        $skuValues = $this->normalizeMultiValue($sku);
        if (!empty($skuValues)) {
            $collection->addAttributeToFilter('sku', $this->buildLikeFilter($skuValues));
        }

        $eanValues = $this->normalizeMultiValue($ean);
        if (!empty($eanValues) && $this->attributeProvider->hasProductAttribute($barcodeAttributeCode)) {
            $collection->addAttributeToFilter($barcodeAttributeCode, $this->buildLikeFilter($eanValues));
        }

        $supplierValues = $this->normalizeMultiValue($supplier_id);
        if (!empty($supplierValues) && $this->attributeProvider->hasProductAttribute($supplierAttributeCode)) {
            $collection->addAttributeToFilter($supplierAttributeCode, $this->buildExactFilter($supplierValues));
        }

        $brandValues = $this->normalizeMultiValue($brand_id);
        if (!empty($brandValues) && $this->attributeProvider->hasProductAttribute($brandAttributeCode)) {
            $collection->addAttributeToFilter($brandAttributeCode, $this->buildExactFilter($brandValues));
        }

        $this->applyAdditionalAttributeFilters($collection, $attr);

        $normalizedStock = strtolower((string) $stock);
        if (in_array($normalizedStock, ['1', 'true', 'in_stock', 'in-stock'], true)) {
            $this->stockQtyResolver->applyInStockFilter($collection, true);
        } elseif (in_array($normalizedStock, ['0', 'false', 'out_of_stock', 'out-of-stock'], true)) {
            $this->stockQtyResolver->applyInStockFilter($collection, false);
        }

        $this->joinPriceIndex($collection, (int) $store->getWebsiteId());

        $priceFrom = $this->normalizeRangeBound($price_from);
        $priceTo = $this->normalizeRangeBound($price_to);
        if ($priceFrom !== null) {
            $collection->getSelect()->where(self::PRICE_INDEX_ALIAS . '.min_price >= ?', $priceFrom);
        }
        if ($priceTo !== null) {
            $collection->getSelect()->where(self::PRICE_INDEX_ALIAS . '.min_price <= ?', $priceTo);
        }

        $purchaseFrom = $this->normalizeRangeBound($purchase_price_from);
        $purchaseTo = $this->normalizeRangeBound($purchase_price_to);
        if ($purchaseFrom !== null && $this->attributeProvider->hasProductAttribute(self::COST_ATTRIBUTE_CODE)) {
            $collection->addAttributeToFilter(self::COST_ATTRIBUTE_CODE, ['gteq' => $purchaseFrom]);
        }
        if ($purchaseTo !== null && $this->attributeProvider->hasProductAttribute(self::COST_ATTRIBUTE_CODE)) {
            $collection->addAttributeToFilter(self::COST_ATTRIBUTE_CODE, ['lteq' => $purchaseTo]);
        }

        $collection->groupByAttribute('entity_id');

        $this->applySort($collection, $sort_by, $sort_order);
        $collection->getSelect()->order('e.entity_id ASC');

        $collection->setCurPage($page);
        $collection->setPageSize($perPage);
        $collection->addTierPriceData();
        // Loads all category ids in one query on load; without this the mapper
        // lazy-loads them one query per product.
        $collection->addCategoryIds();

        $catalogProducts = $collection->getItems();
        $this->productMapper->preload($catalogProducts);

        $items = [];
        foreach ($catalogProducts as $catalogProduct) {
            $items[] = $this->productMapper->map($catalogProduct, $categoryIds);
        }

        $meta = $this->paginationMetaFactory->create()
            ->setTotal((int) $collection->getSize())
            ->setPage($page)
            ->setPerPage($perPage);

        return $this->productListFactory->create()
            ->setMeta($meta)
            ->setItems($items);
    }

    public function getBySku($sku)
    {
        $store = $this->storeManager->getStore();
        $product = $this->productRepository->get($sku, false, $store->getId(), true);

        // The list endpoint silently filters by status/visibility/type; mirror
        // that contract here so a disabled or hidden SKU surfaces as a 404
        // instead of leaking data that the list would not have shown.
        if ((int) $product->getStatus() !== ProductStatus::STATUS_ENABLED) {
            throw NoSuchEntityException::singleField('sku', $sku);
        }

        $allowedVisibility = [
            ProductVisibility::VISIBILITY_IN_CATALOG,
            ProductVisibility::VISIBILITY_IN_SEARCH,
            ProductVisibility::VISIBILITY_BOTH,
        ];
        if (!in_array((int) $product->getVisibility(), $allowedVisibility, true)) {
            throw NoSuchEntityException::singleField('sku', $sku);
        }

        if (!in_array((string) $product->getTypeId(), self::ALLOWED_PRODUCT_TYPES, true)) {
            throw NoSuchEntityException::singleField('sku', $sku);
        }

        return $this->productMapper->map($product);
    }

    /**
     * Join the price index table so price-range filter and sort can target
     * `min_price`. Uses PriceTableResolver to handle non-default dimension modes
     * (e.g. when indexer/catalog_product_price/dimensions_mode = website the
     * physical table is `catalog_product_index_price_ws<id>`).
     *
     * @param ProductCollection $collection
     * @param int $websiteId
     * @return void
     */
    private function joinPriceIndex(ProductCollection $collection, $websiteId)
    {
        $dimensions = [
            $this->dimensionFactory->create(
                WebsiteDimensionProvider::DIMENSION_NAME,
                (string) $websiteId
            ),
        ];
        $priceIndexTable = $this->priceTableResolver->resolve('catalog_product_index_price', $dimensions);

        $collection->getSelect()->joinLeft(
            [self::PRICE_INDEX_ALIAS => $priceIndexTable],
            sprintf(
                '%s.entity_id = e.entity_id AND %s.customer_group_id = 0 AND %s.website_id = %d',
                self::PRICE_INDEX_ALIAS,
                self::PRICE_INDEX_ALIAS,
                self::PRICE_INDEX_ALIAS,
                $websiteId
            ),
            []
        );
    }

    /**
     * Cast a raw query-string value to a non-negative float, or null when it
     * cannot be used as a filter bound.
     *
     * @param mixed $value
     * @return float|null
     */
    private function normalizeRangeBound($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $float = (float) $value;
        if ($float < 0) {
            return null;
        }

        return $float;
    }

    /**
     * Apply whitelisted sort_by/sort_order to the collection.
     *
     * `order_number` is intentionally omitted in this phase — it is a computed
     * field aggregated from sales_order_item and has no sortable column.
     *
     * @param ProductCollection $collection
     * @param mixed $sortBy
     * @param mixed $sortOrder
     * @return void
     */
    private function applySort(ProductCollection $collection, $sortBy, $sortOrder)
    {
        if ($sortBy === null || $sortBy === '') {
            return;
        }

        $direction = strtoupper((string) $sortOrder);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = self::SORT_DEFAULT_DIRECTION;
        }

        switch ((string) $sortBy) {
            case 'sku':
                $collection->setOrder('sku', $direction);
                break;
            case 'name':
                $collection->setOrder('name', $direction);
                break;
            case 'brand':
                $brandAttributeCode = $this->attributeProvider->getBrandAttributeCode();
                if ($brandAttributeCode && $this->attributeProvider->hasProductAttribute($brandAttributeCode)) {
                    $collection->setOrder($brandAttributeCode, $direction);
                }
                break;
            case 'price':
                $collection->getSelect()->order(self::PRICE_INDEX_ALIAS . '.min_price ' . $direction);
                break;
            case 'purchase_price':
                if ($this->attributeProvider->hasProductAttribute(self::COST_ATTRIBUTE_CODE)) {
                    $collection->setOrder(self::COST_ATTRIBUTE_CODE, $direction);
                }
                break;
            case 'available_stock':
                $collection->getSelect()->order('qty ' . $direction);
                break;
        }
    }

    /**
     * @param mixed $categoryId
     * @return int[]
     */
    private function normalizeCategoryIds($categoryId)
    {
        $values = [];
        $this->collectCategoryIdValues($categoryId, $values);

        $ids = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $id = (int) $part;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $value
     * @param array $values
     * @return void
     */
    private function collectCategoryIdValues($value, array &$values)
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectCategoryIdValues($item, $values);
            }
            return;
        }

        if (is_scalar($value)) {
            $values[] = $value;
        }
    }

    /**
     * Normalize a request value into a flat list of non-empty strings.
     * Accepts scalar, comma-separated string, or array-of-arrays.
     *
     * @param mixed $value
     * @return string[]
     */
    private function normalizeMultiValue($value)
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $flat = [];
        array_walk_recursive($value, static function ($item) use (&$flat) {
            if ($item === null || is_bool($item) || is_array($item) || is_object($item)) {
                return;
            }

            $string = trim((string) $item);
            if ($string !== '') {
                $flat[] = $string;
            }
        });

        return array_values(array_unique($flat));
    }

    /**
     * Build a Magento collection filter condition for an exact-match attribute.
     * Returns a scalar for a single value and an `in` condition for multi.
     *
     * @param string[] $values
     * @return string|array
     */
    private function buildExactFilter(array $values)
    {
        if (count($values) === 1) {
            return $values[0];
        }

        return ['in' => $values];
    }

    /**
     * Build a Magento collection filter condition for a LIKE attribute.
     * Single value → `['like' => '%v%']`; multi → array of `like` conditions
     * which Magento OR-joins.
     *
     * @param string[] $values
     * @return array
     */
    private function buildLikeFilter(array $values)
    {
        if (count($values) === 1) {
            return ['like' => '%' . $values[0] . '%'];
        }

        $conditions = [];
        foreach ($values as $value) {
            $conditions[] = ['like' => '%' . $value . '%'];
        }

        return $conditions;
    }

    /**
     * Apply `attr[code]=value` (or `attr[code][]=v1&attr[code][]=v2`) filters.
     * Only attributes declared in admin → Additional Attributes are honored.
     * Unknown codes and codes hardcoded in this controller are silently skipped.
     *
     * @param ProductCollection $collection
     * @param mixed $attr
     * @return void
     */
    private function applyAdditionalAttributeFilters(ProductCollection $collection, $attr)
    {
        if (!is_array($attr) || empty($attr)) {
            return;
        }

        $allowedCodes = array_flip($this->attributeProvider->getAdditionalAttributeCodes());
        if (empty($allowedCodes)) {
            return;
        }

        foreach ($attr as $code => $rawValue) {
            $code = is_string($code) ? trim($code) : '';
            if ($code === '' || !isset($allowedCodes[$code])) {
                continue;
            }

            $values = $this->normalizeMultiValue($rawValue);
            if (empty($values)) {
                continue;
            }

            $operator = $this->attributeProvider->resolveAttributeOperator($code);
            $condition = $operator === 'like'
                ? $this->buildLikeFilter($values)
                : $this->buildExactFilter($values);

            $collection->addAttributeToFilter($code, $condition);
        }
    }
}
