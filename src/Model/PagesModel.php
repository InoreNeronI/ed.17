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
        $options = parseConfig($data_folder, 'options');
        if (empty($options)) {
            throw new \Exception(sprintf('The options file `%s` is missing in the target: %s', 'options.yml', $data_folder));
        }

        $pageWidth = empty($config['pageWidths']['p' . $page]) ? $defaultPageSidePercents : $config['pageWidths']['p' . $page];
        $widthStyling = \def::styling()['width'];
        $pageSides = $defaultPageSidePercents;
        $pageSideSkip = null;
        foreach ($pageWidth as $sideLetter => $sideWith) {
            if (in_array($sideWith, array_keys($widthStyling))) {
                if ($sideWith === 100) {
                    unset($pageSides[$sideLetter]);
                    $pageSideSkip = array_keys($pageSides)[0];
                }
                $pageWidth[$sideLetter] = $widthStyling[$sideWith];
            }
        }
        $sideA = $pageSideSkip !== 'a' ? $this->loadData($args, $page, 'a') : [];
        $sideB = $pageSideSkip !== 'b' ? $this->loadData($args, $page, 'b') : [];

        $images = [];
        foreach(empty($config['pageImages']['p' . $page]) ? [] : $config['pageImages']['p' . $page] as $image) {
            $imageData = explode('.', $image);
            $imageData = explode('_', $imageData[0]);
            if ($imageData[5] === $args['lengua'] || $imageData[5] === 'img') {
                $startText = intval(str_replace('t', '', $imageData[2]));
                $endText = intval(str_replace('t', '', $imageData[3]));
                $side = str_replace('p' . $page, '', $imageData[0]);
                for ($i = $startText; $i <= $endText; $i++) {
                    $images[$side]['p' . $page . $side . '_t' . sprintf('%02d', $i)] = [
                        'alignment' => $imageData[1],
                        'width' => $widthStyling[$imageData[4]],
                        'offsetWidth' => $widthStyling[100 - $imageData[4]],
                        'path' => '/images/' . $args['code'] . '/' . $image
                    ];
                }
            }
        }

        return array_merge($args, $sideA, $sideB, [
            'page' => $page,
            'pageImages' => $images,
            'pageOptions' => empty($options['p' . $page]) ? [] : $options['p' . $page],
            'pageTitles' => empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageReplaces' => empty($config['pageReplaces']['p' . $page]) ? [] : $config['pageReplaces']['p' . $page],
            'pageWidth' => $pageWidth,
            'pageSideSkip' => $pageSideSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }
}
