<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Api\ProductRepositoryInterface as CatalogProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tess\PricingTool\Api\ProductManagementInterface;
use Tess\PricingTool\Model\Data\PaginationMetaFactory;
use Tess\PricingTool\Model\Data\ProductListFactory;

class ProductManagement implements ProductManagementInterface
{
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

        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($store->getId());
        $collection->addStoreFilter($store);
        $collection->addAttributeToSelect([
            'name',
            'description',
            'short_description',
            'price',
            'cost',
            'tax_class_id',
            'type_id',
        ]);
        $collection->addAttributeToSelect(
            $this->attributeProvider->getExistingProductAttributes([
                AttributeProvider::EAN_ATTRIBUTE,
                AttributeProvider::MANUFACTURER_NUMBER_ATTRIBUTE,
                AttributeProvider::BRAND_ATTRIBUTE,
                AttributeProvider::DELIVERY_TIME_ATTRIBUTE,
                AttributeProvider::EXTRA_AMOUNT_ATTRIBUTE,
                AttributeProvider::SHIPPING_COST_ATTRIBUTE,
                AttributeProvider::UNIT_ATTRIBUTE,
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

        if ($category_id) {
            $collection->addCategoriesFilter(['in' => [(int) $category_id]]);
        }

        if ($article_number) {
            $collection->addAttributeToFilter('sku', ['like' => '%' . $article_number . '%']);
        }

        if ($ean && $this->attributeProvider->hasProductAttribute(AttributeProvider::EAN_ATTRIBUTE)) {
            $collection->addAttributeToFilter(AttributeProvider::EAN_ATTRIBUTE, ['like' => '%' . $ean . '%']);
        }

        if ($supplier_id && $this->attributeProvider->hasProductAttribute(AttributeProvider::SUPPLIER_ATTRIBUTE)) {
            $collection->addAttributeToFilter(AttributeProvider::SUPPLIER_ATTRIBUTE, $supplier_id);
        }

        if ($brand_id && $this->attributeProvider->hasProductAttribute(AttributeProvider::BRAND_ATTRIBUTE)) {
            $collection->addAttributeToFilter(AttributeProvider::BRAND_ATTRIBUTE, $brand_id);
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
            $items[] = $this->productMapper->map($catalogProduct, $category_id);
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
}
