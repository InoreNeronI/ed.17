<?php

namespace Kernel;

use Controller;
use Event;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

/**
 * Trait KernelTrait
 */
trait KernelTrait
{
    /** @var string $baseSlug */
    private static $baseSlug = 'index';

    /** @var string $baseUrl */
    private static $baseUrl = '/';

    /** @var bool */
    private static $debug;

    /** @var EventDispatcher\EventDispatcher */
    private $dispatcher;

    /** @var array $headers */
    private static $headers = [];

    /** @var Routing\Matcher\UrlMatcherInterface */
    private $matcher;

    /** @var HttpKernel\Controller\ControllerResolverInterface */
    private $resolver;

    /**
     * @param HttpFoundation\Request $request
     *
     * @return HttpFoundation\Request
     */
    private static function getRequest(HttpFoundation\Request $request)
    {
        $slug = $request->attributes->get('_route');
        $request->attributes->add(['_route' => is_null($slug) ? static::$baseSlug : $slug, 'messages' => static::getMessages($slug)]);

        return $request;
    }

    /**
     * @param HttpFoundation\Response $response
     * @param int                     $status
     *
     * @return HttpFoundation\Response
     */
    private static function getResponse(HttpFoundation\Response $response, $status = HttpFoundation\Response::HTTP_OK)
    {
        // Add previously defined headers to the response object
        //$response->headers->add(static::$headers);
        $response->setStatusCode($status);

        return $response;
    }

    /**
     * Handles a request.
     * Our handle method takes an HTTP Request object, the Symfony Http Kernel so we have access to the master request, and a catch flag (we'll see why below).
     *
     * @param HttpFoundation\Request $request
     * @param int                    $type
     * @param bool                   $catch
     *
     * @return HttpFoundation\Response|HttpFoundation\RedirectResponse
     */
    public function handle(HttpFoundation\Request $request, $type = HttpKernel\HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->dispatcher->addSubscriber(new HttpKernel\EventListener\RouterListener($this->matcher, new HttpFoundation\RequestStack()));

        // Feed the RequestContext
        $this->matcher->getContext()->fromRequest($request);

        try {
            // Next we take our HTTP request object and see if our Request contains a routing match (see our routes class below for a match)
            /** @var array $parameters */
            $parameters = $this->matcher->match($request->getPathInfo());
            $request->attributes->add($parameters);

            // Our request found a match so let's use the Controller Resolver to resolve our controller.
            /** @var callable|false $controller */
            $controller = $this->resolver->getController($request);

            // Pass our request arguments as an argument to our resolved controller (see controller below). If you have form data, the resolver's
            // 'getArguments' method's functionality will parse that data for you and then pass it as an array to your controller.
            /** @var array $arguments */
            $arguments = $this->resolver->getArguments($request, $controller);

            // Invoke the name of the controller that is resolved from a match in our routing class
            /** @var HttpFoundation\Response $response */
            $response = static::getResponse(call_user_func_array($controller, $arguments));

            // The dispatcher, the central object of the event dispatcher system, notifies listeners of an event dispatched to it.
            // Put another way: your code dispatches an event to the dispatcher, the dispatcher notifies all registered listeners for the event, and each listener do whatever it wants with the event.
            $this->dispatcher->dispatch('response', new Event\ResponseEvent($response, $request));
        } catch (\NoticeException $e) {
            return static::getFallbackResponse($e, true);
        } catch (\WarningException $e) {
            return static::getFallbackResponse($e, true);
        } catch (\Exception $e) {
            return static::getFallbackResponse($e);
        } catch (\Throwable $e) {
            return static::getFallbackResponse($e);
        }

        return $response;
    }

    /**
     * @link https://github.com/symfony/symfony/blob/3.2/src/Symfony/Component/Debug/Exception/FatalThrowableError.php
     *
     * @param \Throwable $e
     * @param bool       $notice
     *
     * @return HttpFoundation\Response
     */
    private static function getFallbackResponse($e, $notice = false)
    {
        if (class_exists('ParseError') && $e instanceof \ParseError) {
            $title = 'Parse error';
            $severity = E_PARSE;
        } elseif (class_exists('TypeError') && $e instanceof \TypeError) {
            $title = 'Type error';
            $severity = E_RECOVERABLE_ERROR;
        // No such route exception, return a 404 response
        } elseif ($e instanceof Routing\Exception\ResourceNotFoundException || $e instanceof Routing\Exception\MethodNotAllowedException) {
            $title = 'Resource not found';
            $severity = HttpFoundation\Response::HTTP_NOT_FOUND;
        } else {
            $title = 'Error';
            $severity = E_ERROR;
        }

        $request = static::prepareExceptionRequest($e->getCode(), $e->getFile(), $e->getLine(), $e->getMessage(), $notice, $title, $severity);
        $response = (new Controller\ControllerBase())->renderAction($request);

        return static::getResponse($response, HttpFoundation\Response::HTTP_SEE_OTHER);
    }

    /**
     * @param int           $code
     * @param string        $file
     * @param string        $line
     * @param string        $message
     * @param string|bool   $notice
     * @param string        $title
     * @param int|bool      $status
     *
     * @return HttpFoundation\Request
     */
    private static function prepareExceptionRequest($code, $file, $line, $message, $notice = false, $title = 'Error', $status = null)
    {
        if ($code && $code !== 0) {
            $title .= ' #'.$code;
        }
        if (!$status) {
            // Something blew up exception, return a 500 response
            $status = HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR;
        }
        static::$headers = array_merge(static::$headers, ['ErrorData' => [
            'debug' => static::$debug,
            'file' => $file,
            'filename' => basename($file),
            'line' => $line,
            'message' => $message,
            'status' => $status,
            'notice' => $notice,
            'title' => $title, ]]);

        return static::getRequest(HttpFoundation\Request::create(static::$baseUrl));
    }
}
