<?php
namespace Combipower\TessAI\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Combipower\TessAI\Api\CategoryManagementInterface;
use Combipower\TessAI\Model\Data\CategoryFactory;
use Combipower\TessAI\Model\Data\CategoryListFactory;

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

        $byId = [];
        $childIdsByParent = [];
        $rootIds = [];

        foreach ($collection as $category) {
            $id = (string) $category->getId();
            $rawParentId = (int) $category->getParentId();
            $parentId = $rawParentId === $rootCategoryId ? null : (string) $rawParentId;

            $byId[$id] = $this->categoryFactory->create()
                ->setId($id)
                ->setName((string) $category->getName())
                ->setParentId($parentId)
                ->setDepth(max((int) $category->getLevel() - $rootLevel - 1, 0))
                ->setChildren([]);

            if ($parentId === null) {
                $rootIds[] = $id;
            } else {
                $childIdsByParent[$parentId][] = $id;
            }
        }

        foreach ($childIdsByParent as $parentId => $childIds) {
            if (!isset($byId[$parentId])) {
                continue;
            }

            $childObjects = [];
            foreach ($childIds as $childId) {
                $childObjects[] = $byId[$childId];
            }
            $byId[$parentId]->setChildren($childObjects);
        }

        $items = [];
        foreach ($rootIds as $rootId) {
            $items[] = $byId[$rootId];
        }

        return $this->categoryListFactory->create()->setItems($items);
    }
}
