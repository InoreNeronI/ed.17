<?php

namespace App\Controller;

use App\Helper;
use App\Security;
use Symfony\Component\HttpFoundation;

/**
 * Trait BaseControllerTrait.
 */
trait BaseControllerTrait
{
    /* @var array authorization */
    private $authorization = [];
    /* @var array codes */
    private $codes;
    /* @var array langISOCodes */
    private $langISOCodes;
    /* @var array metric */
    private $metric;
    /* @var array targets */
    private $targets;

    /**
     * @return array
     */
    private function renderArguments()
    {
        return array_merge($this->authorization, [
            'codes' => $this->codes,
            'langISOCodes' => $this->langISOCodes,
            'metric' => $this->metric,
            'targets' => $this->targets,
        ]);
    }

    /**
     * Generates a response from the given request object.
     *
     * @param string $content
     * @param int    $expiryMinutes
     *
     * @return HttpFoundation\Response
     */
    private static function processRender($content = 'Hello World!', $expiryMinutes = 1)
    {
        /** @var HttpFoundation\Response $response */
        $response = new HttpFoundation\Response($content, 200);

        // avoid one of the most widespread Internet security issue, XSS (Cross-Site Scripting)
        $response->headers->set('Content-Type', 'text/html');

        // configure the HTTP cache headers
        $response->setMaxAge($expiryMinutes * 60);

        // return response object back
        return $response;
    }

    /**
     * @param HttpFoundation\Request $request
     * @param array                  $options
     * @param $data
     *
     * @return array
     */
    private static function prepareMessages(HttpFoundation\Request $request, array $options, $data)
    {
        $messages = Helper\TranslationsHelper::localize($request->get('messages'), $data, $request->getLocale(), $options['langISOCodes']);

        return isset($data['table']) ? array_merge($messages, [
            'code' => $options['codes'][$data['table']],
            'metric' => $options['metric'],
            'target' => $options['targets'][$data['table']], ]) : array_merge($messages, ['metric' => $options['metric']]);
    }

    /**
     * @param HttpFoundation\Request $request
     * @param array                  $options
     *
     * @return array
     */
    private function getRender(HttpFoundation\Request $request, array $options)
    {
        $data = [];
        if ($request->getMethod() === 'POST') {
            $manager = new Security\Authorization(
                $options['database_host'],
                $options['database_user'],
                $options['database_password'],
                $options['database_name'],
                $options['database_driver'],
                isset($options['options']) ? $options['options'] : []);
            $data = $manager->checkCredentials($request->request->all());
        }

        return static::prepareMessages($request, $options, $data);
    }

    /**
     * @param HttpFoundation\Request $request
     * @param string                 $page
     * @param array                  $options
     *
     * @return array
     */
    private function getSplitPageRender(HttpFoundation\Request $request, $page, array $options)
    {
        if ($request->getMethod() === 'POST') {
            $manager = new Helper\PagesHelper(
                $options['database_host'],
                $options['database_user'],
                $options['database_password'],
                $options['database_name'],
                $options['database_driver'],
                isset($options['options']) ? $options['options'] : []);
            $data = $request->request->all();
            $messages = $this->prepareMessages($request, $options, $data);

            return $manager->loadPageData($messages, sprintf('%02d', intval($page)));
        }

        return [];
    }

    /**
     * @param HttpFoundation\Session\Session $session
     * @param string                         $msg
     *
     * @return array
     */
    private static function getFlashMessages(HttpFoundation\Session\Session $session, $msg)
    {
        $session->isStarted() ?: $session->start();
        // add flash messages
        if (strpos($msg, 'no connection')) {
            return $session->getFlashBag()->add(
                'error',
                'Data connection error'
            );
        } elseif (strpos($msg, 'no result')) {
            return $session->getFlashBag()->add(
                'error',
                'Data query error'
            );
        }

        return $session->getFlashBag()->add(
            'warning',
            ucfirst($msg)
        );
    }
}
