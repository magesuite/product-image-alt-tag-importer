<?php

declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Model;

class MediaGallery
{
    protected array $items = [];

    public function __construct(protected \Magento\Framework\App\ResourceConnection $resource) {}

    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function findByFilename(string $filename, int $storeId): array
    {
        $matchedRows = [];
        foreach ($this->items as $item) {
            if (str_ends_with($item['value'], $filename)) {
                $matchedRows[] = $item;
            }
        }

        foreach ($matchedRows as $row) {
            if ($row['store_id'] == $storeId) {
                return $row;
            }
        }

        foreach ($matchedRows as $row) {
            if ($row['store_id'] == 0) {
                $row['store_id'] = $storeId;
                $row['record_id'] = null;
                return $row;
            }
        }

        throw new \Magento\Framework\Exception\NoSuchEntityException(
            __('Media gallery item with filename %1 not found', $filename)
        );
    }

    public function load(array $filenames): void
    {
        $select = $this->resource->getConnection()->select()
            ->from(['cpemgv' => $this->resource->getTableName('catalog_product_entity_media_gallery_value')])
            ->join(['cpemg' => $this->resource->getTableName('catalog_product_entity_media_gallery')], 'cpemg.value_id = cpemgv.value_id')
            ->where('cpemg.value IN (?)', $filenames);
        $this->items = $this->resource->getConnection()->fetchAll($select);
    }
}
