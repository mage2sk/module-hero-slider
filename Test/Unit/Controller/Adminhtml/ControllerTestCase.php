<?php
declare(strict_types=1);

namespace Panth\HeroSlider\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Shared doubles for backend controller tests: records redirects and messages.
 */
abstract class ControllerTestCase extends TestCase
{
    /** @var array{0:string,1:array}|null */
    protected ?array $redirect = null;
    protected array $success = [];
    protected array $errors = [];
    protected array $exceptions = [];

    protected function context(array $params = [], ?array $post = null, array $files = []): Context
    {
        $this->redirect = null;
        $this->success = $this->errors = $this->exceptions = [];

        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn($key, $default = null) => array_key_exists($key, $params) ? $params[$key] : $default
        );
        $request->method('getPostValue')->willReturn($post);
        $request->method('getFiles')->willReturnCallback(static fn($key = null) => $files[$key] ?? null);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $params = []) use ($redirect) {
            $this->redirect = [$path, $params];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addSuccessMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->success[] = (string)$m;
            return $messages;
        });
        $messages->method('addErrorMessage')->willReturnCallback(function ($m) use ($messages) {
            $this->errors[] = (string)$m;
            return $messages;
        });
        $messages->method('addExceptionMessage')->willReturnCallback(function ($e, $m = null) use ($messages) {
            $this->exceptions[] = (string)$m;
            return $messages;
        });

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        return $context;
    }
}
