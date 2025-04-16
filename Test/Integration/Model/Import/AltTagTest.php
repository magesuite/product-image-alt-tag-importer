<?php

/**
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection ObjectManagerInspection
 */

declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Test\Integration\Model\Import;

// phpcs:disable
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\Store\Test\Fixture\Group as StoreGroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
// phpcs:enable

class AltTagTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\App\ObjectManager $objectManager = null;
    protected ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository = null;
    protected ?\Magento\Framework\Filesystem $filesystem = null;
    protected ?\MageSuite\ProductImageAltTagImporter\Model\Import\AltTag $importModel = null;
    protected ?\Magento\Store\Model\StoreRepository $storeRepository = null;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->filesystem = $this->objectManager->get(\Magento\Framework\Filesystem::class);
        $this->importModel = $this->objectManager->get(\MageSuite\ProductImageAltTagImporter\Model\Import\AltTag::class);
        $this->storeRepository = $this->objectManager->get(\Magento\Store\Model\StoreRepository::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/Catalog/_files/product_with_image.php
     * @magentoDataFixture Magento/Store/_files/second_store.php
     */
    public function testItImportProductImageLabel(): void
    {
        $product = $this->productRepository->get('simple', false, null, true);
        $galleryImages = $product->getMediaGalleryImages();
        $this->assertEquals(1, $galleryImages->count());
        $this->assertEquals('Image Alt Text', $galleryImages->getFirstItem()->getLabel());

        $pathToFile = __DIR__ . '/../../_files/import.csv';
        $directory = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::ROOT);
        $source = $this->objectManager->create(
            \Magento\ImportExport\Model\Import\Source\Csv::class, ['file' => $pathToFile, 'directory' => $directory]
        );
        $errors = $this->importModel->setSource(
            $source
        )->setParameters(
            [
                'behavior' => \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE,
                'entity' => \MageSuite\ProductImageAltTagImporter\Model\Import\AltTag::ENTITY_CODE,
                \Magento\ImportExport\Model\Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => ','
            ]
        )->validateData();
        $this->assertTrue($errors->getErrorsCount() == 0);
        $this->importModel->importData();

        $product = $this->productRepository->get('simple', false, null, true);
        $galleryImages = $product->getMediaGalleryImages();
        $this->assertEquals(1, $galleryImages->count());
        $this->assertEquals('test-label-in-german', $galleryImages->getFirstItem()->getLabel());

        $store = $this->storeRepository->get('fixture_second_store');
        $product = $this->productRepository->get('simple', false, $store->getId(), true);
        $galleryImages = $product->getMediaGalleryImages();
        $this->assertEquals(1, $galleryImages->count());
        $this->assertEquals('test-label-in-english', $galleryImages->getFirstItem()->getLabel());
    }
}
