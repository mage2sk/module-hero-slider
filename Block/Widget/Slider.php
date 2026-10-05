<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Block\Widget;

use Magento\Widget\Block\BlockInterface;
use Panth\HeroSlider\Block\Slider as SliderBlock;

class Slider extends SliderBlock implements BlockInterface
{
    protected $_template = 'Panth_HeroSlider::slider.phtml';
}
