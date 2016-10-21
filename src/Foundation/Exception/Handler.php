<?php
/**
 * This file is part of Notadd.
 * @author TwilRoad <269044570@qq.com>
 * @copyright (c) 2016, iBenchu.org
 * @datetime 2016-10-21 09:50
 */
namespace Notadd\Foundation\Exception;
use Exception;
use Psr\Log\LoggerInterface;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exception\HttpResponseException;
use Symfony\Component\Debug\Exception\FlattenException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
/**
 * Class Handler
 * @package Notadd\Foundation\Exceptions
 */
class Handler implements ExceptionHandlerContract {
    /**
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;
    /**
     * @var array
     */
    protected $dontReport = [];
    /**
     * @param \Illuminate\Contracts\Container\Container $container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    /**
     * @param \Exception $e
     * @return void
     * @throws \Exception
     */
    public function report(Exception $e) {
        if($this->shouldntReport($e)) {
            return;
        }
        try {
            $logger = $this->container->make(LoggerInterface::class);
        } catch(Exception $ex) {
            throw $e; // throw the original exception
        }
        $logger->error($e);
    }
    /**
     * @param \Exception $e
     * @return bool
     */
    public function shouldReport(Exception $e) {
        return !$this->shouldntReport($e);
    }
    /**
     * @param \Exception $e
     * @return bool
     */
    protected function shouldntReport(Exception $e) {
        $dontReport = array_merge($this->dontReport, [HttpResponseException::class]);
        foreach($dontReport as $type) {
            if($e instanceof $type) {
                return true;
            }
        }
        return false;
    }
    /**
     * @param \Exception $e
     * @return \Exception
     */
    protected function prepareException(Exception $e) {
        if($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        } elseif($e instanceof AuthorizationException) {
            $e = new HttpException(403, $e->getMessage());
        }
        return $e;
    }
    /**
     * @param \Illuminate\Http\Request $request
     * @param \Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Exception $e) {
        $e = $this->prepareException($e);
        if($e instanceof HttpResponseException) {
            return $e->getResponse();
        } elseif($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        } elseif($e instanceof ValidationException) {
            return $this->convertValidationExceptionToResponse($e, $request);
        }
        return $this->prepareResponse($request, $e);
    }
    /**
     * @param \Illuminate\Http\Request $request
     * @param \Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareResponse($request, Exception $e) {
        if($this->isHttpException($e)) {
            return $this->toIlluminateResponse($this->renderHttpException($e), $e);
        } else {
            return $this->toIlluminateResponse($this->convertExceptionToResponse($e), $e);
        }
    }
    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @param \Exception $e
     * @return \Illuminate\Http\Response
     */
    protected function toIlluminateResponse($response, Exception $e) {
        if($response instanceof SymfonyRedirectResponse) {
            $response = new RedirectResponse($response->getTargetUrl(), $response->getStatusCode(), $response->headers->all());
        } else {
            $response = new Response($response->getContent(), $response->getStatusCode(), $response->headers->all());
        }
        return $response->withException($e);
    }
    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Exception $e
     * @return void
     */
    public function renderForConsole($output, Exception $e) {
        (new ConsoleApplication)->renderException($e, $output);
    }
    /**
     * @param \Symfony\Component\HttpKernel\Exception\HttpException $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpException $e) {
        $status = $e->getStatusCode();
        if(view()->exists("errors.{$status}")) {
            return response()->view("errors.{$status}", ['exception' => $e], $status, $e->getHeaders());
        } else {
            return $this->convertExceptionToResponse($e);
        }
    }
    /**
     * @param \Illuminate\Validation\ValidationException $e
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request) {
        if($e->response) {
            return $e->response;
        }
        $errors = $e->validator->errors()->getMessages();
        if($request->expectsJson()) {
            return response()->json($errors, 422);
        }
        return redirect()->back()->withInput($request->input())->withErrors($errors);
    }
    /**
     * @param \Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertExceptionToResponse(Exception $e) {
        $e = FlattenException::create($e);
        $handler = new SymfonyExceptionHandler(config('app.debug'));
        return SymfonyResponse::create($handler->getHtml($e), $e->getStatusCode(), $e->getHeaders());
    }
    /**
     * @param \Exception $e
     * @return bool
     */
    protected function isHttpException(Exception $e) {
        return $e instanceof HttpException;
    }
}