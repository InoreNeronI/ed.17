<?php

namespace Event\Listener;

use Event;
use Symfony\Component\EventDispatcher;

/**
 * Class ContentLengthListener.
 */
class ContentLengthListener implements EventDispatcher\EventSubscriberInterface
{
    /**
     * @param Event\ResponseEvent $event
     */
    public function onResponse(Event\ResponseEvent $event)
    {
        $response = $event->getResponse();
        $headers = $response->headers;

        if (!$headers->has('Content-Length') && !$headers->has('Transfer-Encoding')) {
            $headers->set('Content-Length', strlen($response->getContent()));
        }
    }

    /**
     * A single subscriber can host as many listeners as you want on as many events as needed.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return ['response' => ['onResponse', -255]];
    }
}
