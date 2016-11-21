<?php

namespace App\Model;

/**
 * Class PagesModel.
 */
final class PagesModel extends CredentialsModel
{
    /** @var array */
    private static $pageTexts = ['id' => '', 'texts' => []];

    /**
     * @param array  $args
     * @param string $wildcardA
     * @param string $wildcardB
     *
     * @return array
     *
     * @throws \Exception
     */
    private function loadData(array $args, $wildcardA, $wildcardB)
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('LOWER(t.edg051_codprueba) as id', 't.edg051_cod_texto as text_code', 'TRIM(t.edg051_texto_' . $args['lengua'] . ') as text_string')
            ->from($args['table'], 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $args['course'], 'text_code' => 'p' . $wildcardA . $wildcardB . '%']);

        /** @var array $texts */
        $texts = $queryBuilder->execute()->fetchAll();
        if (!empty($texts)) {
            static::$pageTexts['id'] = $texts[0]['id'];
            static::$pageTexts['texts'][$wildcardB] = [];
            foreach ($texts as $text) {
                static::$pageTexts['texts'][$wildcardB][$text['text_code']] = trim($text['text_string']);
            }

            return static::$pageTexts;
        } else {
            throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
        }
    }

    /**
     * @param array  $args
     * @param string $page
     * @param array  $defaultPageSidePercents
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $args, $page, $defaultPageSidePercents = ['a' => '50', 'b' => '50'])
    {
        $data_folder = DATA_DIR . '/' . $args['code'];
        $config = parseConfig($data_folder, 'config');
        if (empty($config)) {
            throw new \Exception(sprintf('The configuration file `%s` is missing in the target: %s', 'config.yml', $data_folder));
        }

        $pageWidth = empty($config['pageWidths']['p' . $page]) ? $defaultPageSidePercents : $config['pageWidths']['p' . $page];
        $widthStyling = \def::styling()['width'];
        $pageSideSkip = null;
        $pageSides = $defaultPageSidePercents;
        foreach ($pageWidth as $sideLetter => $sideWith) {
            if (in_array($sideWith, array_keys($widthStyling))) {
                if ($sideWith === 100) {
                    unset($pageSides[$sideLetter]);
                    $pageSideSkip = array_keys($pageSides)[0];
                }
                $pageWidth[$sideLetter] = $widthStyling[$sideWith];
            }
        }
        //$pageSideSkip = empty($config['pageSideSkips']['p' . $page]) ? null : $config['pageSideSkips']['p' . $page];
        $sideA = $pageSideSkip !== 'a' ? $this->loadData($args, $page, 'a') : [];
        $sideB = $pageSideSkip !== 'b' ? $this->loadData($args, $page, 'b') : [];

        $options = parseConfig($data_folder, 'options');
        if (empty($options)) {
            throw new \Exception(sprintf('The options file `%s` is missing in the target: %s', 'options.yml', $data_folder));
        }

        return array_merge($args, $sideA, $sideB, [
            'lang' => $args['lang'],
            'page' => $page,
            'pageOptions' => empty($options['p' . $page]) ? [] : $options['p' . $page],
            'pageTitles' => empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageReplaces' => empty($config['pageReplaces']['p' . $page]) ? [] : $config['pageReplaces']['p' . $page],
            'pageWidth' => $pageWidth,
            'pageSideSkip' => $pageSideSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }
}
