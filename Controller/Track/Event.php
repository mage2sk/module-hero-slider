<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Track;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\Config as HeroConfig;
use Panth\HeroSlider\Model\StatTracker;
use Panth\HeroSlider\Model\TrackGuard;

class Event implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly SlideRepositoryInterface $slideRepository,
        private readonly StatTracker $tracker,
        private readonly HeroConfig $heroConfig,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly TrackGuard $trackGuard
    ) {
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(204);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        if (!$this->heroConfig->isEnabled() || !$this->heroConfig->isAnalyticsEnabled()) {
            return $result;
        }

        $slideId    = (int)$this->request->getParam('slide_id');
        $eventType  = (string)$this->request->getParam('type');
        $deviceType = (string)$this->request->getParam('device');

        if ($slideId <= 0
            || !in_array($eventType, StatTracker::VALID_EVENTS, true)
            || !in_array($deviceType, StatTracker::VALID_DEVICES, true)) {
            return $result;
        }

        if ($this->trackGuard->isRateLimited()) {
            return $result->setHttpResponseCode(429);
        }

        try {
            $slide = $this->slideRepository->getById($slideId);
            if (!$slide->getIsActive()) {
                return $result;
            }
        } catch (NoSuchEntityException) {
            return $result;
        }

        if ($this->trackGuard->isDuplicate($slideId, $eventType)) {
            return $result;
        }

        $this->tracker->track($slideId, $eventType, $deviceType);
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(403);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        return new InvalidRequestException($result);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        if (!$request instanceof HttpRequest || !$request->isPost()) {
            return null;
        }
        return $this->formKeyValidator->validate($request);
    }
}
