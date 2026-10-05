<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Api;

use Panth\HeroSlider\Api\Data\SlideInterface;

interface SlideRepositoryInterface
{
    public function save(SlideInterface $slide): SlideInterface;

    public function getById(int $id): SlideInterface;

    public function delete(SlideInterface $slide): bool;

    public function deleteById(int $id): bool;
}
