<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Panth\HeroSlider\Model\ImageUploader;
use Panth\Core\Security\UploadExtensionPolicy;

class Upload extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_save';

    public function __construct(
        Context $context,
        private readonly ImageUploader $imageUploader,
        private readonly JsonFactory $resultJsonFactory,
        private readonly UploadExtensionPolicy $uploadExtensionPolicy
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $param = $this->getRequest()->getParam('param', 'image_desktop');

            if (isset($_FILES[$param]['name']) && is_string($_FILES[$param]['name'])) {
                $this->uploadExtensionPolicy->assertSafeExtension($_FILES[$param]['name']);
            }

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
