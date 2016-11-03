<?php

namespace App\Model;

/**
 * Class Model.
 */
final class StudentModel extends Map
{
    /**
     * @param array $form_fields
     * @param array $params
     * @param $table
     *
     * @return string
     *
     * @throws \Exception
     */
    public function checkCredentials(array $form_fields, array $params = [], $table = USER_TABLE/*, $slug = LOGIN_SLUG*/)
    {
        //$fields = static::parseFields($form_fields, static::getFilename($slug), $table);
        $params = empty($params) ? \def::parameters() : $params;
        parent::__construct(null, null, null, null, null, $params);

        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()->select('u.*')->from($table, 'u')
                             ->where('u.edg020_id_periodo = :periodo')
                             ->andWhere('u.edg020_libro_escolaridad = :cod_alumno')
                             ->andWhere('u.edg020_fec_dia = :naci_dia_alumno')
                             ->andWhere('u.edg020_fec_mes = :naci_mes_alumno')
                             ->setParameters([
                                 'periodo' => $form_fields['fperiodo'],
                                 'cod_alumno' => $form_fields['fcodalued'],
                                 'naci_dia_alumno' => $form_fields['ffnacidia'],
                                 'naci_mes_alumno' => $form_fields['ffnacimes'],
                             ]);
        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();
        /** @var array $result */
        $result = $query->fetchAll();
        /** @var int $total */
        $total = count($result);
        if ($total > 0) {
            if ($total === 1) {
                $codes = static::parseYamlFile('probak/code');
                $cod_prueba = $form_fields['fcodprueba'];
                if (isset($codes[$cod_prueba])) {
                    return static::getAccess($result[0], $codes[$cod_prueba]);
                } else {
                    throw new \Exception(sprintf('The code you have entered does not match: \'%s\'', $cod_prueba));
                }
            } else {
                throw new \Exception(sprintf('More than unique result returns the query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
            }
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }

    /**
     * @param $student
     * @param $data
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getAccess($student, $data)
    {
        if (is_array($data) && count($data) > 1) {
            foreach ($data as $key => $value) {
                if (// Eusk:
                    (strpos($value, 'eus') !== false &&
                     (strtolower($key) === strtolower($student['edg020_tipo_eus']) || strpos(strtolower($key), lcfirst($student['edg020_codmodelo'])) !== false) &&
                     $lang = static::getLanguage($student, 'eus')) ||    // Gazte:
                    /*(strpos($value, 'cas') !== false &&
                     lcfirst($key) === lcfirst($student['edg020_tipo_cas']) &&
                     $lang = static::getLanguage($student, 'cas')) ||*/  // G. sortak:
                    (strpos($value, 'gsorta') !== false &&
                     lcfirst($key) === lcfirst($student['edg020_tipo_gso']) &&
                     $lang = static::getLanguage($student, 'gso')) ||    // Inge:
                    (strpos($value, 'ing') !== false &&
                     lcfirst($key) === lcfirst($student['edg020_tipo_ing']) &&
                     $lang = static::getLanguage($student, 'ing')) ||    // Mate:
                    /*(strpos($value, 'mat') !== false &&
                     lcfirst($key) === lcfirst($student['edg020_tipo_mat']) &&
                     $lang = static::getLanguage($student, 'mat')) ||*/  // Zie:
                    (strpos($value, 'zie') !== false &&
                     lcfirst($key) === lcfirst($student['edg020_tipo_zie']) &&
                     $lang = static::getLanguage($student, 'zie'))) {
                    return ['edg051_texto_'.$lang, $value];
                }
            }
        } elseif (
            (strpos($data, 'eus') !== false && $lang = static::getLanguage($student, 'eus')) ||
            ((strpos($data, 'cas') !== false || strpos($data, 'gaz') !== false) && $lang = static::getLanguage($student, 'cas')) ||
            (strpos($data, 'gsorta') !== false && $lang = static::getLanguage($student, 'gso')) ||
            (strpos($data, 'ing') !== false && $lang = static::getLanguage($student, 'ing')) ||
            (strpos($data, 'mat') !== false && $lang = static::getLanguage($student, 'mat')) ||
            (strpos($data, 'zie') !== false && $lang = static::getLanguage($student, 'zie'))) {
            return ['edg051_texto_'.$lang, $data];
        } else {
            throw new \Exception(sprintf('Access denied for student \'%s\'', $student['edg020_libro_escolaridad']));
        }
    }

    /**
     * @param $student
     * @param $ins_suffix
     * @param string $checkIfSkipped
     * @param string $replaceIfSkipped
     * @param array  $asIs
     *
     * @return null|string
     *
     * @throws \Exception
     */
    private static function getLanguage($student, $ins_suffix, $checkIfSkipped = 'eus', $replaceIfSkipped = 'cas', $asIs = ['eus', 'cas'])
    {
        $lang = null;

        if ($student['edg020_lengua_tipo'] === 'fam') {
            $lang = $student['edg020_lengua'];
        } elseif ($student['edg020_lengua_tipo'] === 'ins' && isset($student['edg020_lengua_'.$ins_suffix])) {
            $lang = $student['edg020_lengua_'.$ins_suffix];
        }

        if ($lang === $checkIfSkipped && $student['edg020_exento_'.$checkIfSkipped] === 1) {
            $lang = $replaceIfSkipped;
        }

        if (is_null($lang) && in_array($ins_suffix, $asIs)) {
            $lang = $ins_suffix;
        } elseif (is_null($lang)) {
            throw new \Exception(sprintf('No language found for student \'%s\'', $student['edg020_libro_escolaridad']));
        }

        return $lang;
    }
}
