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
     * @param array  $default_width_percents
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $args, $page, $default_width_percents = ['a' => '50', 'b' => '50'])
    {
        $id = $args['code'];
        $config = parseConfig(DATA_DIR, $id);

        if (empty($config)) {
            throw new \Exception(sprintf('The configuration file `%s` is missing in the target: %s', $id, DATA_DIR));
        }
        $pageWidth = empty($config['pageWidths']['p' . $page]) ? $default_width_percents : $config['pageWidths']['p' . $page];
        $widthStyling = \def::styling()['width'];
        foreach ($pageWidth as $sideLetter => $sideWith) {
            if (in_array($sideWith, array_keys($widthStyling))) {
                $pageWidth[$sideLetter] = $widthStyling[$sideWith];
            }
        }
        $pageSideSkip = empty($config['pageSideSkips']['p' . $page]) ? null : $config['pageSideSkips']['p' . $page];
        $sideA = $pageSideSkip !== 'a' ? $this->loadData($args, $page, 'a') : [];
        $sideB = $pageSideSkip !== 'b' ? $this->loadData($args, $page, 'b') : [];

        return array_merge($args, $sideA, $sideB, [
            'id' => $id,
            'lang' => $args['lang'],
            'options' => empty($config['pageOptions']['p' . $page]) ? [] : $config['pageOptions']['p' . $page],
            'page' => $page,
            'pageWidth' => $pageWidth,
            'sideSkip' => $pageSideSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }
}
