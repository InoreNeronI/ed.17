<?php

namespace App\Model;

use App\Model;

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
     * @param mixed $slug
     * @param array $params
     * @param mixed $table
     */
    public function checkCredentials(array $form_fields, $slug = LOGIN_SLUG, array $params = [], $table = USER_TABLE)
    {
	    $params = empty($params) ? \def::parameters() : $params;
        parent::__construct(null, null, null, null, null, $params);

	    //$fields = static::parseFields($form_fields, static::getFilename($slug), $table);
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
        if ($result = $queryBuilder->execute()->fetchAll()) {
	        echo sprintf('%s registro%s encontrado%s', $total = count($result), $plural = $total > 1 ? 's' : '', $plural);
            dump($result);

        }

	    $cod_prueba = $form_fields['fcodprueba'];

        //dump($_POST);
        /*$codigo = $fields['fcodalued'];
        $codigoOK = $this->select($table, '*', ["`$table.edg020_libro_escolaridad`" => "`$codigo`;"]);

        */
    }
}
