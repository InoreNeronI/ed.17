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
            $this->loadTexts($args['lengua'], $args['table'], $args['course'], $config, $page),
            ['values' => $this->loadValues($page, $args['target'], $args['studentCode'])]);
    }

    /**
     * Runs a SQL query.
     *
     * @param string $page
     * @param string $target
     * @param string $targetId
     * @param array  $values
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadValues($page, $target, $targetId, array $values = [])
    {
        $connection = $this->getConnection();
        $manager = $connection->getSchemaManager();
        $table = $manager->listTableDetails($target);
        foreach ($table->getColumns() as $key => $value) {
            if (preg_match('/p'.$page.'[a|b]_t.+/i', $key)) {
                $values[] = $key;
            }
        }
        $query = sprintf('SELECT %s FROM %s WHERE id = "%s";', implode(', ', $values), $target, $targetId);

        return empty($values) ? [] : $connection->executeQuery($query)->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Runs a merge/update (i.e. insert or update) SQL query.
     *
     * @param array $args
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function saveData(array $args)
    {
        $commonCols = ['id', 'birthday', 'birthmonth', 'course', 'stage', 'code', 'lang', 'lengua', 'time'];
        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new \DateTime( date('Y-m-d H:i:s.'.$micro, $t) );
        $time = $d->format("Y-m-d H:i:s.u");
        $commonValues = ['id' => $args['studentCode'], 'birthday' => $args['studentDay'], 'birthmonth' => $args['studentMonth'], 'course' => $args['course'], 'stage' => $args['stage'], 'code' => $args['code'], 'lang' => $args['lang'], 'lengua' => $args['lengua'], 'time' => $time];
        $statement = 'INSERT INTO '.$args['target'].' (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s';
        $columns = [];
        $values = [];
        $connection = $this->getConnection();
        foreach ($args as $key => $value) {
            if (preg_match('/p\d+[a|b]_t.+/i', $key)) {
                $columns[] = $key;
                $values[] = $value;
                $statement = sprintf($statement, $key.', %s', '"'.$value.'", %s', $key.' = VALUES('.$key.'), %s');
            }
        }
        $statement = sprintf($statement, implode(', ', $commonCols), ':'.implode(', :', $commonCols), 'id = VALUES(id), birthday = VALUES(birthday), birthmonth = VALUES(birthmonth), course = VALUES(course), stage = VALUES(stage), code = VALUES(code), lang = VALUES(lang), lengua = VALUES(lengua), time = VALUES(time)');
        $mergeStmt = $connection->prepare($statement);
        foreach ($commonCols as $col) {
            $mergeStmt->bindValue(':'.$col, $commonValues[$col]);
        }
        try {
            $mergeStmt->execute();
            if (!realpath(DATA_CACHE_DIR)) {
                mkdir(DATA_CACHE_DIR, 0777, true);
            }
            $path = DATA_CACHE_DIR.'/'.$args['code'];
            file_put_contents($path, implode('#', array_merge($commonCols, $columns)).PHP_EOL, FILE_APPEND);
            file_put_contents($path, implode('#', array_merge($commonValues, $values)).PHP_EOL, FILE_APPEND);
        } catch (DBAL\Exception\TableNotFoundException $e) {
            $table = new DBAL\Schema\Table($args['target']);
            $table->addColumn('code', 'string', ['length' => 20, 'notnull' => true]);
            $table->addColumn('id', 'string', ['length' => 11, 'notnull' => true]);
            $table->addColumn('birthday', 'integer', ['length' => 2, 'notnull' => true]);
            $table->addColumn('birthmonth', 'integer', ['length' => 2, 'notnull' => true]);
            $table->addColumn('course', 'integer', ['length' => 4, 'notnull' => true]);
            $table->addColumn('stage', 'string', ['length' => 4, 'notnull' => true]);
            $table->addColumn('lang', 'string', ['length' => 5, 'notnull' => true]);
            $table->addColumn('lengua', 'string', ['length' => 3, 'notnull' => true]);
            $table->addColumn('time', 'string', ['length' => 30, 'notnull' => true]);
            $table->setPrimaryKey(['code', 'id']);
            $connection->getSchemaManager()->createTable($table);

            return $this->saveData($args);
        } catch (DBAL\Exception\InvalidFieldNameException $e) {
            $schemaManager = $connection->getSchemaManager();
            // @see http://www.craftitonline.com/2014/09/doctrine-migrations-with-schema-api-without-symfony-symfony-cmf-seobundle-sylius-example
            $tableDiffColumns = [];
            foreach ($columns as $column) {
                if (!array_search($column, array_keys($schemaManager->listTableColumns($args['target'])))) {
                    $tableDiffColumns[] = new DBAL\Schema\Column($column, DBAL\Types\Type::getType('string'), ['length' => 100, 'notnull' => false]);
                }
            }
            $schemaManager->alterTable(new DBAL\Schema\TableDiff($args['target'], $tableDiffColumns));

            return $this->saveData($args);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Error while saving data: %s', $e->getMessage()), 0, $e);
        }

        return true;
    }
}
