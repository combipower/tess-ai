<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Api\ProductRepositoryInterface as CatalogProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
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

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        CatalogProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        AttributeProvider $attributeProvider,
        ProductMapper $productMapper,
        ProductListFactory $productListFactory,
        PaginationMetaFactory $paginationMetaFactory
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->attributeProvider = $attributeProvider;
        $this->productMapper = $productMapper;
        $this->productListFactory = $productListFactory;
        $this->paginationMetaFactory = $paginationMetaFactory;
    }

    public function getList(
        $category_id = null,
        $supplier_id = null,
        $brand_id = null,
        $article_number = null,
        $ean = null,
        $stock = null,
        $page = 1,
        $per_page = 50
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
        ]);
        $collection->addAttributeToSelect(
            $this->attributeProvider->getExistingProductAttributes([
                $barcodeAttributeCode,
                $manufacturerNumberAttributeCode,
                $brandAttributeCode,
                $unitAttributeCode,
            ])
        );
        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        $categoryIds = $this->normalizeCategoryIds($category_id);
        if (!empty($categoryIds)) {
            $collection->addCategoriesFilter(['in' => $categoryIds]);
        }

        if ($article_number) {
            $collection->addAttributeToFilter('sku', ['like' => '%' . $article_number . '%']);
        }

        if ($ean && $this->attributeProvider->hasProductAttribute($barcodeAttributeCode)) {
            $collection->addAttributeToFilter($barcodeAttributeCode, ['like' => '%' . $ean . '%']);
        }

        if ($supplier_id && $this->attributeProvider->hasProductAttribute($supplierAttributeCode)) {
            $collection->addAttributeToFilter($supplierAttributeCode, $supplier_id);
        }

        if ($brand_id && $this->attributeProvider->hasProductAttribute($brandAttributeCode)) {
            $collection->addAttributeToFilter($brandAttributeCode, $brand_id);
        }

        $normalizedStock = strtolower((string) $stock);
        if (in_array($normalizedStock, ['1', 'true', 'in_stock', 'in-stock'], true)) {
            $collection->addFieldToFilter('is_in_stock', 1);
        } elseif (in_array($normalizedStock, ['0', 'false', 'out_of_stock', 'out-of-stock'], true)) {
            $collection->addFieldToFilter('is_in_stock', 0);
        }

        $collection->setCurPage($page);
        $collection->setPageSize($perPage);
        $collection->addTierPriceData();

        $items = [];
        foreach ($collection as $catalogProduct) {
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

        return $this->productMapper->map($product);
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
}
