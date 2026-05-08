<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\HeroSlider\Api\Data\SliderInterface;

class Slider extends AbstractModel implements SliderInterface
{
    protected function _construct(): void
    {
        $this->_init(\Panth\HeroSlider\Model\ResourceModel\Slider::class);
    }

    public function getIdentifier(): ?string
    {
        $v = $this->getData(self::IDENTIFIER);
        return $v === null ? null : (string)$v;
    }

    public function setIdentifier(?string $identifier): self
    {
        return $this->setData(self::IDENTIFIER, $identifier);
    }

    public function getName(): ?string
    {
        $v = $this->getData(self::NAME);
        return $v === null ? null : (string)$v;
    }

    public function setName(?string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getStoreIds(): array
    {
        $ids = $this->getData(self::STORE_IDS);
        if (!is_array($ids)) {
            return [];
        }
        return array_map('intval', $ids);
    }

    public function setStoreIds(array $storeIds): self
    {
        return $this->setData(self::STORE_IDS, array_values(array_unique(array_map('intval', $storeIds))));
    }
}
