<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\HeroSlider\Api\SlideRepositoryInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_delete';

    public function __construct(
        Context $context,
        private readonly SlideRepositoryInterface $slideRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');
        if ($id) {
            try {
                $this->slideRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('Slide deleted.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Throwable $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find the slide to delete.'));
        return $resultRedirect->setPath('*/*/');
    }
}
