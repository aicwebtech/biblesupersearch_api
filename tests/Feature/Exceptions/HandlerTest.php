<?php

namespace Tests\Feature\Exceptions;

use App\Exceptions\Handler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * The exception handler translates three cases before delegating to Laravel: a missing model
 * becomes a 404, an expired CSRF token becomes a redirect carrying a csrf_error message, and
 * an unauthenticated API request becomes a 401 JSON body.
 */
class HandlerTest extends TestCase
{
    private function handler(): Handler
    {
        return $this->app->make(Handler::class);
    }

    public function testModelNotFoundIsRenderedAsANotFoundResponse(): void
    {
        $response = $this->handler()->render(Request::create('/missing'), new ModelNotFoundException());

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * An expired CSRF token must send the visitor back to the form with a flash message
     * rather than showing a raw 419 page.
     */
    public function testTokenMismatchRedirectsBackWithACsrfErrorMessage(): void
    {
        $request  = Request::create('http://localhost/admin/some-form', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->handler()->render($request, new TokenMismatchException());

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Your session timed out. Please try again.', $this->app['session.store']->get('csrf_error'));
    }

    public function testUnauthenticatedApiRequestGetsA401JsonBody(): void
    {
        $request = Request::create('/api/whatever');
        $request->headers->set('Accept', 'application/json');

        $method = new \ReflectionMethod(Handler::class, 'unauthenticated');

        $response = $method->invoke($this->handler(), $request, new AuthenticationException());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['error' => 'Unauthenticated.'], json_decode($response->getContent(), true));
    }

    public function testUnauthenticatedWebRequestRedirectsToLogin(): void
    {
        $request = Request::create('/admin');
        $request->setLaravelSession($this->app['session.store']);

        $method = new \ReflectionMethod(Handler::class, 'unauthenticated');

        $response = $method->invoke($this->handler(), $request, new AuthenticationException());

        $this->assertTrue($response->isRedirect());
    }

    /**
     * HttpException and ModelNotFoundException are listed in $dontReport so routine 404s do
     * not fill the log.
     */
    public function testRoutineHttpExceptionsAreNotReported(): void
    {
        $property = new \ReflectionProperty(Handler::class, 'dontReport');
        $dontReport = $property->getValue($this->handler());

        $this->assertContains(ModelNotFoundException::class, $dontReport);
        $this->assertContains(\Symfony\Component\HttpKernel\Exception\HttpException::class, $dontReport);
    }

    public function testReportDelegatesWithoutThrowing(): void
    {
        $this->assertNull($this->handler()->report(new NotFoundHttpException('nothing here')));
    }
}
