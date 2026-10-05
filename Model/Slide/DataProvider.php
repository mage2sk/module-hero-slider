<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model\Slide;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    protected $loadedData;

    private \Magento\Framework\Filesystem\Directory\ReadInterface $mediaDirectory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->mediaDirectory = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $items = $this->collection->getItems();
        $this->loadedData = [];
        foreach ($items as $slide) {
            $this->loadedData[$slide->getId()] = $this->prepareSlideData($slide);
        }

        $persisted = $this->dataPersistor->get('panth_heroslider_slide');
        if (!empty($persisted)) {
            $slide = $this->collection->getNewEmptyItem();
            $slide->setData($persisted);
            $this->loadedData[$slide->getId() ?: ''] = $this->prepareSlideData($slide);
            $this->dataPersistor->clear('panth_heroslider_slide');
        }
        return $this->loadedData;
    }

    private function prepareSlideData($slide): array
    {
        $data = $slide->getData();
        foreach (['image_desktop', 'image_mobile'] as $field) {
            $value = $data[$field] ?? null;
            if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['[', '{'], true)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : null;
                $data[$field] = $value;
            }
            if (is_array($value)) {
                if (isset($value['name'])) {
                    $value = [$value];
                }
                $files = [];
                foreach ($value as $file) {
                    if (!is_array($file) || !is_string($file['name'] ?? null) || trim($file['name']) === '') {
                        continue;
                    }
                    if (empty($file['url'])) {
                        $file['url'] = $this->getImageUrl($file['name']);
                    }
                    $files[] = $file;
                }
                $data[$field] = $files ?: null;
            } elseif (!empty($data[$field])) {
                $data[$field] = [
                    array_merge(
                        [
                            'name' => $data[$field],
                            'url'  => $this->getImageUrl($data[$field]),
                        ],
                        $this->getFileMeta('panth/heroslider/slide/' . ltrim((string) $data[$field], '/'))
                    ),
                ];
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    private function getFileMeta(string $path): array
    {
        try {
            if ($this->mediaDirectory->isFile($path)) {
                $stat = $this->mediaDirectory->stat($path);
                $mime = function_exists('mime_content_type')
                    ? (string) mime_content_type($this->mediaDirectory->getAbsolutePath($path))
                    : '';
                return ['size' => (int) ($stat['size'] ?? 0), 'type' => $mime];
            }
        } catch (\Exception $e) {
            return ['size' => 0];
        }
        return ['size' => 0];
    }

    private function getImageUrl(string $name): string
    {
        $base = $this->storeManager
            ->getStore()
            ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return rtrim($base, '/') . '/panth/heroslider/slide/' . ltrim($name, '/');
    }
}
