<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Adminhtml\Slider;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter as UiFilter;
use Panth\HeroSlider\Api\SliderRepositoryInterface;
use Panth\HeroSlider\Model\ResourceModel\Slider\CollectionFactory;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_HeroSlider::slider_delete';

    public function __construct(
        Context $context,
        private readonly UiFilter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly SliderRepositoryInterface $sliderRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        $errors = 0;
        foreach ($collection as $slider) {
            try {
                $this->sliderRepository->delete($slider);
                $deleted++;
            } catch (\Throwable) {
                $errors++;
            }
        }
        if ($deleted > 0) {
            $this->messageManager->addSuccessMessage(__('%1 slider(s) deleted.', $deleted));
        }
        if ($errors > 0) {
            $this->messageManager->addErrorMessage(__('%1 slider(s) failed to delete.', $errors));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
