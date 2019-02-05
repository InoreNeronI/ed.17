<?php

namespace Security;

use Security;

/**
 * Class Authorization.
 */
class Authorization extends Security\Connection\Connection
{
    /** @var string $current */
    protected static $current = 'dist';

    /**
     * @param string $current
     *
     * @return array
     *
     * @throws \NoticeException
     */
    public static function setCurrent($current)
    {
        static::$current = $current;
    }

    /**
     * @param array  $args
     * @param string $dbName
     * @param string $userTable
     *
     * @return array
     *
     * @throws \NoticeException
     */
    public function checkCredentials(array $args, $dbName, $userTable)
    {
        return call_user_func(__METHOD__.static::$current, $args, $dbName, $userTable);
    }

    /**
     * @param array  $args
     * @param string $dbName
     * @param string $userTable
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private function checkCredentialsDist(array $args, $dbName, $userTable)
    {   //$fields = static::parseFields($args, static::getFilename($slug), $table);
        /** @var array $credentials */
        $credentials = [
            'course' => $args['course'],
            'studentCode' => $args['studentCode'],
            'studentDay' => $args['studentDay'],
            'studentMonth' => $args['studentMonth'],
        ];
        if ($this->checkUploaders($args)) {
            return array_merge($args, ['is_admin' => 'ed17-100' === strtolower($args['studentCode'])]);
        }
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('u.*')->from($userTable, 'u')
            ->where('u.periodo = :course')
            ->andWhere('u.codalumno = :studentCode')
            ->andWhere('u.fec_dia = :studentDay')
            ->andWhere('u.fec_mes = :studentMonth')
            ->setParameters($credentials);

        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();

        /** @var array $user */
        $user = $query->fetch();

        if (!empty($user)) {
            /** @var array $pws */
            $pws = \def::dbCredentials()[$dbName];
            /** @var string $codPrueba */
            $codPrueba = $args['studentPassword'];
            if (isset($pws[$codPrueba])) {
                return array_merge(['dbname' => $dbName], static::requestAccess($user, $pws[$codPrueba]), $credentials);
            }
            throw new \NoticeException(sprintf('The code you have entered does not match: \'%s\'', $codPrueba));
        }
        throw new \NoticeException(static::$debug ? sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())) : 'No results found');
    }

    /**
     * @param array $args
     *
     * @return bool|int|string
     */
    public function checkUploaders(array $args)
    {
        foreach (\def::dbUploaders() as $user => $password) {
            if (strtolower($args['studentCode']) === $user && $args['studentPassword'] === $password) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array  $args
     * @param string $table
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private function checkCredentialsLocal(array $args, $table)
    {
    }

    /**
     * @param array        $user
     * @param array|string $args
     *
     * @return array
     */
    private static function requestAccess(array $user, $args)
    {
        $data = static::getAccess($user, $args);
        foreach (\def::stages() as $stage) {
            if (false !== strpos($data['table'], $stage)/* && empty($data['stage'])*/) {
                $data['stage'] = $stage;
                break;
            }
        }

        return $data;
    }

    /**
     * @param array        $user
     * @param array|string $args
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private static function getAccess(array $user, $args)
    {
        if (is_array($args)) {
            foreach ($args as $key => $item) {
                if (/* Eusk: */(false !== strpos($item, 'eus') &&
                        (strtolower($key) === strtolower($user['tipo_eus']) || false !== strpos(strtolower($key), lcfirst($user['codmodelo']))) &&
                        $mod = 'eus') ||
                    /* Gazte: */
                    ((false !== strpos($item, 'cas') || false !== strpos($item, 'gaz')) /*&& lcfirst($key) === lcfirst($user['tipo_cas'])*/ && $mod = 'cas') ||
                    /* G. sortak: */
                    (false !== strpos($item, 'gsorta') && lcfirst($key) === lcfirst($user['tipo_gso']) && $mod = 'gso') ||
                    /* Inge: */
                    (false !== strpos($item, 'ing') /*&& lcfirst($key) === lcfirst($user['tipo_ing'])*/ && $mod = 'ing') ||
                    /* Mate: */
                    (false !== strpos($item, 'mat') /*&& lcfirst($key) === lcfirst($user['tipo_mat'])*/ && $mod = 'mat') ||
                    /* Zie: */
                    (false !== strpos($item, 'zie') /*&& lcfirst($key) === lcfirst($user['tipo_zie'])*/ && $mod = 'zie')) {
                    return ['lengua' => $lengua = static::getLanguage($user, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $item];
                }
            }
        } elseif (/* Eusk: */(false !== strpos($args, 'eus') && $mod = 'eus') ||
            /* Gazte: */
            ((false !== strpos($args, 'cas') || false !== strpos($args, 'gaz')) && $mod = 'cas') ||
            /* G. sortak: */
            (false !== strpos($args, 'gsorta') && $mod = 'gso') ||
            /* Inge: */
            (false !== strpos($args, 'ing') && $mod = 'ing') ||
            /* Mate: */
            (false !== strpos($args, 'mat') && $mod = 'mat') ||
            /* Zie: */
            (false !== strpos($args, 'zie') && $mod = 'zie') ||
            /* Simul: */
            (false !== strpos($args, 'simul') && $mod = 'mat')) {
            return ['lengua' => $lengua = static::getLanguage($user, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $args];
        } else {
            throw new \NoticeException(sprintf('Access denied for student \'%s\'', $user['codalumno']));
        }
    }

    /**
     * @param array  $user
     * @param string $default
     * @param array  $asIs
     *
     * @return string|null
     *
     * @throws \NoticeException
     */
    private static function getLanguage($user, $default, $asIs = ['eus', 'cas'])
    {
        if ('fam' === $user['lengua_tipo']) {
            return $user['lengua'];
        }
        if ('ins' === $user['lengua_tipo'] && isset($user['lengua_'.$default])) {
            return $user['lengua_'.$default];
        }
        if (in_array($default, $asIs)) {
            return $default;
        }
        throw new \NoticeException(sprintf('No language found for student \'%s\'', $user['codalumno']));
    }
}
