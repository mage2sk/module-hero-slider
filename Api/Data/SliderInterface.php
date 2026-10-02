<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Api\Data;

interface SliderInterface
{
    public const SLIDER_ID  = 'slider_id';
    public const IDENTIFIER = 'identifier';
    public const NAME       = 'name';
    public const IS_ACTIVE  = 'is_active';
    public const STORE_IDS  = 'store_ids';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getId();
    public function setId($id);

    public function getIdentifier(): ?string;
    public function setIdentifier(?string $identifier): self;

    public function getName(): ?string;
    public function setName(?string $name): self;

    public function getIsActive(): bool;
    public function setIsActive(bool $isActive): self;

    /** @return int[] */
    public function getStoreIds(): array;
    /** @param int[] $storeIds */
    public function setStoreIds(array $storeIds): self;
}
