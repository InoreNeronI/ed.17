<?php

namespace App\Model;

/**
 * Class PagesModel.
 */
final class PagesModel extends CredentialsModel
{
    /** @var array */
    private static $pageTexts = ['code' => '', 'texts' => []];

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
            ->select('LOWER(t.edg051_codprueba) as code', 't.edg051_cod_texto as text_code', 'TRIM(t.edg051_texto_' . $args['lengua'] . ') as text_string')
            ->from($args['table'], 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $args['course'], 'text_code' => 'p' . $wildcardA . $wildcardB . '%']);

        /** @var array $texts */
        $texts = $queryBuilder->execute()->fetchAll();
        if (!empty($texts)) {
            static::$pageTexts['code'] = $texts[0]['code'];
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
     * @param array  $defaultPageAreaPercents
     *
     * @return array
     *
     * @throws \Exception
     */
    public function loadPageData(array $args, $page, $dataDir = DATA_DIR, $defaultPageAreaPercents = ['a' => '50', 'b' => '50'])
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
        $pageWidth = empty($config['pageAreaWidths']['p' . $page]) ? $defaultPageAreaPercents : $config['pageAreaWidths']['p' . $page];
        /** @var array $widthStyling */
        $widthStyling = \def::styling()['width'];
        /** @var array $pageAreas */
        $pageAreas = $defaultPageAreaPercents;
        /** @var string|null $pageAreaSkip */
        $pageAreaSkip = null;
        /** @var array $pageTextReplaces */
        $pageTextReplaces = empty($config['pageTextReplaces']['p' . $page]) ? [] : $config['pageTextReplaces']['p' . $page];

        // Sides' widths
        foreach ($pageWidth as $sideLetter => $sideWith) {
            if (in_array($sideWith, array_keys($widthStyling))) {
                if ($sideWith === 100) {
                    unset($pageAreas[$sideLetter]);
                    $pageAreaSkip = array_keys($pageAreas)[0];
                }
                $pageWidth[$sideLetter] = $widthStyling[$sideWith];
            }
        }

        // Load texts
        $pageAreaSkip !== 'a' ? $this->loadData($args, $page, 'a') : null;
        $pageAreaSkip !== 'b' ? $this->loadData($args, $page, 'b') : null;

        // Images
        $images = [];
        foreach(empty($config['pageImages']['p' . $page]) ? [] : $config['pageImages']['p' . $page] as $image) {
            $imageData = explode('.', $image);
            $imageData = explode('_', $imageData[0]);
            if ($imageData[5] === $args['lengua'] || $imageData[5] === 'img') {
                $side = str_replace('p' . $page, '', $imageData[0]);
                $offset = 100 - $imageData[4];
                $images[$side][$imageData[0] . '_' . $imageData[2]] = [
                    'alignment' => $imageData[1],
                    'offset' => $widthStyling[isset($widthStyling[$offset]) ? $offset : 'auto'],
                    'path' => '/images/' . $args['code'] . '/' . $image,
                    'since' => intval(str_replace('t', '', $imageData[2])),
                    'till' => intval(str_replace('t', '', $imageData[3])),
                    'width' => $widthStyling[isset($widthStyling[$imageData[4]]) ? $imageData[4] : 'auto']
                ];
            }
        }

        // Replaces width
        foreach ($pageTextReplaces as $code => $replace) {
            $pageTextReplaces[$code]['parameters']['width'] = $widthStyling[isset($pageTextReplaces[$code]['parameters']['width']) ? $pageTextReplaces[$code]['parameters']['width'] : 'auto'];
        }

        return array_merge($args, static::$pageTexts, [
            'page' => $page,
            'pageImages' => $images,
            'pageOptions' => empty($options['p' . $page]) ? [] : $options['p' . $page],
            'pageTitles' => empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageTextReplaces' => $pageTextReplaces,
            'pageWidth' => $pageWidth,
            'pageAreaSkip' => $pageAreaSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }
}
