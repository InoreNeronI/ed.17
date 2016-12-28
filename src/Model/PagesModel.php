<?php

namespace App\Model;

/**
 * Class PagesModel.
 */
final class PagesModel extends CredentialsModel
{
    /** @var array */
    private static $mediaLibrary = ['audio' => ['ext' => ['mp3', 'ogg']], 'img' => ['ext' => ['jpg', 'png']], 'video' => ['ext' => ['3gp', 'mp4']]];
    /** @var array */
    private static $pageAreaPercents = ['a' => '50', 'b' => '50'];
    /** @var array */
    private static $pageTexts = ['code' => '', 'numbers' => [], 'texts' => []];
    /** @var array */
    private static $widthStyle = [];

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
            ->select('LOWER(t.edg051_codprueba) as code', 'LOWER(t.edg051_cod_texto) as text_code', 'TRIM(t.edg051_item_num) as item_num', 'TRIM(t.edg051_texto_'.$args['lengua'].') as text_string')
            ->from($args['table'], 't')
            ->where('t.edg051_periodo = :periodo')
            ->andWhere('t.edg051_cod_texto LIKE :text_code')
            ->orderBy('t.edg051_cod_texto')
            ->setParameters(['periodo' => $args['course'], 'text_code' => 'p'.$wildcardA.$wildcardB.'%']);

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
        throw new \Exception(sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())));
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
        /** @var array static::$widthStyle */
        static::$widthStyle = \def::styling()['width'];

        /** @var string $data_folder */
        $data_folder = $dataDir.'/'.$args['code'];

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

        // Sides' width
        /** @var array $pageWidth */
        $pageWidth = empty($config['pageAreaWidths']['p'.$page]) ? static::$pageAreaPercents : $config['pageAreaWidths']['p'.$page];
        /** @var string|null $pageAreaSkip */
        $pageAreaSkip = null;
         foreach ($pageWidth as $sideLetter => $sideWith) {
            if (in_array($sideWith, array_keys(static::$widthStyle))) {
                if ($sideWith === 100) {
                    unset(static::$pageAreaPercents[$sideLetter]);
                    $pageAreaSkip = array_keys(static::$pageAreaPercents)[0];
                }
                $pageWidth[$sideLetter] = static::$widthStyle[$sideWith];
            }
        }

        // Load texts
        $pageAreaSkip !== 'a' ? $this->loadData($args, $page, 'a') : null;
        $pageAreaSkip !== 'b' ? $this->loadData($args, $page, 'b') : null;

        // Parse media
        $mediaLibrary = [];
        foreach (empty($config['pageAudios']['p'.$page]) ? [] : $config['pageAudios']['p'.$page] as $media) {
            $mediaLibrary = self::parseMedia($args, $page, 'audio', $media, $mediaLibrary);
        }
        foreach (empty($config['pageImages']['p'.$page]) ? [] : $config['pageImages']['p'.$page] as $media) {
            $mediaLibrary = self::parseMedia($args, $page, 'img', $media, $mediaLibrary);
        }
        foreach (empty($config['pageVideos']['p'.$page]) ? [] : $config['pageVideos']['p'.$page] as $media) {
            $mediaLibrary = self::parseMedia($args, $page, 'video', $media, $mediaLibrary);
        }

        // Replaces width
        /** @var array $pageTextReplaces */
        $pageTextReplaces = empty($config['pageTextReplaces']['p'.$page]) ? [] : $config['pageTextReplaces']['p'.$page];
        foreach ($pageTextReplaces as $code => $replace) {
            $pageTextReplaces[$code]['parameters']['width'] = static::$widthStyle[isset($pageTextReplaces[$code]['parameters']['width']) ? $pageTextReplaces[$code]['parameters']['width'] : 'auto'];
        }

        return array_merge($args, static::$pageTexts, [
            'page' => $page,
            'pageMedia' => empty($mediaLibrary) ? [] : $mediaLibrary,
            'pageOptions' => empty($options['p'.$page]) ? [] : $options['p'.$page],
            'pageTitles' => empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageTextReplaces' => $pageTextReplaces,
            'pageWidth' => $pageWidth,
            'pageAreaSkip' => $pageAreaSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }

    /**
     * @param array $args
     * @param string $page
     * @param string $tag
     * @param string $media
     * @param array $mediaLibrary
     * @return array
     */
    private static function parseMedia(array $args, $page, $tag, $media, array $mediaLibrary)
    {
        $mediaData = explode('.', $media);
        $mediaExt = strtolower($mediaData[1]);
        $mediaData = explode('_', $mediaData[0]);
        if (in_array($mediaExt, static::$mediaLibrary[$tag]['ext']) && ($mediaData[5] === $args['lengua'] || $mediaData[5] === $tag)) {
            $baseDir = $tag === 'img' ? '/images/' : '/media/';
            $offset = 100 - $mediaData[4];
            $side = str_replace('p' . $page, '', $mediaData[0]);
            if ($tag === 'audio') {
                $path = [];
                foreach (static::$mediaLibrary['audio']['ext'] as $ext) {
                    $path[$ext] = $mediaExt === $ext ? $baseDir.$args['code'].'/'.$media : '';
                }
                $size = $args['sizes']['levels'][$args['sizes'][$mediaData[4]]];
                $width = $args['sizes']['percentages'][$args['sizes'][$mediaData[4]]];
            } else/*if ($tag === 'img' || $tag === 'video')*/ {
                $path = $baseDir.$args['code'].'/'.$media;
                $size = null;
                $width = $mediaData[4];
            }
            $mediaLibrary[$side][$tag][$mediaData[0] . '_' . $mediaData[2]] = [
                'align' => $mediaData[1],
                'offset' => static::$widthStyle[isset(static::$widthStyle[$offset]) ? $offset : 'auto'],
                'path' => $path,
                'since' => intval(str_replace('t', '', $mediaData[2])),
                'size' => $size,
                'tag' => $tag,
                'till' => intval(str_replace('t', '', $mediaData[3])),
                'width' => static::$widthStyle[isset(static::$widthStyle[$width]) ? $width : 'auto'],
            ];
        }
        return $mediaLibrary;
    }
}
