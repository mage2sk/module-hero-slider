<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * CMS widget thin wrapper. Magento's widget renderer creates this block,
 * passes the chosen `slider_identifier` parameter as block data, then
 * delegates to the regular Slider block's template via inheritance.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Block\Widget;

use Magento\Widget\Block\BlockInterface;
use Panth\HeroSlider\Block\Slider as SliderBlock;

class Slider extends SliderBlock implements BlockInterface
{
    protected $_template = 'Panth_HeroSlider::slider.phtml';
}
