<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class ImagePreview extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        $field = $this->getData('name');
        $base = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/panth/heroslider/slide/';

        foreach ($dataSource['data']['items'] as &$item) {
            $val = $item[$field] ?? null;
            if (is_string($val) && $val !== '') {
                $url = $base . ltrim($val, '/');
                $item[$field . '_src'] = $url;
                $item[$field . '_orig_src'] = $url;
                $item[$field . '_alt'] = $item['title'] ?? '';
                $item[$field . '_link'] = '';
            } else {
                $item[$field . '_src'] = '';
                $item[$field . '_orig_src'] = '';
                $item[$field . '_alt'] = '';
                $item[$field . '_link'] = '';
            }
        }
        return $dataSource;
    }
}
