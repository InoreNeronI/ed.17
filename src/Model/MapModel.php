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
        parent::__construct($params);
        $fields = static::parseFields($fields, static::getFilename($slug), $table);
        dump($fields);
        /*$codigo = $fields['fcodalued'];
        $codigoOK = $this->select($table, '*', ["`$table.edg020_libro_escolaridad`" => "`$codigo`;"]);
        //$this->select($break_table);

        dump($repository);
        dump($codigoOK);*/
    }
}
