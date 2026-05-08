<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Api\Data\SlideInterface;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\ResourceModel\Slide as SlideResource;

class SlideRepository implements SlideRepositoryInterface
{
    public function __construct(
        private readonly SlideResource $resource,
        private readonly SlideFactory $slideFactory
    ) {
    }

    public function save(SlideInterface $slide): SlideInterface
    {
        try {
            $this->resource->save($slide);
        } catch (\Throwable $e) {
            throw new CouldNotSaveException(__('Could not save the slide: %1', $e->getMessage()), $e);
        }
        return $slide;
    }

    public function getById(int $id): SlideInterface
    {
        $slide = $this->slideFactory->create();
        $this->resource->load($slide, $id);
        if (!$slide->getId()) {
            throw new NoSuchEntityException(__('Slide with id "%1" does not exist.', $id));
        }
        return $slide;
    }

    public function delete(SlideInterface $slide): bool
    {
        try {
            $this->resource->delete($slide);
        } catch (\Throwable $e) {
            throw new CouldNotDeleteException(__('Could not delete the slide: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }
}
