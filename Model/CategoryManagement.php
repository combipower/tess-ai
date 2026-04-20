<?php
namespace Tess\PricingTool\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tess\PricingTool\Api\CategoryManagementInterface;
use Tess\PricingTool\Model\Data\CategoryFactory;
use Tess\PricingTool\Model\Data\CategoryListFactory;

class CategoryManagement implements CategoryManagementInterface
{
    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CategoryListFactory
     */
    private $categoryListFactory;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        CategoryListFactory $categoryListFactory,
        CategoryFactory $categoryFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->categoryListFactory = $categoryListFactory;
        $this->categoryFactory = $categoryFactory;
    }

    public function getList()
    {
        $store = $this->storeManager->getStore();
        $rootCategoryId = (int) $store->getRootCategoryId();
        $rootCategory = $this->categoryRepository->get($rootCategoryId, $store->getId());
        $rootLevel = (int) $rootCategory->getLevel();

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($store->getId());
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addFieldToFilter('path', ['like' => $rootCategory->getPath() . '/%']);
        $collection->addAttributeToSort('path', 'ASC');

        $items = [];
        foreach ($collection as $category) {
            $parentId = (int) $category->getParentId() === $rootCategoryId
                ? null
                : (string) $category->getParentId();

            $items[] = $this->categoryFactory->create()
                ->setId((string) $category->getId())
                ->setName((string) $category->getName())
                ->setParentId($parentId)
                ->setDepth(max((int) $category->getLevel() - $rootLevel - 1, 0));
        }

        return $this->categoryListFactory->create()->setItems($items);
    }
}
