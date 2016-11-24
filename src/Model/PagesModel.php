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
     * @param string $dataDir
     * @param array $defaultPageSidePercents
     * @return array
     * @throws \Exception
     */
    public function loadPageData(array $args, $page, $dataDir = DATA_DIR, $defaultPageSidePercents = ['a' => '50', 'b' => '50'])
    {
        /** @var string $data_folder */
        $data_folder = $dataDir . '/' . $args['code'];
        /** @var array $config */
        $config = parseConfig($data_folder, 'config');
        if (empty($config)) {
            throw new \Exception(sprintf('The configuration file `%s` is missing in the target: %s', 'config.yml', $data_folder));
        }
        /** @var array $options */
        $options = parseConfig($data_folder, 'options');
        if (empty($options)) {
            throw new \Exception(sprintf('The options file `%s` is missing in the target: %s', 'options.yml', $data_folder));
        }
        /** @var array $pageWidth */
        $pageWidth = empty($config['pageWidths']['p' . $page]) ? $defaultPageSidePercents : $config['pageWidths']['p' . $page];
        /** @var array $widthStyling */
        $widthStyling = \def::styling()['width'];
        /** @var array $pageSides */
        $pageSides = $defaultPageSidePercents;
        /** @var string|null $pageSideSkip */
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
                $side = str_replace('p' . $page, '', $imageData[0]);
                $offset = 100 - $imageData[4];
                $images[$side][$imageData[0] . '_' . $imageData[2]] = [
                    'alignment' => $imageData[1],
                    'offset' => isset($widthStyling[$offset]) ? $widthStyling[$offset] : $widthStyling['auto'],
                    'path' => '/images/' . $args['code'] . '/' . $image,
                    'since' => intval(str_replace('t', '', $imageData[2])),
                    'till' => intval(str_replace('t', '', $imageData[3])),
                    'width' => isset($widthStyling[$imageData[4]]) ? $widthStyling[$imageData[4]] : $widthStyling['auto']
                ];
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
