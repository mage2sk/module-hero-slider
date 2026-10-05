<?php
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
        $escaper = $this->context->getEscaper();
        return [
            'label'      => __('Delete'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                "deleteConfirm('%s', '%s', {\"data\": {}})",
                $escaper->escapeJs($escaper->escapeHtml(__('Are you sure you want to delete this slide?'))),
                $this->getUrl('*/*/delete', ['entity_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
