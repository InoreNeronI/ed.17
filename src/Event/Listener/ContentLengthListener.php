<?php

namespace App\Event\Listener;

use App\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ContentLengthListener.
 */
class ContentLengthListener implements EventSubscriberInterface
{
    /**
     * @param ResponseEvent $event
     */
    public function onResponse(ResponseEvent $event)
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
