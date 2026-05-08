<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model\Slider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
    /** @var array<int|string,array> */
    protected $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly SliderResource $sliderResource,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        foreach ($this->collection->getItems() as $slider) {
            $row = $slider->getData();
            // Collection iteration does NOT call ResourceModel::_afterLoad,
            // so store_ids must be populated explicitly. Cast to STRING:
            // Magento_Cms's store-options source returns option values as
            // strings, and Magento UI multiselect uses strict equality
            // (`===`) for pre-selection — an int [0] won't match a
            // string "0" and the field renders as if nothing is selected.
            $row['store_ids'] = array_map(
                'strval',
                $this->sliderResource->lookupStoreIds((int)$slider->getId())
            );
            $this->loadedData[$slider->getId()] = $row;
        }
        $persisted = $this->dataPersistor->get('panth_heroslider_slider');
        if (!empty($persisted)) {
            $slider = $this->collection->getNewEmptyItem();
            $slider->setData($persisted);
            $this->loadedData[$slider->getId() ?: ''] = $slider->getData();
            $this->dataPersistor->clear('panth_heroslider_slider');
        }
        return $this->loadedData;
    }
}
