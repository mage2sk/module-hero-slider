<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Source model that powers the "Slider" dropdown on the slide form.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;

class SliderOptions implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Select Slider --')]];
        $coll = $this->collectionFactory->create();
        foreach ($coll as $slider) {
            $options[] = [
                'value' => (int)$slider->getId(),
                'label' => sprintf('%s (%s)', $slider->getName(), $slider->getIdentifier()),
            ];
        }
        return $options;
    }
}
