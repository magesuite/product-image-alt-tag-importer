<?php

declare(strict_types=1);

namespace MageSuite\ProductImageAltTagImporter\Model;

class MediaGallery
{
    protected array $mediaGallery = [];

    public function __construct(protected \Magento\Framework\App\ResourceConnection $resource) {}

    public function getItems(): array
    {
        if (!empty($this->mediaGallery)) {
            return $this->mediaGallery;
        }

        $this->load();
        return $this->mediaGallery;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function findByFilename(string $filename, int $storeId): array
    {
        $gallery = $this->getItems();

        $matchedRows = [];
        foreach ($gallery as $item) {
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
                return $row;
            }
        }

        throw new \Magento\Framework\Exception\NoSuchEntityException(
            __('Media gallery item with filename %1 not found', $filename)
        );
    }

    protected function load(): void
    {
        $select = $this->resource->getConnection()->select()
            ->from(['value' => $this->resource->getTableName('catalog_product_entity_media_gallery_value')])
            ->join(['main' => $this->resource->getTableName('catalog_product_entity_media_gallery')], 'main.value_id = value.value_id');
        $result = array_column($this->resource->getConnection()->fetchAll($select), null, 'value_id');
        $this->mediaGallery = $result;
    }
}
