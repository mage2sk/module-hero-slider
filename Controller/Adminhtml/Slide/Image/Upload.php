<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Panth\HeroSlider\Model\ImageUploader;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_save';

    public function __construct(
        Context $context,
        private readonly ImageUploader $imageUploader,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $param = $this->getRequest()->getParam('param', 'image_desktop');
            $result = $this->imageUploader->saveFileToTmpDir((string)$param);
            $result['cookie'] = [
                'name'     => $this->_session->getName(),
                'value'    => $this->_session->getSessionId(),
                'lifetime' => $this->_session->getCookieLifetime(),
                'path'     => $this->_session->getCookiePath(),
                'domain'   => $this->_session->getCookieDomain(),
            ];
            return $resultJson->setData($result);
        } catch (\Throwable $e) {
            return $resultJson->setData(['error' => $e->getMessage(), 'errorcode' => $e->getCode()]);
        }
    }
}
