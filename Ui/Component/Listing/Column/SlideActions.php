<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SlideActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['entity_id'])) {
                continue;
            }
            $id = (int)$item['entity_id'];
            $item[$name]['edit'] = [
                'href'  => $this->urlBuilder->getUrl('panth_heroslider/slide/edit', ['entity_id' => $id]),
                'label' => __('Edit'),
            ];
            $item[$name]['delete'] = [
                'href'    => $this->urlBuilder->getUrl('panth_heroslider/slide/delete', ['entity_id' => $id]),
                'label'   => __('Delete'),
                'confirm' => [
                    'title'   => __('Delete slide #%1', $id),
                    'message' => __('Are you sure you want to delete this slide?'),
                ],
            ];
        }
        return $dataSource;
    }
}
