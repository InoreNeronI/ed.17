<?php

namespace App\Controller;

use App\Template;
use Symfony\Component\HttpFoundation;

class Test
{
	public static function indexAction(HttpFoundation\Request $request)
	{
		return static::loginAction($request);
	}

	public static function loginAction(HttpFoundation\Request $request)
	{
		/** @var string $slug */
		$slug = $request->get('_route');
		/** @var array $parameters */
		$parameters = $request->get('parameters');
		/** @var string $content */
		$content = Template::render($slug, $parameters);
		/** @var HttpFoundation\Response $response */
		$response = new HttpFoundation\Response($content, 200);
		$response->headers->set('Content-Type', 'text/html');
		// configure the HTTP cache headers
//		$response->setMaxAge(10);
		// return response object back
		return $response;

	}

	public function viewDocumentAction(HttpFoundation\Request $request, $action, $documentId)
	{
		// ... Grab services and models and ... perform cool business logic
		$response = new HttpFoundation\Response();

		// return response object back to App Kernel
		return $response;
	}
	public function sampleAction(HttpFoundation\Request $request)
	{
		$response = new HttpFoundation\Response('sampleeeeeeeeeeee');

		return $response;
	}
}
