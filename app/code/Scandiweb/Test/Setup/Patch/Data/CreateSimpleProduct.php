<?php
/**
 * @category    Scandiweb
 * @package     Scandiweb_Test
 * @author      Mariam Zakareishvili <info@scandiweb.com>
 * @copyright   Copyright (c) 2022 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Create Migration product class
 */
class CreateSimpleProduct implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected ModuleDataSetupInterface $setup;

    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productInterfaceFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var State
     */
    protected State $appState;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;

    /**
     * @var EavSetup
     */
    protected EavSetup $eavSetup;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $categoryCollectionFactory;

    /**
     * @var array
     */
    protected array $sourceItems = [];

    /**
     * Migration patch constructor.
     *
     * @param ModuleDataSetupInterface $setup
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     * @param State $appState
     * @param StoreManagerInterface $storeManager
     * @param EavSetup $eavSetup
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param CategoryLinkManagementInterface $categoryLink
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink,
        CollectionFactory $categoryCollectionFactory
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Add new product
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute(): void
    {
        $product = $this->productInterfaceFactory->create();

        if ($product->getIdBySku('test-product')) {
            return;
        }

        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setWebsiteIds($websiteIDs)
            ->setAttributeSetId($attributeSetId)
            ->setName('Test Product')
            ->setUrlKey('testproduct')
            ->setSku('test-product')
            ->setPrice(29.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);
        $product = $this->productRepository->save($product);

        $sourceItemFactory = $this->sourceItemFactory->create();
        $sourceItemFactory->setSourceCode('default');
        $sourceItemFactory->setQuantity(100);
        $sourceItemFactory->setSku($product->getSku());
        $sourceItemFactory->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItemFactory;

        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        $categoryTitles = ['Men', 'Women'];
        $categoryIds = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['in' => $categoryTitles])
            ->getAllIds();
        $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIds);
    }

    /**
     * {@inheritDoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}
