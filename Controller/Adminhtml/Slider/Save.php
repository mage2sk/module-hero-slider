<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slider;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Model\SliderFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slider_save';

    public function __construct(
        Context $context,
        private readonly SliderFactory $sliderFactory,
        private readonly SliderRepositoryInterface $sliderRepository,
        private readonly DataPersistorInterface $dataPersistor
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
            $id = isset($data['slider_id']) ? (int)$data['slider_id'] : 0;
            $slider = $id
                ? $this->sliderRepository->getById($id)
                : $this->sliderFactory->create();

            $identifier = isset($data['identifier']) ? trim((string)$data['identifier']) : '';
            $identifier = preg_replace('/[^a-z0-9_\-]/i', '', $identifier);
            $identifier = strtolower((string)$identifier);
            if ($identifier === '') {
                throw new LocalizedException(
                    __('Identifier is required and must be lowercase letters, digits, hyphen or underscore only.')
                );
            }

            $rawStoreIds = $data['store_ids'] ?? [];
            if (is_string($rawStoreIds)) {
                $rawStoreIds = explode(',', $rawStoreIds);
            }
            // Keep ANY non-empty submitted value (including string "0"),
            // then cast to int. Don't filter out 0 — it represents the
            // "All Store Views" option and is a valid selection.
            $storeIds = [];
            foreach ((array)$rawStoreIds as $v) {
                if ($v === '' || $v === null) {
                    continue;
                }
                $storeIds[] = (int)$v;
            }
            $storeIds = array_values(array_unique($storeIds));

            $slider->addData([
                'identifier' => $identifier,
                'name'       => isset($data['name']) ? (string)$data['name'] : '',
                'is_active'  => !empty($data['is_active']) ? 1 : 0,
                'store_ids'  => $storeIds,
            ]);
            $this->sliderRepository->save($slider);
            $this->messageManager->addSuccessMessage(__('Slider saved.'));
            $this->dataPersistor->clear('panth_heroslider_slider');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['slider_id' => $slider->getId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addExceptionMessage($e, __('Could not save the slider.'));
        }

        $this->dataPersistor->set('panth_heroslider_slider', $data);
        return $resultRedirect->setPath('*/*/edit', ['slider_id' => (int)($data['slider_id'] ?? 0)]);
    }
}
