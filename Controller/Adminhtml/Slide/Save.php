<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\ImageUploader;
use Panth\HeroSlider\Model\LinkUrlValidator;
use Panth\HeroSlider\Model\SlideFactory;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_save';

    public function __construct(
        Context $context,
        private readonly SlideFactory $slideFactory,
        private readonly SlideRepositoryInterface $slideRepository,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ImageUploader $imageUploader
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $linkUrl = trim((string)($data['link_url'] ?? ''));
            if ($linkUrl !== '' && !LinkUrlValidator::isAllowed($linkUrl)) {
                throw new LocalizedException(
                    __('The link URL must be a relative path or use http, https, mailto or tel.')
                );
            }
            foreach (['button_bg_color', 'button_text_color'] as $colorField) {
                $color = trim((string)($data[$colorField] ?? ''));
                if ($color !== '' && !LinkUrlValidator::isAllowedColor($color)) {
                    throw new LocalizedException(__('Button colors must be a CSS color such as #09090C.'));
                }
            }

            $id = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
            $slide = $id
                ? $this->slideRepository->getById($id)
                : $this->slideFactory->create();

            foreach (['image_desktop', 'image_mobile'] as $field) {
                $value = $data[$field] ?? null;
                if (is_array($value)) {
                    if (empty($value)) {
                        $data[$field] = null;
                    } else {
                        $first = reset($value);
                        $name = is_array($first) && !empty($first['name'])
                            ? $this->imageUploader->sanitizeImageName((string)$first['name'])
                            : '';
                        if ($name !== '') {
                            if (!empty($first['tmp_name']) && $this->imageUploader->isTmpFile($name)) {
                                $name = $this->imageUploader->moveFileFromTmp($name);
                            }
                            $data[$field] = $name;
                        } else {
                            $data[$field] = null;
                        }
                    }
                } elseif (is_string($value) && $value !== '') {
                    $data[$field] = $this->imageUploader->sanitizeImageName($value) ?: null;
                }
            }

            foreach (['button_label', 'button_bg_color', 'button_text_color', 'image_alt', 'link_url', 'image_mobile'] as $maybeNull) {
                if (isset($data[$maybeNull]) && $data[$maybeNull] === '') {
                    $data[$maybeNull] = null;
                }
            }
            $data['is_active'] = !empty($data['is_active']) ? 1 : 0;
            $data['sort_order'] = (int)($data['sort_order'] ?? 0);

            $slide->addData($data);
            $this->slideRepository->save($slide);
            $this->messageManager->addSuccessMessage(__('Slide saved.'));
            $this->dataPersistor->clear('panth_heroslider_slide');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $slide->getId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Could not save the slide.'));
        }

        $this->dataPersistor->set('panth_heroslider_slide', $data);
        return $resultRedirect->setPath('*/*/edit', ['entity_id' => (int)($data['entity_id'] ?? 0)]);
    }
}
