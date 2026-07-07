<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Model\Slider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;

class DataProvider extends AbstractDataProvider
{
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
