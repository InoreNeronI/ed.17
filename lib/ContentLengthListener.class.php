<?php
namespace App;

use App\ResponseEvent;

class ContentLengthListener
{
	/**
	 * Construct won't be called inside this class and is uncallable from the outside. This prevents instantiating this class.
	 * This is by purpose, because we want a static class.
	 *
	 * @url http://stackoverflow.com/a/11576945
	 */
	private function __construct() {}

	public function onResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();

        if ($response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $event->getRequest()->getRequestFormat()
        ) {
            return;
        }

        $response->setContent($response->getContent().'GA CODE');
    }
}