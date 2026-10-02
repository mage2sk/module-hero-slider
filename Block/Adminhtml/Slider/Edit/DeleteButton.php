<?php
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
        $escaper = $this->context->getEscaper();
        return [
            'label'      => __('Delete'),
            'class'      => 'delete',
            'on_click'   => sprintf(
                "deleteConfirm('%s', '%s', {\"data\": {}})",
                $escaper->escapeJs($escaper->escapeHtml(__(
                    'Are you sure you want to delete this slider? Slides assigned to it will lose their group reference.'
                ))),
                $this->getUrl('*/*/delete', ['slider_id' => $id])
            ),
            'sort_order' => 20,
        ];
    }
}
