<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Block\Adminhtml\Slider\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $id = $this->getSliderId();
        if (!$id) {
            return [];
        }
        return [
            'label'      => __('Delete Slider'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this slider? Slides assigned to it will lose their group reference.'),
                $this->getUrl('*/*/delete', ['slider_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
