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
    /* @var array codes */
    private $codes;
    /* @var array langISOCodes */
    private $langISOCodes;
    /* @var array metric */
    private $metric;
    /* @var array targets */
    private $targets;

    /**
     * @param string $class
     * @param array  $authorization
     * @param array  $options
     *
     * @return Security\Authorization|Helper\PagesHelper
     */
    public static function getAuthManager($class, array $authorization, array $options = [])
    {
        if (in_array('path', array_keys($authorization))) {
            $options['path'] = $authorization['path'];
            unset($authorization['path']);
        }
        if (in_array('port', array_keys($authorization))) {
            $options['port'] = $authorization['port'];
            unset($authorization['port']);
        }
        if (in_array('unix_socket', array_keys($authorization))) {
            $options['unix_socket'] = $authorization['unix_socket'];
            unset($authorization['unix_socket']);
        }
        /** @var Security\Authorization|Helper\PagesHelper $manager */
        $manager = new $class(
            isset($authorization['host']) ? $authorization['host'] : null,
            isset($authorization['user']) ? $authorization['user'] : null,
            isset($authorization['password']) ? $authorization['password'] : null,
            isset($authorization['dbname']) ? $authorization['dbname'] : null,
            $authorization['driver'],
            isset($authorization['options']) ? array_merge($authorization['options'], $options) : $options);

        $manager::setCurrent(static::isAdmin($authorization) ? 'local' : 'dist');

        return $manager;
    }

    /**
     * @param HttpFoundation\Request $request
     *
     * @return array
     */
    private function getData(HttpFoundation\Request $request)
    {
        $data = [];
        if ($request->getMethod() === 'POST') {
            /** @var array $args */
            $args = $request->request->all();
            /** @var Security\Authorization $manager */
            $manager = $this->getAuthManager('App\Security\Authorization', static::authorize($args));
            /** @var array $data */
            $data = $manager->checkCredentials($args);
        }

        return $this->localizeMessages($request, $data);
    }

    /**
     * @param HttpFoundation\Request $request
     * @param string                 $page
     *
     * @return array
     */
    private function getSplitPageData(HttpFoundation\Request $request, $page)
    {
        if ($request->getMethod() === 'POST') {
            /** @var array $args */
            $args = $request->request->all();

            /** @var Helper\PagesHelper $manager */
            $manager = $this->getAuthManager('App\Helper\PagesHelper', static::authorize($args));
            $manager->saveData($args);

            /** @var array $messages */
            $messages = $this->localizeMessages($request, $args);

            return $manager->loadPageData($messages, sprintf('%02d', intval($page)));
        }

        return [];
    }

    /**
     * @param HttpFoundation\Request $request
     * @param array                  $data
     *
     * @return array
     */
    private function localizeMessages(HttpFoundation\Request $request, $data = [])
    {
        $messages = Helper\TranslationsHelper::localize($request->get('messages'), $data, $request->getLocale(), $this->langISOCodes);
        dump($messages);
        //dump($data);
        if (($session = $request->getSession()) && $session->isStarted() && $session->has('ErrorData')) {
            $messages = array_merge(['ErrorData' => $session->get('ErrorData')], $messages);
        }

        return array_merge(isset($data['table']) ? [
            'code' => $this->codes[$data['table']],
            //'origin' => $data['table'],
            'target' => $this->targets[$data['table']], ] : [], $messages, $data, ['metric' => $this->metric]);
    }
}
