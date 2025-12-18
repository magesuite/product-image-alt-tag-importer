<?php

declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Model\Import;

class AltTag extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    public const ENTITY_CODE = 'product_image_alt';
    public const FILENAME_COLUMN = 'filename';

    /** @var bool $needColumnCheck */
    protected $needColumnCheck = true;

    /** @var bool $logInHistory */
    protected $logInHistory = true;

    /** @var array $validColumnNames */
    protected $validColumnNames = [
        'filename',
        'label',
        'store_view_code',
    ];

    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;
    protected \Magento\Framework\App\ResourceConnection $resource;
    protected \MageSuite\ProductImageAltTagImporter\Model\MediaGallery $mediaGallery;
    protected \Magento\Store\Api\StoreRepositoryInterface $storeRepository;

    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator,
        \MageSuite\ProductImageAltTagImporter\Model\MediaGallery $mediaGallery,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->errorAggregator = $errorAggregator;
        $this->mediaGallery = $mediaGallery;
        $this->storeRepository = $storeRepository;
        $this->initMessageTemplates();
    }

    public function getEntityTypeCode(): string
    {
        return static::ENTITY_CODE;
    }

    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    protected function initMessageTemplates(): void
    {
        $this->addMessageTemplate(
            'FilenameIsRequired',
            __('The filename cannot be empty.')
        );
        $this->addMessageTemplate(
            'LabelIsRequired',
            __('The label cannot be empty.')
        );
        $this->addMessageTemplate(
            'StoreViewCodeIsRequired',
            __('The store view code cannot be empty.')
        );
        $this->addMessageTemplate(
            'ImageNotFound',
            __('Image with the specified filename does not exist in the media gallery.')
        );
    }

    public function validateRow(array $rowData, $rowNum): bool // phpcs:ignore
    {
        $filename = $rowData['filename'] ?? '';
        $label = $rowData['label'] ?? '';
        $storeViewCode = $rowData['store_view_code'] ?? '';

        if (!$filename) {
            $this->addRowError('FilenameIsRequired', $rowNum);
        }

        if (!$label) {
            $this->addRowError('LabelIsRequired', $rowNum);
        }

        if (!$storeViewCode) {
            $this->addRowError('StoreViewCodeIsRequired', $rowNum);
        }

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    protected function _importData(): bool
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {// phpcs:ignore
            $entityList = [];
            $this->mediaGallery->load(array_unique(array_column($bunch, 'filename')));

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }

                try {
                    $entityList[] = $this->prepareRowForDb($row);
                } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                    $this->addRowError('ImageNotFound', $rowNum);
                }
            }

            if (empty($entityList)) {
                continue;
            }

            $this->save($entityList);
        }

        return !$this->getErrorAggregator()->hasToBeTerminated();
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function prepareRowForDb(array $rowData): array
    {
        $storeId = $this->storeRepository->get($rowData['store_view_code'])->getId();
        $mediaGalleryItem = $this->mediaGallery->findByFilename($rowData['filename'], (int)$storeId);

        return [
            'label' => $rowData['label'],
            'value_id' => $mediaGalleryItem['value_id'],
            'store_id' => $mediaGalleryItem['store_id'],
            'entity_id' => $mediaGalleryItem['entity_id'],
            'position' => $mediaGalleryItem['position'],
            'disabled' => $mediaGalleryItem['disabled'],
            'record_id' => $mediaGalleryItem['record_id'],
        ];
    }

    protected function save(array $rows): void
    {
        $tableName = $this->resource->getTableName('catalog_product_entity_media_gallery_value');
        $this->countItemsUpdated += $this->resource->getConnection()->insertOnDuplicate($tableName, $rows, ['label']);
    }
}
