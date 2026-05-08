<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\HeroSlider\Api\Data\SlideInterface;

class Slide extends AbstractModel implements SlideInterface
{
    protected function _construct(): void
    {
        $this->_init(\Panth\HeroSlider\Model\ResourceModel\Slide::class);
    }

    public function getTitle(): ?string
    {
        $v = $this->getData(self::TITLE);
        return $v === null ? null : (string)$v;
    }

    public function setTitle(?string $title): self
    {
        return $this->setData(self::TITLE, $title);
    }

    public function getLinkUrl(): ?string
    {
        $v = $this->getData(self::LINK_URL);
        return $v === null ? null : (string)$v;
    }

    public function setLinkUrl(?string $url): self
    {
        return $this->setData(self::LINK_URL, $url);
    }

    public function getButtonLabel(): ?string
    {
        $v = $this->getData(self::BUTTON_LABEL);
        return $v === null ? null : (string)$v;
    }

    public function setButtonLabel(?string $label): self
    {
        return $this->setData(self::BUTTON_LABEL, $label);
    }

    public function getButtonBgColor(): ?string
    {
        $v = $this->getData(self::BUTTON_BG_COLOR);
        return $v === null ? null : (string)$v;
    }

    public function setButtonBgColor(?string $color): self
    {
        return $this->setData(self::BUTTON_BG_COLOR, $color);
    }

    public function getButtonTextColor(): ?string
    {
        $v = $this->getData(self::BUTTON_TEXT_COLOR);
        return $v === null ? null : (string)$v;
    }

    public function setButtonTextColor(?string $color): self
    {
        return $this->setData(self::BUTTON_TEXT_COLOR, $color);
    }

    public function getImageDesktop(): ?string
    {
        $v = $this->getData(self::IMAGE_DESKTOP);
        return $v === null ? null : (string)$v;
    }

    public function setImageDesktop(?string $path): self
    {
        return $this->setData(self::IMAGE_DESKTOP, $path);
    }

    public function getImageMobile(): ?string
    {
        $v = $this->getData(self::IMAGE_MOBILE);
        return $v === null ? null : (string)$v;
    }

    public function setImageMobile(?string $path): self
    {
        return $this->setData(self::IMAGE_MOBILE, $path);
    }

    public function getImageAlt(): ?string
    {
        $v = $this->getData(self::IMAGE_ALT);
        return $v === null ? null : (string)$v;
    }

    public function setImageAlt(?string $alt): self
    {
        return $this->setData(self::IMAGE_ALT, $alt);
    }

    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $order): self
    {
        return $this->setData(self::SORT_ORDER, $order);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }
}
