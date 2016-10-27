<?php
namespace App;

use App\ResponseEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpKernel;
use Symfony\Component\Routing as BaseRouting;

/**
 * Class Kernel
 * @package App
 */
class Kernel implements HttpKernel\HttpKernelInterface
{
	/** @var BaseRouting\Matcher\UrlMatcherInterface */
	private $matcher;

	/** @var HttpKernel\Controller\ControllerResolverInterface */
	private $resolver;

	/** @var EventDispatcher */
	private $dispatcher;

	// Injecting in our Url Matcher and Controller Resolver. Again this injected from my DI container. If you aren't using a DI container,
	// just pass the interface objects as arguments when you instantiate your App Kernel from inside your front controller.
	/**
	 * Kernel constructor.
	 *
	 * @param BaseRouting\Matcher\UrlMatcherInterface $matcher
	 * @param HttpKernel\Controller\ControllerResolverInterface $resolver
	 * @param EventDispatcher $dispatcher
	 */
	public function __construct (BaseRouting\Matcher\UrlMatcherInterface $matcher, HttpKernel\Controller\ControllerResolverInterface $resolver, EventDispatcher $dispatcher)
	{
		$this->matcher = $matcher;
		$this->resolver = $resolver;
		$this->dispatcher = $dispatcher;
	}

	// Our handle method takes an HTTP Request object, the Symfony Http Kernel so we have access to the master request, and a catch flag (we'll see why below)
	/**
	 * Handles a request.
	 *
	 * @param HttpFoundation\Request $request
	 * @param int $type
	 * @param bool $catch
	 *
	 * @return HttpFoundation\Response
	 */
	public function handle(HttpFoundation\Request $request, $type = HttpKernel\HttpKernelInterface::MASTER_REQUEST, $catch = true)
	{
        // Feed the RequestContext
        $this->matcher->getContext()->fromRequest($request);

		// Next we take our HTTP request object and see if our Request contains a routing match (see our routes class below for a match)
		try {
			$request->attributes->add($this->matcher->match($request->getPathInfo()));

			// Our request found a match so let's use the Controller Resolver to resolve our controller.
			/** @var callable|false $controller */
			$controller = $this->resolver->getController($request);

			// Pass our request arguments as an argument to our resolved controller (see controller below). If you have form data, the resolver's
			// 'getArguments' method's functionality will parse that data for you and then pass it as an array to your controller.
			/** @var array $arguments */
			$arguments = $this->resolver->getArguments($request, $controller);

			// Invoke the name of the controller that is resolved from a match in our routing class
			/** @var HttpFoundation\Response $response */
			$response = call_user_func_array($controller, $arguments);

		} catch (BaseRouting\Exception\ResourceNotFoundException $e) {
			// No such route exception return a 404 response
			$response = new HttpFoundation\Response(sprintf('Not Found: %s', $e->getMessage()), 404);

		} catch (\Exception $e) {
			// Something blew up exception return a 500 response
			$response = new HttpFoundation\Response(sprintf('An error occurred: %s', $e->getMessage()), 500);
		}

        // The dispatcher, the central object of the event dispatcher system, notifies listeners of an event dispatched to it.
        // Put another way: your code dispatches an event to the dispatcher, the dispatcher notifies all registered listeners for the event, and each listener do whatever it wants with the event.
        $this->dispatcher->dispatch('response', new ResponseEvent($response, $request));

        return $response;
	}
}
