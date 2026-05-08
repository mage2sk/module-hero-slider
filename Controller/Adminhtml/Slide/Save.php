<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\ImageUploader;
use Panth\HeroSlider\Model\SlideFactory;

class Save extends Action
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
            $id = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
            $slide = $id
                ? $this->slideRepository->getById($id)
                : $this->slideFactory->create();

            // Image fields arrive as either a string path (existing) or an
            // array from the file uploader. We collapse to the relative path
            // and move tmp uploads into their final dir.
            foreach (['image_desktop', 'image_mobile'] as $field) {
                $value = $data[$field] ?? null;
                if (is_array($value)) {
                    if (empty($value)) {
                        $data[$field] = null;
                    } else {
                        $first = reset($value);
                        if (!empty($first['name'])) {
                            $name = $first['name'];
                            // tmp-uploaded file: move to permanent dir
                            if (!empty($first['tmp_name'])) {
                                $this->imageUploader->moveFileFromTmp($name);
                            }
                            $data[$field] = $name;
                        } else {
                            $data[$field] = null;
                        }
                    }
                }
            }

            // Sanitise empties + booleans
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
