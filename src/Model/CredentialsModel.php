<?php

namespace App\Model;

/**
 * Class CredentialsModel.
 */
class CredentialsModel extends Connection\Connection
{
    /**
     * Model constructor.
     *
     * @param string|null $host
     * @param string|null $username
     * @param string|null $password
     * @param string|null $database
     * @param string|null $driver
     * @param array       $options
     */
    public function __construct($host = null, $username = null, $password = null, $database = null, $driver = null, array $options = [])
    {
        $params = empty($options) ? \def::dbCredentials() : $options;
        $host = empty($host) ? $params['database_host'] : $host;
        $username = empty($username) ? $params['database_user'] : $username;
        $password = empty($password) ? $params['database_password'] : $password;
        $database = empty($database) ? $params['database_name'] : $database;
        $driver = empty($driver) ? $params['database_driver'] : $driver;
        parent::__construct($host, $username, $password, $database, $driver, isset($params['database_port']) ? ['port' => $params['database_port']] : []);
    }

    /**
     * @param array  $args
     * @param string $table
     *
     * @return array
     *
     * @throws \Exception
     */
    public function checkCredentials(array $args, $table = USER_TABLE)
    {   //$fields = static::parseFields($args, static::getFilename($slug), $table);
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('u.*')->from($table, 'u')
            ->where('u.edg020_id_periodo = :periodo')
            ->andWhere('u.edg020_libro_escolaridad = :cod_alumno')
            ->andWhere('u.edg020_fec_dia = :naci_dia_alumno')
            ->andWhere('u.edg020_fec_mes = :naci_mes_alumno')
            ->setParameters([
                'periodo' => $args['fcurso'],
                'cod_alumno' => $args['fcodalued'],
                'naci_dia_alumno' => $args['ffnacidia'],
                'naci_mes_alumno' => $args['ffnacimes'],
            ]);
        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();
        /** @var array $student */
        $student = $query->fetch();
        if (!empty($student)) {
            /** @var array $codes */
            $codes = \def::dbSecurity();
            /** @var string $codPrueba */
            $codPrueba = $args['fcodprueba'];
            if (isset($codes[$codPrueba])) {
                return static::requesttAccess($student, $codes[$codPrueba]);
            } else {
                throw new \Exception(sprintf('The code you have entered does not match: \'%s\'', $codPrueba));
            }
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }

    /**
     * @param array $student
     * @param array $data
     *
     * @return array
     */
    private static function requesttAccess(array $student, array $data)
    {
        foreach (\def::periods() as $period) {
            if (strpos($data['table'], $period) !== false && empty($data['period'])) {
                $data['period'] = $period;
            }
        }

        return static::getAccess($student, $data);
    }

    /**
     * @param array $student
     * @param array $data
     *
     * @return array
     *
     * @throws \Exception
     */
    private static function getAccess(array $student, array $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $item) {
                /* Eusk: */
                if ((strpos($item, 'eus') !== false &&
                        (strtolower($key) === strtolower($student['edg020_tipo_eus']) || strpos(strtolower($key), lcfirst($student['edg020_codmodelo'])) !== false) &&
                        $mod = 'eus') ||
                    /* Gazte: */
                    (strpos($item, 'cas') !== false && lcfirst($key) === lcfirst($student['edg020_tipo_cas']) && $mod = 'cas') ||
                    /* G. sortak: */
                    (strpos($item, 'gsorta') !== false && lcfirst($key) === lcfirst($student['edg020_tipo_gso']) && $mod = 'gso') ||
                    /* Inge: */
                    (strpos($item, 'ing') !== false && lcfirst($key) === lcfirst($student['edg020_tipo_ing']) && $mod = 'ing') ||
                    /* Mate: */
                    (strpos($item, 'mat') !== false && lcfirst($key) === lcfirst($student['edg020_tipo_mat']) && $mod = 'mat') ||
                    /* Zie: */
                    (strpos($item, 'zie') !== false && lcfirst($key) === lcfirst($student['edg020_tipo_zie']) && $mod = 'zie')) {
                    return ['lengua' => $lengua = static::getLanguage($student, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $item];
                }
            }
            /* Eusk: */
        } elseif ((strpos($data, 'eus') !== false && $mod = 'eus') ||
            /* Gazte: */
            ((strpos($data, 'cas') !== false || strpos($data, 'gaz') !== false) && $mod = 'cas') ||
            /* G. sortak: */
            (strpos($data, 'gsorta') !== false && $mod = 'gso') ||
            /* Inge: */
            (strpos($data, 'ing') !== false && $mod = 'ing') ||
            /* Mate: */
            (strpos($data, 'mat') !== false && $mod = 'mat') ||
            /* Zie: */
            (strpos($data, 'zie') !== false && $mod = 'zie')) {
            return ['lengua' => $lengua = static::getLanguage($student, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $data];
        } else {
            throw new \Exception(sprintf('Access denied for student \'%s\'', $student['edg020_libro_escolaridad']));
        }
    }

    /**
     * @param array  $student
     * @param string $default
     * @param array  $asIs
     *
     * @return null|string
     *
     * @throws \Exception
     */
    private static function getLanguage($student, $default, $asIs = ['eus', 'cas'])
    {
        if ($student['edg020_lengua_tipo'] === 'fam') {
            return $student['edg020_lengua'];
        } elseif ($student['edg020_lengua_tipo'] === 'ins' && isset($student['edg020_lengua_' . $default])) {
            return $student['edg020_lengua_' . $default];
        }
        if (in_array($default, $asIs)) {
            return $default;
        } else {
            throw new \Exception(sprintf('No language found for student \'%s\'', $student['edg020_libro_escolaridad']));
        }
    }
}
