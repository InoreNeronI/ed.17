<?php

namespace App\Helper;

use App\Helper;
use App\Security;
use Doctrine\DBAL;

/**
 * Class PagesHelper.
 */
final class PagesHelper extends Security\Authorization
{
    /** @var array */
    private static $pageAreaPercents = ['a' => '50', 'b' => '50'];

    /** @var array */
    private static $pageTexts = ['code' => '', 'numbers' => [], 'texts' => []];

    /**
     * @param string $lang
     * @param string $table
     * @param int    $course
     * @param array  $config
     * @param string $page
     *
     * @return array
     */
    private function loadTexts($lang, $table, $course, array $config, $page)
    {
        // Sides' width
        $pageAreaWidths = empty($config['pageAreaWidths']['p'.$page]) ? static::$pageAreaPercents : $config['pageAreaWidths']['p'.$page];
        $pageAreaSkip = null;
        foreach ($pageAreaWidths as $side => $sideWith) {
            if (in_array($sideWith, array_keys(Helper\PagesTranslationHelper::$widthStyle))) {
                if ($sideWith === 100) {
                    unset(static::$pageAreaPercents[$side]);
                    $pageAreaSkip = array_keys(static::$pageAreaPercents)[0];
                }
                $pageAreaWidths[$side] = Helper\PagesTranslationHelper::$widthStyle[$sideWith];
                $sideOpposite = $side === 'a' ? 'b' : 'a';
                $sideWithOpposite = 100 - intval($sideWith);
                isset($pageAreaWidths[$sideOpposite]) ?
                    $pageAreaWidths[$sideOpposite] = isset(Helper\PagesTranslationHelper::$widthStyle[$sideWithOpposite]) ?
                        Helper\PagesTranslationHelper::$widthStyle[$sideWithOpposite] : 'w-auto' :
                        null;
                break;
            }
        }
        // Load texts
        $pageAreaSkip !== 'a' ? $this->loadData($lang, $table, $course, $page, 'a') : null;
        $pageAreaSkip !== 'b' ? $this->loadData($lang, $table, $course, $page, 'b') : null;

        return array_merge(static::$pageTexts, ['pageAreaSkip' => $pageAreaSkip, 'pageAreaWidths' => $pageAreaWidths]);
    }

    /**
     * @param string $lang
     * @param string $table
     * @param int    $course
     * @param string $wildcardA
     * @param string $wildcardB
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadData($lang, $table, $course, $wildcardA, $wildcardB)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('LOWER(t.edg051_codprueba) as code', 'LOWER(t.edg051_cod_texto) as text_code', 'TRIM(t.edg051_item_num) as item_num', 'TRIM(t.edg051_texto_'.$lang.') as text_string')
            ->from($table, 't')
            ->where('t.edg051_periodo = :course')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['course' => $course, 'text_code' => 'p'.$wildcardA.$wildcardB.'%']);

        /** @var array $texts */
        $texts = $queryBuilder->execute()->fetchAll();
        if (!empty($texts)) {
            static::$pageTexts['code'] = $texts[0]['code'];
            static::$pageTexts['numbers'][$wildcardB] = [];
            static::$pageTexts['texts'][$wildcardB] = [];
            foreach ($texts as $text) {
                if (!empty($text['item_num'])) {
                    static::$pageTexts['numbers'][$wildcardB][$text['text_code']] = trim($text['item_num']);
                }
                static::$pageTexts['texts'][$wildcardB][$text['text_code']] = trim($text['text_string']);
            }

            return static::$pageTexts;
        }

        throw new \NoticeException(static::$debug ? sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())) : 'No results found');
    }

    /**
     * @param array  $args
     * @param string $page
     * @param string $dataDir
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $args, $page, $dataDir = DATA_DIR)
    {
        /** @var string $target */
        $target = $dataDir.'/'.$args['code'];

        /** @var array $config */
        $config = parseConfig($target, 'config');
        if (empty($config)) {
            throw new \Exception(sprintf('The configuration file `%s` is missing in the target: %s', 'config.yml', $target));
        }
        /** @var array $options */
        $options = parseConfig($target, 'options');
        if (empty($options)) {
            throw new \Exception(sprintf('The options file `%s` is missing in the target: %s', 'options.yml', $target));
        }

        return array_merge(
            Helper\PagesTranslationHelper::load($args, $config, $options, $page),
            $this->loadTexts($args['lengua'], $args['table'], $args['course'], $config, $page));
    }

    /**
     * @param array $args
     *
     * @return string
     */
    public function saveData(array $args)
    {
        $sql = 'SELECT count(id) FROM '.$args['target'].' WHERE `id` = "'.$args['studentCode'].'";';
        $this->getConnection()->beginTransaction();
        $stmt = $this->getConnection()->prepare($sql);
        try {
            //$stmt->bindParam(':id', $sessionId, 2);
            $stmt->execute();
            $total = $stmt->fetch(\PDO::FETCH_NUM);

            if ($total !== false) {
                return $this->mergeAndCommit($args);
            }

            return false;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
                $table = new DBAL\Schema\Table($args['target']);
                $table->addColumn('id', DBAL\Types\Type::getType('string'), ['length' => 11, 'notnull' => true]);
                $table->addColumn('birthday', DBAL\Types\Type::getType('integer'), ['length' => 2, 'notnull' => true]);
                $table->addColumn('birthmonth', DBAL\Types\Type::getType('integer'), ['length' => 2, 'notnull' => true]);
                $table->addColumn('course', DBAL\Types\Type::getType('integer'), ['length' => 4, 'notnull' => true]);
                $table->addColumn('stage', DBAL\Types\Type::getType('string'), ['length' => 4, 'notnull' => true]);
                $table->addColumn('code', DBAL\Types\Type::getType('string'), ['length' => 20, 'notnull' => true]);
                $table->addColumn('lang', DBAL\Types\Type::getType('string'), ['length' => 5, 'notnull' => true]);
                $table->addColumn('lengua', DBAL\Types\Type::getType('string'), ['length' => 3, 'notnull' => true]);
                $table->addColumn('time', DBAL\Types\Type::getType('string'), ['length' => 30, 'notnull' => true]);
                $table->setPrimaryKey(['id']);
                $this->getConnection()->getSchemaManager()->createTable($table);

                return $this->saveData($args);
            }
            throw new \RuntimeException(sprintf('Error while trying to save data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Runs a merge/update (i.e. insert or update) SQL query when supported by the database.
     *
     * @param array $args
     *
     * @return bool
     */
    private function mergeAndCommit($args)
    {
        $columns = [];
        $values = [];
        $itemsStmt = 'INSERT INTO '.$args['target'].' (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s';
        foreach ($args as $key => $value) {
            if (preg_match('/p\d+[a|b]_t.+/', $key)) {
                //echo $key.': '.$value.'<br/>';
                $columns[] = $key;
                $values[] = $value;
                $itemsStmt = sprintf($itemsStmt, $key.', %s', $value.', %s', $key.' = VALUES('.$key.'), %s');
            }
        }
        $itemsStmt = sprintf($itemsStmt, 'id, birthday, birthmonth, course, stage, code, lang, lengua, time', ':id, :birthday, :birthmonth, :course, :stage, :code, :lang, :lengua, :time', 'id = VALUES(id), birthday = VALUES(birthday), birthmonth = VALUES(birthmonth), course = VALUES(course), stage = VALUES(stage), code = VALUES(code), lang = VALUES(lang), lengua = VALUES(lengua), time = VALUES(time)');
        $mergeStmt = $this->getConnection()->prepare($itemsStmt);
        $mergeStmt->bindValue(':id', $args['studentCode']);
        $mergeStmt->bindValue(':birthday', $args['studentDay']);
        $mergeStmt->bindValue(':birthmonth', $args['studentMonth']);
        $mergeStmt->bindValue(':course', $args['course']);
        $mergeStmt->bindValue(':stage', $args['stage']);
        $mergeStmt->bindValue(':code', $args['code']);
        $mergeStmt->bindValue(':lang', $args['lang']);
        $mergeStmt->bindValue(':lengua', $args['lengua']);
        $mergeStmt->bindValue(':time', date(\DateTime::RFC822, time()));
        try {
            $mergeStmt->execute();
            $this->getConnection()->commit();
        } catch (\Exception $e) {
            $schemaManager = $this->getConnection()->getSchemaManager();
            if (strpos($e->getMessage(), 'Column not found') !== false) {
                /** @see http://www.craftitonline.com/2014/09/doctrine-migrations-with-schema-api-without-symfony-symfony-cmf-seobundle-sylius-example */
                $dbColums = $schemaManager->listTableColumns($args['target']);
                foreach ($columns as $column) {
                    if (!array_search($column, array_keys($dbColums))) {
                        //echo '<br>add '.$column;
                        $tableDiff = new DBAL\Schema\TableDiff($args['target'], [new DBAL\Schema\Column($column, DBAL\Types\Type::getType('string'), ['length' => 100, 'notnull' => false])]);
                        $schemaManager->alterTable($tableDiff);
                    }
                }

                return $this->saveData($args);
            }
        }

        return true;
    }
}
