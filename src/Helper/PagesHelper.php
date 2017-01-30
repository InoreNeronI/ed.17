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
        try {
            $this->getConnection()->beginTransaction();
            $stmt = $this->getConnection()->prepare($sql);
            //$stmt->bindParam(':id', $sessionId, 2);
            $stmt->execute();
            $total = $stmt->fetch(\PDO::FETCH_NUM);

            if ($total !== false) {
                return $this->mergeAndCommit($args['target'], $args['studentCode'], $args['studentDay'], $args['studentMonth'], $args['course'], $args['stage'], $args['code'], $args['lang'], $args['lengua']);
            }

            return false;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
                $table = new DBAL\Schema\Table($args['target']);
                $table->addColumn('id', 'string', ['length' => 11]);
                $table->addColumn('birthday', 'integer', ['length' => 2]);
                $table->addColumn('birthmonth', 'integer', ['length' => 2]);
                $table->addColumn('course', 'integer', ['length' => 4]);
                $table->addColumn('stage', 'string', ['length' => 4]);
                $table->addColumn('code', 'string', ['length' => 20]);
                $table->addColumn('lang', 'string', ['length' => 5]);
                $table->addColumn('lengua', 'string', ['length' => 3]);
                $table->addColumn('time', 'string', ['length' => 30]);
                $table->setPrimaryKey(['id']);
                $this->getConnection()->getSchemaManager()->createTable($table);

                return $this->saveData($args);
            }
            throw new \RuntimeException(sprintf('Error while trying to save data: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Returns a merge/upsert (i.e. insert or update) SQL query when supported by the database.
     *
     * @param string $table
     *
     * @return string|null The SQL string or null when not supported
     */
    private function zeroSql($table)
    {
        return 'INSERT INTO '.$table.' (id, birthday, birthmonth, course, stage, code, lang, lengua, time) VALUES (:id, :birthday, :birthmonth, :course, :stage, :code, :lang, :lengua, :time) '.
            'ON DUPLICATE KEY UPDATE id = VALUES(id), birthday = VALUES(birthday), birthmonth = VALUES(birthmonth), course = VALUES(course), stage = VALUES(stage), code = VALUES(code), lang = VALUES(lang), lengua = VALUES(lengua), time = VALUES(time)';
    }

    /**
     * @param string $table
     * @param string $id
     * @param string $birthday
     * @param string $birthmonth
     * @param int    $course
     * @param string $stage
     * @param string $code
     * @param string $lang
     * @param string $lengua
     *
     * @return bool
     */
    private function mergeAndCommit($table, $id, $birthday, $birthmonth, $course, $stage, $code, $lang, $lengua)
    {
        $mergeStmt = $this->getConnection()->prepare($this->zeroSql($table));
        $mergeStmt->bindValue(':id', $id);
        $mergeStmt->bindValue(':birthday', $birthday);
        $mergeStmt->bindValue(':birthmonth', $birthmonth);
        $mergeStmt->bindValue(':course', $course);
        $mergeStmt->bindValue(':stage', $stage);
        $mergeStmt->bindValue(':code', $code);
        $mergeStmt->bindValue(':lang', $lang);
        $mergeStmt->bindValue(':lengua', $lengua);
        $mergeStmt->bindValue(':time', date(\DateTime::RFC822, time()));
        $mergeStmt->execute();
        $this->getConnection()->commit();

        return true;
    }
}
