<?php

namespace App\Tests\EventListener;

use App\EventListener\ApiDocsUiGuardListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiDocsUiGuardListenerTest extends TestCase
{
    private function event(string $path, ?string $accept, bool $main = true): RequestEvent
    {
        $request = Request::create($path);
        // Request::create() añade un Accept de navegador por defecto.
        if (null !== $accept) {
            $request->headers->set('Accept', $accept);
        } else {
            $request->headers->remove('Accept');
        }

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function htmlRequests(): iterable
    {
        yield 'navegador en /api/docs' => ['/api/docs', 'text/html,application/xhtml+xml,*/*;q=0.8'];
        yield 'navegador con barra final' => ['/api/docs/', 'text/html'];
        yield 'extensión .html' => ['/api/docs.html', 'application/json'];
    }

    /**
     * @dataProvider htmlRequests
     */
    public function testBloqueaLaInterfazWebCuandoEstaDesactivada(string $path, string $accept): void
    {
        $listener = new ApiDocsUiGuardListener(false);

        $this->expectException(NotFoundHttpException::class);

        $listener($this->event($path, $accept));
    }

    /**
     * @dataProvider htmlRequests
     */
    public function testDejaPasarLaInterfazWebCuandoEstaActivada(string $path, string $accept): void
    {
        $listener = new ApiDocsUiGuardListener(true);

        $listener($this->event($path, $accept));

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function jsonRequests(): iterable
    {
        yield 'application/json en /api/docs' => ['/api/docs', 'application/json'];
        yield 'sin cabecera Accept' => ['/api/docs', null];
        yield 'comodín (curl)' => ['/api/docs', '*/*'];
        yield 'openapi json' => ['/api/docs.jsonopenapi', 'text/html'];
        yield 'json-ld' => ['/api/docs.jsonld', 'text/html'];
        yield 'json' => ['/api/docs.json', 'text/html'];
    }

    /**
     * @dataProvider jsonRequests
     */
    public function testNuncaBloqueaElJsonDeOpenApi(string $path, ?string $accept): void
    {
        $listener = new ApiDocsUiGuardListener(false);

        $listener($this->event($path, $accept));

        $this->addToAssertionCount(1);
    }

    public function testIgnoraRutasQueNoSonLaDocumentacion(): void
    {
        $listener = new ApiDocsUiGuardListener(false);

        $listener($this->event('/api/communication/packages', 'text/html'));
        $listener($this->event('/api/docsx', 'text/html'));
        $listener($this->event('/dashboard/api/docs', 'text/html'));

        $this->addToAssertionCount(1);
    }

    public function testIgnoraSubpeticiones(): void
    {
        $listener = new ApiDocsUiGuardListener(false);

        $listener($this->event('/api/docs', 'text/html', main: false));

        $this->addToAssertionCount(1);
    }
}
