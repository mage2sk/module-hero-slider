<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slide;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Ui\Component\MassAction\Filter as UiFilter;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\ResourceModel\Slide\CollectionFactory;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slide_delete';

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
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        $errors = 0;
        foreach ($collection as $slide) {
            try {
                $this->slideRepository->delete($slide);
                $deleted++;
            } catch (\Throwable $e) {
                $errors++;
            }
        }
        if ($deleted > 0) {
            $this->messageManager->addSuccessMessage(__('%1 slide(s) deleted.', $deleted));
        }
        if ($errors > 0) {
            $this->messageManager->addErrorMessage(__('%1 slide(s) failed to delete.', $errors));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
