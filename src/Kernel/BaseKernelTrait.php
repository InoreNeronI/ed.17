<?php

namespace App\Kernel;

use App\Controller;
use App\Event;
use Symfony\Component\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing;

/**
 * Trait BaseKernelTrait
 */
trait BaseKernelTrait
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
     * @param \Exception $exception
     * @param bool       $notice
     * @param string     $title
     *
     * @return HttpFoundation\Request
     */
    private static function prepareExceptionRequest(\Exception $exception, $notice = false, $title = 'Error')
    {
        if ($exception instanceof Routing\Exception\ResourceNotFoundException ||
            $exception instanceof Routing\Exception\MethodNotAllowedException) {
            $title = 'Resource not found';
            // No such route exception, return a 404 response
            $status = HttpFoundation\Response::HTTP_NOT_FOUND;
        } else {
            if ($exception->getCode() !== 0) {
                $title .= ' #'.$exception->getCode();
            }
            // Something blew up exception, return a 500 response
            $status = HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR;
        }
        static::$headers = array_merge(static::$headers, ['ErrorData' => [
            'debug' => static::$debug,
            'file' => $file = $exception->getFile(),
            'filename' => basename($file),
            'line' => $exception->getLine(),
            'message' => $exception->getMessage(),
            'status' => $status,
            'notice' => $notice,
            'title' => $title, ]]);

        return static::getRequest(HttpFoundation\Request::create(static::$baseUrl));
    }

    /**
     * @param HttpFoundation\Request $request
     *
     * @return HttpFoundation\Request
     */
    private static function getRequest(HttpFoundation\Request $request)
    {
        $route = $request->attributes->get('_route');
        $request->attributes->add(['_route' => is_null($route) ? static::$baseSlug : $route, 'messages' => static::getMessages()]);

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
     * @param \Exception $e
     * @param bool       $notice
     *
     * @return HttpFoundation\Response
     */
    private static function getFallbackResponse(\Exception $e, $notice = false)
    {
        $controller = new Controller\BaseController();
        $request = static::prepareExceptionRequest($e, $notice);
        $response = $controller->renderAction($request);

        return static::getResponse($response, HttpFoundation\Response::HTTP_SEE_OTHER);
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
        }

        return $response;
    }
}
