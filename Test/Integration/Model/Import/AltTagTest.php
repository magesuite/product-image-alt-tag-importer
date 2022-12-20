<?php
declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Test\Integration\Model\Import;

class AltTagTest extends \PHPUnit\Framework\TestCase
{
    protected ?\Magento\Framework\App\ObjectManager $objectManager;

    protected ?\Magento\Catalog\Api\ProductRepositoryInterface $productRepository;

    protected ?\Magento\Framework\Filesystem $filesystem;

    protected ?\MageSuite\ProductImageAltTagImporter\Model\Import\AltTag $importModel;

    protected function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->productRepository = $this->objectManager->get(
            \Magento\Catalog\Api\ProductRepositoryInterface::class
        );
        $this->filesystem = $this->objectManager->get(
            \Magento\Framework\Filesystem::class
        );
        $this->importModel = $this->objectManager->get(
            \MageSuite\ProductImageAltTagImporter\Model\Import\AltTag::class
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/Catalog/_files/product_with_image.php
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
            \Magento\ImportExport\Model\Import\Source\Csv::class,
            [
                'file' => $pathToFile,
                'directory' => $directory
            ]
        );
        $errors = $this->importModel->setSource(
            $source
        )->setParameters(
            [
                'behavior' => \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND,
                'entity' => \MageSuite\ProductImageAltTagImporter\Model\Import\AltTag::ENTITY_CODE,
                \Magento\ImportExport\Model\Import::FIELD_FIELD_MULTIPLE_VALUE_SEPARATOR => ','
            ]
        )->validateData();
        $this->assertTrue($errors->getErrorsCount() == 0);
        $this->importModel->importData();

        $product = $this->productRepository->get('simple', false, null, true);
        $galleryImages = $product->getMediaGalleryImages();
        $this->assertEquals(1, $galleryImages->count());
        $this->assertEquals('dummy label 1', $galleryImages->getFirstItem()->getLabel());
    }
}
