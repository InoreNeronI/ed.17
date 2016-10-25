<?php

namespace App;

use App\View;
use Symfony\Component\HttpFoundation;

/**
 * Class Controller
 * @package App
 */
class Controller
{
	/**
	 * Generates a response from the given request object.
	 *
	 * @param HttpFoundation\Request $request
	 *
	 * @return HttpFoundation\Response
	 */
	public static function action(HttpFoundation\Request $request)
	{
		/** @var string $slug */
		$slug = $request->get('_route');
		/** @var array $parameters */
		$parameters = $request->get('parameters');
		/** @var HttpFoundation\Response $response */
		$response = new HttpFoundation\Response(View::render($slug, $parameters), 200);
		$response->headers->set('Content-Type', 'text/html');
		// configure the HTTP cache headers
//		$response->setMaxAge(10);
		// return response object back
		return $response;
	}
}
