<?php
/**
 * Copyright © Panth Infotech. All rights reserved.
 *
 * Frontend tracking endpoint. Accepts POST { slide_id, type, device }
 * and increments the daily stat bucket.
 *
 * CSRF protection is INTENTIONALLY bypassed via CsrfAwareActionInterface
 * because:
 *   1. Tracking events are sent from FPC-cached pages — those pages don't
 *      have a fresh form_key per visitor and we don't want to trigger
 *      cache misses just to mint a token.
 *   2. The endpoint accepts only whitelisted slide IDs (must exist + be
 *      active) and whitelisted event/device strings; an attacker who
 *      forges requests can at most inflate counters for slides that are
 *      already visible to them, with no read access and no privilege
 *      escalation.
 *   3. The endpoint never reads / writes session, customer, or order
 *      data — it touches a single counter table and nothing else.
 *
 * Defence-in-depth still in place:
 *   - POST-only (HttpPostActionInterface).
 *   - Strict input validation: int slide_id, enum event_type, enum
 *     device_type. Anything outside the allowlist returns 204 with no
 *     write.
 *   - Existence check: slide_id must resolve to an active row.
 *   - Atomic UPSERT in the tracker so race-conditions can't corrupt rows.
 */
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
        // Tracking endpoint — input is strictly validated and writes only
        // to a single counter row, no PII or auth-bound state. See class
        // docblock for the full security argument.
        return true;
    }
}
