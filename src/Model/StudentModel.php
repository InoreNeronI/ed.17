<?php

namespace App\Model;

/**
 * Class Model.
 */
final class StudentModel extends Map
{
    /**
     * @param array  $form_fields
     * @param string $table
     *
     * @return array
     *
     * @throws \Exception
     */
    public function checkCredentials(array $form_fields, $table = USER_TABLE)
    {   //$fields = static::parseFields($form_fields, static::getFilename($slug), $table);
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
                             ->select('u.*')->from($table, 'u')
                             ->where('u.edg020_id_periodo = :periodo')
                             ->andWhere('u.edg020_libro_escolaridad = :cod_alumno')
                             ->andWhere('u.edg020_fec_dia = :naci_dia_alumno')
                             ->andWhere('u.edg020_fec_mes = :naci_mes_alumno')
                             ->setParameters([
                                 'periodo' => $form_fields['fcurso'],
                                 'cod_alumno' => $form_fields['fcodalued'],
                                 'naci_dia_alumno' => $form_fields['ffnacidia'],
                                 'naci_mes_alumno' => $form_fields['ffnacimes'],
                             ]);
        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();
        /** @var array $student */
        $student = $query->fetch();
        if (!empty($student)) {
            /** @var array $codes */
            $codes = \def::security();
            /** @var string $cod_prueba */
            $cod_prueba = $form_fields['fcodprueba'];
            if (isset($codes[$cod_prueba])) {
                /** @var array $access_data */
                $access_data = static::getAccess($student, $codes[$cod_prueba]);
                foreach (\def::periods() as $period) {
                    if (strpos($access_data['table'], $period) !== false) {
                        $access_data['period'] = $period;
                    }
                }

                return $access_data;
            } else {
                throw new \Exception(sprintf('The code you have entered does not match: \'%s\'', $cod_prueba));
            }
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }

    /**
     * @param array        $student
     * @param array|string $data
     *
     * @return array
     *
     * @throws \Exception
     */
    private static function getAccess(array $student, $data)
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

    /**
     * @param array  $form_fields
     * @param string $page
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $form_fields, $page)
    {
        return array_merge($form_fields,
            ['page' => $page, 'lang' => $form_fields['lang'], 'splits' => [
                'a' => $this->loadData($form_fields, $page . 'a'), 'b' => $this->loadData($form_fields, $page . 'b'), ]]);
    }

    /**
     * @param array  $form_fields
     * @param string $wildcard
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadData(array $form_fields, $wildcard)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('t.edg051_codprueba as id', 't.edg051_cod_texto as text_code', 'TRIM(t.edg051_texto_' . $form_fields['flengua'] . ') as text_string')
            ->from($form_fields['ftestuak'], 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $form_fields['fcurso'], 'text_code' => 'p' . $wildcard . '%']);

        /** @var array $student */
        $texts = $queryBuilder->execute()->fetchAll();
        if (!empty($texts)) {
            return $texts;
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }
}
