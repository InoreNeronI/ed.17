<?php

namespace App\Helper;

use App\Helper;
use App\Security;

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
     * @param int    $year
     * @param array  $config
     * @param string $page
     *
     * @return array
     */
    private function loadTexts($lang, $table, $year, array $config, $page)
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
        $pageAreaSkip !== 'a' ? $this->loadData($lang, $table, $year, $page, 'a') : null;
        $pageAreaSkip !== 'b' ? $this->loadData($lang, $table, $year, $page, 'b') : null;

        return array_merge(static::$pageTexts, ['pageAreaSkip' => $pageAreaSkip, 'pageAreaWidths' => $pageAreaWidths]);
    }

    /**
     * @param string $lang
     * @param string $table
     * @param int    $year
     * @param string $wildcardA
     * @param string $wildcardB
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadData($lang, $table, $year, $wildcardA, $wildcardB)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('LOWER(t.edg051_codprueba) as code', 'LOWER(t.edg051_cod_texto) as text_code', 'TRIM(t.edg051_item_num) as item_num', 'TRIM(t.edg051_texto_'.$lang.') as text_string')
            ->from($table, 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $year, 'text_code' => 'p'.$wildcardA.$wildcardB.'%']);

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
        //throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        throw new \NoticeException('No results found');
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
     * @param bool  $debug
     */
    private function saveData(array $args, $debug = DEBUG)
    {
    }
}
