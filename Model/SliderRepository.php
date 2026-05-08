<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Api\Data\SliderInterface;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Model\ResourceModel\Slider as SliderResource;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;

class SliderRepository implements SliderRepositoryInterface
{
    public function __construct(
        private readonly SliderResource $resource,
        private readonly SliderFactory $sliderFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(SliderInterface $slider): SliderInterface
    {
        try {
            $this->resource->save($slider);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the slider: %1', $e->getMessage()), $e);
        }
        return $slider;
    }

    public function getById(int $id): SliderInterface
    {
        $slider = $this->sliderFactory->create();
        $this->resource->load($slider, $id);
        if (!$slider->getId()) {
            throw new NoSuchEntityException(__('Slider with id "%1" does not exist.', $id));
        }
        return $slider;
    }

    public function getByIdentifier(string $identifier): ?SliderInterface
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter('identifier', $identifier)
            ->setPageSize(1);
        $first = $collection->getFirstItem();
        return $first->getId() ? $first : null;
    }

    public function delete(SliderInterface $slider): bool
    {
        try {
            $this->resource->delete($slider);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the slider: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }
}
