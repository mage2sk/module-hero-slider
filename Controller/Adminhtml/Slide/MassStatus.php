<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter as UiFilter;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;

class MassStatus extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_save';

    public function __construct(
        Context $context,
        private readonly UiFilter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly SlideRepositoryInterface $slideRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $statusParam = $this->getRequest()->getParam('status');
        if ($statusParam === null) {
            $this->messageManager->addErrorMessage(__('Missing status parameter.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $status = (int)$statusParam;

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $updated = 0;
        $errors = 0;
        foreach ($collection as $slide) {
            try {
                $slide->setIsActive((bool)$status);
                $this->slideRepository->save($slide);
                $updated++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }
        if ($updated > 0) {
            $this->messageManager->addSuccessMessage(
                __('%1 slide(s) %2.', $updated, $status ? __('enabled') : __('disabled'))
            );
        }
        if ($errors > 0) {
            $this->messageManager->addErrorMessage(__('%1 slide(s) failed to update.', $errors));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
