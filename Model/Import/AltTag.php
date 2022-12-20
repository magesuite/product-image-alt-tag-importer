<?php
declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Model\Import;

class AltTag extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    public const ENTITY_CODE = 'product_image_alt';
    public const FILENAME_COLUMN = 'filename';

    protected $needColumnCheck = true;
    protected $logInHistory = true;
    protected $validColumnNames = [
        'filename',
        'label'
    ];

    protected \Magento\Framework\DB\Adapter\AdapterInterface $connection;
    protected \Magento\Framework\App\ResourceConnection $resource;
    protected int $batchSize;

    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface $errorAggregator,
        int $batchSize = 100
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;
        $this->initMessageTemplates();
        $this->batchSize = $batchSize;
    }

    public function getEntityTypeCode()
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
    }

    public function validateRow(array $rowData, $rowNum): bool
    {
        $filename = $rowData['filename'] ?? '';
        $label = $rowData['label'] ?? '';

        if (!$filename) {
            $this->addRowError('FilenameIsRequired', $rowNum);
        }

        if (!$label) {
            $this->addRowError('LabelIsRequired', $rowNum);
        }

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    protected function _importData(): bool
    {
        switch ($this->getBehavior()) {
            case \Magento\ImportExport\Model\Import::BEHAVIOR_REPLACE:
            case \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND:
                $this->saveAndReplaceEntity();
                break;
        }

        return true;
    }

    protected function saveAndReplaceEntity(): void
    {
        $behavior = $this->getBehavior();
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }

                $rowId = $row[static::FILENAME_COLUMN];
                $rows[] = $rowId;
                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    $columnValues[$columnKey] = $row[$columnKey];
                }

                $entityList[$rowId][] = $columnValues;
                $this->countItemsCreated += (int) !isset($row[static::FILENAME_COLUMN]);
                $this->countItemsUpdated += (int) isset($row[static::FILENAME_COLUMN]);
            }

            $this->saveEntityFinish($entityList);
        }
    }

    protected function saveEntityFinish(array $entityData): bool
    {
        $rows = [];
        $conditions = [];

        foreach ($entityData as $entityRows) {
            foreach ($entityRows as $entityRow) {
                $select = $this->connection->select()
                    ->from(
                        $this->connection->getTableName('catalog_product_entity_media_gallery'),
                        ['value_id']
                    )->where('value like ?', '%' . $entityRow['filename']);
                $valueIds = $this->connection->fetchCol($select);

                if (empty($valueIds)) {
                    continue;
                }

                foreach ($valueIds as $valueId) {
                    $case = $this->connection->quoteInto('?', (int)$valueId);
                    $result = $this->connection->quoteInto('?', $entityRow['label']);
                    $conditions[$case] = $result;
                }

                if (count($conditions) >= $this->batchSize) {
                    $this->saveData($conditions);
                    $conditions = [];
                }
            }
        }

        if (!empty($conditions)) {
            $this->saveData($conditions);
        }

        return !empty($entityData);
    }

    protected function saveData(array $conditions): void
    {
        $value = $this->connection->getCaseSql('value_id', $conditions);
        $where = ['value_id IN (?)' => array_keys($conditions)];
        $this->connection->update(
            $this->connection->getTableName('catalog_product_entity_media_gallery_value'),
            ['label' => $value],
            $where
        );
    }

    protected function getAvailableColumns(): array
    {
        return $this->validColumnNames;
    }
}
