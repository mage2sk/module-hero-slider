<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Panth\HeroSlider\Model\ImageUploader;
use Panth\Core\Security\UploadExtensionPolicy;

class Upload extends Action implements HttpPostActionInterface
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
            $param = (string)$this->getRequest()->getParam('param', 'image_desktop');
            if (!in_array($param, ['image_desktop', 'image_mobile'], true)) {
                throw new LocalizedException(__('Unknown image field.'));
            }

            $file = $this->getRequest()->getFiles($param);
            if (!is_array($file) || !isset($file['name']) || !is_string($file['name'])) {
                throw new LocalizedException(__('No image file was uploaded.'));
            }
            $this->uploadExtensionPolicy->assertSafeExtension($file['name']);

            $result = $this->imageUploader->saveFileToTmpDir($param);
            return $resultJson->setData($result);
        } catch (\Throwable $e) {
            return $resultJson->setData(['error' => $e->getMessage(), 'errorcode' => $e->getCode()]);
        }
    }
}
