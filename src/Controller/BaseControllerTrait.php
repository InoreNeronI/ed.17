<?php

namespace App\Controller;

use App\Helper;
use Symfony\Component\HttpFoundation;

/**
 * Trait BaseControllerTrait.
 */
trait BaseControllerTrait
{
    /* @var array authorization */
    private $authorization;
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
     * @param string $method
     * @param array  $args
     * @param array  $options
     *
     * @return object
     */
    private function getAuthManager($class, $method = null, array $args = [], array $options = [])
    {
        if (empty($args)) {
            $args = $this->authorization;
        }
        if (in_array('database_path', array_keys($args))) {
            $options['path'] = $args['database_path'];
            unset($args['database_path']);
        }
        if (in_array('database_port', array_keys($args))) {
            $options['port'] = $args['database_port'];
            unset($args['database_port']);
        }
        if (in_array('database_socket', array_keys($args))) {
            $options['unix_socket'] = $args['database_socket'];
            unset($args['database_socket']);
        }
        if (in_array('user_table', array_keys($args))) {
            $options['entity'] = $args['user_table'];
            unset($args['user_table']);
        }
        $this->authorization = array_merge($args, empty($options) ? [] : ['options' => $options]);

        $object = new $class(
            $this->authorization['database_host'],
            $this->authorization['database_user'],
            $this->authorization['database_password'],
            $this->authorization['database_name'],
            $this->authorization['database_driver'],
            isset($this->authorization['options']) ? $this->authorization['options'] : []);

        return is_null($method) ? $object : new \ReflectionMethod($object, $method);
    }

    /**
     * @param HttpFoundation\Request $request
     *
     * @return array
     */
    private function getRender(HttpFoundation\Request $request)
    {
        $data = [];
        if ($request->getMethod() === 'POST') {
            $manager = $this->getAuthManager('App\Security\Authorization');
            $data = $manager->checkCredentials($request->request->all());
        }

        return $this->localizeMessages($request, $data);
    }

    /**
     * @param HttpFoundation\Request $request
     * @param string                 $page
     *
     * @return array
     */
    private function getSplitPageRender(HttpFoundation\Request $request, $page)
    {
        if ($request->getMethod() === 'POST') {
            $manager = $this->getAuthManager('App\Helper\PagesHelper');
            $messages = $this->localizeMessages($request, $request->request->all());

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
    private function localizeMessages(HttpFoundation\Request $request, $data)
    {
        $messages = isset($data['table']) ? array_merge($request->get('messages'), [
            'code' => $this->codes[$data['table']],
            'origin' => $data['table'],
            'target' => $this->targets[$data['table']], ]) : $request->get('messages');
        $messages = Helper\TranslationsHelper::localize($messages, $data, $request->getLocale(), $this->langISOCodes);

        return array_merge($messages, ['metric' => $this->metric]);
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
