<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Controller\Track;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\NoSuchEntityException;
use Panth\HeroSlider\Api\SlideRepositoryInterface;
use Panth\HeroSlider\Controller\Track\Event;
use Panth\HeroSlider\Model\Config;
use Panth\HeroSlider\Model\Slide;
use Panth\HeroSlider\Model\StatTracker;
use Panth\HeroSlider\Model\TrackGuard;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private ?int $code = null;
    private array $headers = [];
    private Config $config;
    private TrackGuard $guard;
    private SlideRepositoryInterface $repo;
    private StatTracker $tracker;
    private FormKeyValidator $formKeyValidator;

    protected function setUp(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isAnalyticsEnabled')->willReturn(true);
        $this->guard = $this->createStub(TrackGuard::class);
        $this->repo = $this->createStub(SlideRepositoryInterface::class);
        $this->tracker = $this->createStub(StatTracker::class);
        $this->formKeyValidator = $this->createStub(FormKeyValidator::class);
    }

    private function slide(bool $active): Slide
    {
        $slide = $this->createStub(Slide::class);
        $slide->method('getIsActive')->willReturn($active);
        return $slide;
    }

    private function controller(array $params): Event
    {
        $this->code = null;
        $this->headers = [];
        $raw = $this->createStub(Raw::class);
        $raw->method('setHttpResponseCode')->willReturnCallback(function ($c) use ($raw) {
            $this->code = $c;
            return $raw;
        });
        $raw->method('setHeader')->willReturnCallback(function ($n, $v) use ($raw) {
            $this->headers[$n] = $v;
            return $raw;
        });
        $rawFactory = $this->createStub(RawFactory::class);
        $rawFactory->method('create')->willReturn($raw);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($k) => $params[$k] ?? null);

        return new Event($request, $rawFactory, $this->repo, $this->tracker, $this->config, $this->formKeyValidator, $this->guard);
    }

    private function expectNoTracking(): void
    {
        $this->tracker = $this->createMock(StatTracker::class);
        $this->tracker->expects($this->never())->method('track');
    }

    public function testValidEventIsTrackedWithNoCacheHeaders(): void
    {
        $this->repo->method('getById')->willReturn($this->slide(true));
        $this->tracker = $this->createMock(StatTracker::class);
        $this->tracker->expects($this->once())->method('track')->with(5, 'click', 'mobile');

        $this->controller(['slide_id' => '5', 'type' => 'click', 'device' => 'mobile'])->execute();

        $this->assertSame(204, $this->code);
        $this->assertStringContainsString('no-store', $this->headers['Cache-Control']);
        $this->assertSame('no-cache', $this->headers['Pragma']);
    }

    public function testDisabledAnalyticsIgnoresRequest(): void
    {
        $this->config = $this->createStub(Config::class);
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isAnalyticsEnabled')->willReturn(false);
        $this->expectNoTracking();

        $this->controller(['slide_id' => '5', 'type' => 'view', 'device' => 'desktop'])->execute();
        $this->assertSame(204, $this->code);
    }

    public function testInvalidParametersAreIgnoredBeforeRateLimiting(): void
    {
        $this->expectNoTracking();
        $this->guard = $this->createMock(TrackGuard::class);
        $this->guard->expects($this->never())->method('isRateLimited');

        foreach ([
            ['slide_id' => '0', 'type' => 'view', 'device' => 'desktop'],
            ['slide_id' => '5', 'type' => 'hover', 'device' => 'desktop'],
            ['slide_id' => '5', 'type' => 'view', 'device' => 'fridge'],
        ] as $params) {
            $this->controller($params)->execute();
            $this->assertSame(204, $this->code);
        }
    }

    public function testRateLimitedRequestGets429(): void
    {
        $this->guard->method('isRateLimited')->willReturn(true);
        $this->expectNoTracking();

        $this->controller(['slide_id' => '5', 'type' => 'view', 'device' => 'desktop'])->execute();
        $this->assertSame(429, $this->code);
    }

    public function testMissingOrInactiveSlideIsIgnored(): void
    {
        $this->expectNoTracking();
        $this->repo->method('getById')->willReturnCallback(function (int $id) {
            if ($id === 404) {
                throw new NoSuchEntityException(__('missing'));
            }
            return $this->slide(false);
        });

        $this->controller(['slide_id' => '404', 'type' => 'view', 'device' => 'desktop'])->execute();
        $this->assertSame(204, $this->code);
        $this->controller(['slide_id' => '6', 'type' => 'view', 'device' => 'desktop'])->execute();
        $this->assertSame(204, $this->code);
    }

    public function testDuplicateEventInSessionIsNotCountedTwice(): void
    {
        $this->repo->method('getById')->willReturn($this->slide(true));
        $this->guard->method('isDuplicate')->willReturn(true);
        $this->expectNoTracking();

        $this->controller(['slide_id' => '5', 'type' => 'view', 'device' => 'desktop'])->execute();
        $this->assertSame(204, $this->code);
    }

    public function testCsrfExceptionCarries403(): void
    {
        $exception = $this->controller([])->createCsrfValidationException($this->createStub(RequestInterface::class));

        $this->assertInstanceOf(InvalidRequestException::class, $exception);
        $this->assertSame(403, $this->code);
    }

    public function testCsrfValidationUsesFormKeyForPostOnly(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $post = $this->createStub(HttpRequest::class);
        $post->method('isPost')->willReturn(true);
        $this->formKeyValidator->expects($this->once())->method('validate')->with($post)->willReturn(false);
        $get = $this->createStub(HttpRequest::class);
        $get->method('isPost')->willReturn(false);

        $controller = $this->controller([]);
        $this->assertFalse($controller->validateForCsrf($post));
        $this->assertNull($controller->validateForCsrf($get));
        $this->assertNull($controller->validateForCsrf($this->createStub(RequestInterface::class)));
    }
}
