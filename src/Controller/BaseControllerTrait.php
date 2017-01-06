<?php

namespace App\Controller;

use App\Helper;
use App\Security;
use ReflectionClass;
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
     * @param array $credentials
     * @param array $options
     *
     * @return array
     */
    private function renderAuthorization(array $credentials = [], array $options = [])
    {
        empty($credentials) ? $credentials = $this->authorization : null;
        if (in_array('database_path', array_keys($credentials))) {
            $options['path'] = $credentials['database_path'];
            unset($credentials['database_path']);
        }
        if (in_array('database_port', array_keys($credentials))) {
            $options['port'] = $credentials['database_port'];
            unset($credentials['database_port']);
        }
        if (in_array('database_socket', array_keys($credentials))) {
            $options['unix_socket'] = $credentials['database_socket'];
            unset($credentials['database_socket']);
        }
        if (in_array('user_table', array_keys($credentials))) {
            $options['entity'] = $credentials['user_table'];
            unset($credentials['user_table']);
        }
        $this->authorization = array_merge($credentials, ['options' => $options]);
    }

    /**
     * @param HttpFoundation\Request $request
     * @param array                  $data
     *
     * @return array
     */
    private function prepareMessages(HttpFoundation\Request $request, $data)
    {
        $messages = isset($data['table']) ? array_merge($request->get('messages'), [
            'code' => $this->codes[$data['table']],
            'table' => $data['table'],
            'target' => $this->targets[$data['table']], ]) : $request->get('messages');
        $messages = Helper\TranslationsHelper::localize($messages, $data, $request->getLocale(), $this->langISOCodes);

        return array_merge($messages, ['metric' => $this->metric]);
    }

    /**
     * @param string $class
     * @param array $args
     *
     * @return object
     */
    private function getAuthManager($class, array $args = [])
    {
        $this->renderAuthorization();
        $rc = new ReflectionClass($class);
        //$args = array_values(empty($args) ? $this->authorization : $args);
        $args = empty($args) ? $this->authorization : $args;

        return $rc->newInstanceArgs($args);
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
            $this->renderAuthorization();
            $manager = new Security\Authorization(
                $this->authorization['database_host'],
                $this->authorization['database_user'],
                $this->authorization['database_password'],
                $this->authorization['database_name'],
                $this->authorization['database_driver'],
                isset($this->authorization['options']) ? $this->authorization['options'] : []);
            $data = $manager->checkCredentials($request->request->all());
        }

        return $this->prepareMessages($request, $data);
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
            $this->renderAuthorization();
            $manager = new Helper\PagesHelper(
                $this->authorization['database_host'],
                $this->authorization['database_user'],
                $this->authorization['database_password'],
                $this->authorization['database_name'],
                $this->authorization['database_driver'],
                isset($this->authorization['options']) ? $this->authorization['options'] : []);
            $messages = $this->prepareMessages($request, $request->request->all());

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
