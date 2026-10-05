<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Api\Data;

interface SlideInterface
{
    public const ENTITY_ID         = 'entity_id';
    public const SLIDER_ID         = 'slider_id';
    public const TITLE             = 'title';
    public const LINK_URL          = 'link_url';
    public const BUTTON_LABEL      = 'button_label';
    public const BUTTON_BG_COLOR   = 'button_bg_color';
    public const BUTTON_TEXT_COLOR = 'button_text_color';
    public const IMAGE_DESKTOP     = 'image_desktop';
    public const IMAGE_MOBILE      = 'image_mobile';
    public const IMAGE_ALT         = 'image_alt';
    public const SORT_ORDER        = 'sort_order';
    public const IS_ACTIVE         = 'is_active';
    public const CREATED_AT        = 'created_at';
    public const UPDATED_AT        = 'updated_at';

    public function getId();
    public function setId($id);

    public function getSliderId(): ?int;
    public function setSliderId(?int $sliderId): self;

    public function getTitle(): ?string;
    public function setTitle(?string $title): self;

    public function getLinkUrl(): ?string;
    public function setLinkUrl(?string $url): self;

    public function getButtonLabel(): ?string;
    public function setButtonLabel(?string $label): self;

    public function getButtonBgColor(): ?string;
    public function setButtonBgColor(?string $color): self;

    public function getButtonTextColor(): ?string;
    public function setButtonTextColor(?string $color): self;

    public function getImageDesktop(): ?string;
    public function setImageDesktop(?string $path): self;

    public function getImageMobile(): ?string;
    public function setImageMobile(?string $path): self;

    public function getImageAlt(): ?string;
    public function setImageAlt(?string $alt): self;

    public function getSortOrder(): int;
    public function setSortOrder(int $order): self;

    public function getIsActive(): bool;
    public function setIsActive(bool $isActive): self;
}
