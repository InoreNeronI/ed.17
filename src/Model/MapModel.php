<?php

namespace App\Model;

use App\Model;
use App\View;

/**
 * Class Model.
 */
final class MapModel extends Model
{
    /**
     * @param string $slug
     * @param string $dir
     *
     * @return string
     */
    private static function getFilename($slug, $dir = CONFIG_DIR)
    {
        return "$dir/map/$slug.yml";
    }

    /**
     * @param array $form_fields
     * @param array $params
     * @param $table
     *
     * @return string
     * @throws \Exception
     */
    public function checkCredentials(array $form_fields, array $params = [], $table = USER_TABLE/*, $slug = LOGIN_SLUG*/)
    {
	    $params = empty($params) ? \def::parameters() : $params;
        parent::__construct(null, null, null, null, null, $params);

	    //$fields = static::parseFields($form_fields, static::getFilename($slug), $table);
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this
	        ->getQueryBuilder()
	        ->select('u.*')
            ->from($table, 'u')
            ->where('u.edg020_id_periodo = :periodo')
            ->andWhere('u.edg020_libro_escolaridad = :cod_alumno')
            ->andWhere('u.edg020_fec_dia = :naci_dia_alumno')
            ->andWhere('u.edg020_fec_mes = :naci_mes_alumno')
            ->setParameters([
            	'periodo' => $form_fields['fperiodo'],
	            'cod_alumno' => $form_fields['fcodalued'],
	            'naci_dia_alumno' => $form_fields['ffnacidia'],
	            'naci_mes_alumno' => $form_fields['ffnacimes']
            ])/*
            ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id')*/;
        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();
        /** @var array $result */
        $result = $query->fetchAll();
        if (count($result) > 0) {
            if (count($result) === 1) {
                $alumno = $result[0];
                /////$lengua = $alumno['edg020_lengua_tipo'] === 'fam' ? $alumno['edg020_lengua'] : null;
                $cod_prueba = $form_fields['fcodprueba'];
                $codes = parseConfig(CONFIG_DIR, 'map/probak_codes');
                $proba = null;
                if (isset($codes[$cod_prueba])) {
                    $testuak_proba = $codes[$cod_prueba];
                    if (is_array($testuak_proba) && count($testuak_proba) > 1) {
                        foreach($testuak_proba as /*$key => */$value) {
                            if (isset($value[lcfirst($alumno['edg020_codmodelo'])])) {
                                return $value[lcfirst($alumno['edg020_codmodelo'])];
                            }
                        }
                    } else {
                        return $testuak_proba;
                    }
                } else {
                    throw new \Exception(sprintf('The code you have entered does not match: %s', $cod_prueba));
                }
            } else {
                throw new \Exception(sprintf('More than a unique result found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
            }
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }
}
