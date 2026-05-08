<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Panth\HeroSlider\Api\SlideRepositoryInterface;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_save';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly SlideRepositoryInterface $slideRepository,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        if ($id) {
            try {
                $slide = $this->slideRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This slide no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $this->registry->register('panth_heroslider_slide', $slide);
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_HeroSlider::slides');
        $resultPage->addBreadcrumb(__('Hero Slider'), __('Hero Slider'));
        $resultPage->addBreadcrumb($id ? __('Edit Slide') : __('New Slide'), $id ? __('Edit Slide') : __('New Slide'));
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Slide') : __('New Slide'));
        return $resultPage;
    }
}
