<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Controller\Track;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Model\StatTracker;

class Event implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly SlideRepositoryInterface $slideRepository,
        private readonly StatTracker $tracker
    ) {
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(204);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        $body = (string)$this->request->getContent();
        $payload = $body !== '' ? json_decode($body, true) : null;
        if (!is_array($payload)) {
            $payload = [
                'slide_id' => $this->request->getParam('slide_id'),
                'type'     => $this->request->getParam('type'),
                'device'   => $this->request->getParam('device'),
            ];
        }

        $slideId    = isset($payload['slide_id']) ? (int)$payload['slide_id'] : 0;
        $eventType  = isset($payload['type'])     ? (string)$payload['type']  : '';
        $deviceType = isset($payload['device'])   ? (string)$payload['device']: '';

        if ($slideId <= 0
            || !in_array($eventType, StatTracker::VALID_EVENTS, true)
            || !in_array($deviceType, StatTracker::VALID_DEVICES, true)) {
            return $result;
        }

        try {
            $slide = $this->slideRepository->getById($slideId);
            if (!$slide->getIsActive()) {
                return $result;
            }
        } catch (NoSuchEntityException) {
            return $result;
        }

        $this->tracker->track($slideId, $eventType, $deviceType);
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
