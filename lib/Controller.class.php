<?php

namespace App;

use App\Repository;
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
		if ($request->getMethod() === 'POST')
			static::postActionHook($request);

		/** @var string $slug */
		$slug = $request->get('_route');

		/** @var array $parameters */
		$parameters = $request->get('parameters');

		/** @var HttpFoundation\Response $response */
		$response = new HttpFoundation\Response(View::render($slug, $parameters), 200);

        // Avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
		$response->headers->set('Content-Type', 'text/html');

		// configure the HTTP cache headers
		//$response->setMaxAge(10);

		// return response object back
		return $response;
	}

	/**
	 * @param HttpFoundation\Request $request
	 *
	 * @return HttpFoundation\Response
	 */
	public static function postActionHook(HttpFoundation\Request $request)
	{
		$postData = $request->request->all();
		$manager = new Repository;
		$manager->checkCredentials($postData);
		/*ladybug_dump($postData);
		ladybug_dump($manager);*/
	}

}
