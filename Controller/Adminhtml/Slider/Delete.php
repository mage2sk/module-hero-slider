<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slider;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\HeroSlider\Api\SliderRepositoryInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slider_delete';

    public function __construct(Context $context, private readonly SliderRepositoryInterface $sliderRepository)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('slider_id');
        if ($id) {
            try {
                $this->sliderRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('Slider deleted.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['slider_id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find the slider to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
