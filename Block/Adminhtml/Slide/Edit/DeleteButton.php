<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Block\Adminhtml\Slide\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $id = $this->getSlideId();
        if (!$id) {
            return [];
        }
        return [
            'label'      => __('Delete Slide'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this slide?'),
                $this->getUrl('*/*/delete', ['entity_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
