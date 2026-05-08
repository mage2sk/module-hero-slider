<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slider;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Panth\HeroSlider\Api\SliderRepositoryInterface;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slider_save';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly SliderRepositoryInterface $sliderRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('slider_id');
        if ($id) {
            try {
                $slider = $this->sliderRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This slider no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $this->registry->register('panth_heroslider_slider', $slider);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_HeroSlider::sliders');
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Slider') : __('New Slider'));
        return $resultPage;
    }
}
