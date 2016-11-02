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
     * @param array $fields
     * @param mixed $slug
     * @param array $params
     * @param mixed $table
     */
    public function checkCredentials(array $fields, $slug = LOGIN_SLUG, array $params = PARAMETERS, $table = USER_TABLE)
    {
        parent::__construct(null, null, null, null, null, $params);
        $periodo = $fields['fperiodo'];
        $cod_alumno = $fields['fcodalued'];
        $naci_dia_alumno = $fields['ffnacidia'];
        $naci_mes_alumno = $fields['ffnacimes'];
        $cod_prueba = $fields['fcodprueba'];

        dump($fields['fperiodo']);
        //dump($fields);
        $fields = static::parseFields($fields, static::getFilename($slug), $table);
dump($fields['fperiodo']);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('u.*')
            ->from(USER_TABLE, 'u')
            ->where('u.edg020_id_periodo = :periodo')
            ->andWhere('u.edg020_libro_escolaridad = :cod_alumno')
            ->andWhere('u.edg020_fec_dia = :naci_dia_alumno')
            ->andWhere('u.edg020_fec_mes = :naci_mes_alumno')
            ->andWhere('u.edg020_libro_escolaridad = :cod_prueba')
            ->setParameters(['periodo' => $periodo, 'cod_alumno' => $cod_alumno, 'naci_dia_alumno' => $naci_dia_alumno, 'naci_mes_alumno' => $naci_mes_alumno])/*
            ->leftJoin('u', 'phonenumbers', 'p', 'u.id = p.user_id')*/;
        if ($query = $queryBuilder->execute()) {
            dump($queryBuilder->getSQL());
            dump($query->fetch());
        }
        dump($fields);

        dump($_POST);
        /*$codigo = $fields['fcodalued'];
        $codigoOK = $this->select($table, '*', ["`$table.edg020_libro_escolaridad`" => "`$codigo`;"]);
        //$this->select($break_table);

        dump($repository);
        dump($codigoOK);*/
    }
}
