<?php

namespace App\Helper;

use App\Security;

/**
 * Class PagesHelper.
 */
final class PagesHelper extends Security\Authorization
{
    /** @var array */
    private static $library = ['audio' => ['ext' => ['mp3', 'ogg']], 'img' => ['ext' => ['jpg', 'png']], 'video' => ['ext' => ['3gp', 'mp4']]];
    /** @var array */
    private static $pageAreaPercents = ['a' => '50', 'b' => '50'];
    /** @var array */
    private static $pageTexts = ['code' => '', 'numbers' => [], 'texts' => []];
    /** @var array */
    private static $widthStyle = [];

    /**
     * @param array  $args
     * @param array  $config
     * @param string $page
     *
     * @return array
     */
    private function loadTexts(array $args, array $config, $page)
    {
        // Sides' width
        $pageAreaWidths = empty($config['pageAreaWidths']['p'.$page]) ? static::$pageAreaPercents : $config['pageAreaWidths']['p'.$page];
        $pageAreaSkip = null;
        foreach ($pageAreaWidths as $side => $sideWith) {
            if (in_array($sideWith, array_keys(static::$widthStyle))) {
                if ($sideWith === 100) {
                    unset(static::$pageAreaPercents[$side]);
                    $pageAreaSkip = array_keys(static::$pageAreaPercents)[0];
                }
                $pageAreaWidths[$side] = static::$widthStyle[$sideWith];
                $sideOpposite = $side === 'a' ? 'b' : 'a';
                $sideWithOpposite = 100 - intval($sideWith);
                isset($pageAreaWidths[$sideOpposite]) ?
                    $pageAreaWidths[$sideOpposite] = isset(static::$widthStyle[$sideWithOpposite]) ? static::$widthStyle[$sideWithOpposite] : 'w-auto' :
                    null;
                break;
            }
        }
        // Load texts
        $pageAreaSkip !== 'a' ? $this->loadData($args, $page, 'a') : null;
        $pageAreaSkip !== 'b' ? $this->loadData($args, $page, 'b') : null;

        return [$pageAreaSkip, $pageAreaWidths];
    }

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
        /** @var array static::$widthStyle */
        static::$widthStyle = \def::metric()['widths'];
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
        /** @var array $pageAreaWidths */
        /** @var string|null $pageAreaSkip */
        list($pageAreaSkip, $pageAreaWidths) = $this->loadTexts($args, $config, $page);

        return array_merge($args, static::$pageTexts, [
            'page' => $page,
            'pageMedia' => $this->translatePageLibrary($page, $config, $args),
            'pageOptions' => empty($options['p'.$page]) ? [] : $options['p'.$page],
            'pageTitles' => empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageOptionReplaces' => $this->translatePageReplaces($page, $config, 'Option'),
            'pageTextReplaces' => $this->translatePageReplaces($page, $config),
            'pageAreaWidths' => $pageAreaWidths,
            'pageAreaSkip' => $pageAreaSkip,
            'totalPages' => $config['totalPages'],
        ]);
    }

    /**
     * @param string $page
     * @param array  $config
     * @param string $brand
     *
     * @return array
     */
    private function translatePageReplaces($page, array $config, $brand = 'Text')
    {
        $replaces = empty($config['page'.$brand.'Replaces']['p'.$page]) ? [] : $config['page'.$brand.'Replaces']['p'.$page];
        foreach ($replaces as $code => $replace) {
            empty($replaces[$code]['parameters']['width']) ?:
                $replaces[$code]['parameters']['width'] =
                    static::widthPercentToHtmlClass($replaces[$code]['parameters']['width']);
        }

        return $replaces;
    }

    /**
     * @param string $page
     * @param array  $config
     * @param array  $args
     * @param array  $library
     *
     * @return array
     */
    private function translatePageLibrary($page, array $config, array $args, array $library = [])
    {
        foreach (empty($config['pageAudios']['p'.$page]) ? [] : $config['pageAudios']['p'.$page] as $media) {
            $library = static::translatePageMedia($args, $page, 'audio', $media, $library);
        }
        foreach (empty($config['pageImages']['p'.$page]) ? [] : $config['pageImages']['p'.$page] as $media) {
            $library = static::translatePageMedia($args, $page, 'img', $media, $library);
        }
        foreach (empty($config['pageVideos']['p'.$page]) ? [] : $config['pageVideos']['p'.$page] as $media) {
            $library = static::translatePageMedia($args, $page, 'video', $media, $library);
        }

        return $library;
    }

    /**
     * @param array  $args
     * @param string $page
     * @param string $tag
     * @param string $media
     * @param array  $library
     *
     * @return array
     */
    private static function translatePageMedia(array $args, $page, $tag, $media, array $library = [])
    {
        $mediaData = explode('.', $media);
        $mediaExt = strtolower($mediaData[1]);
        $mediaData = explode('_', $mediaData[0]);
        if (in_array($mediaExt, static::$library[$tag]['ext']) && ($mediaData[5] === $args['lengua'] || $mediaData[5] === $tag)) {
            $baseDir = $tag === 'img' ? '/images/' : '/media/';
            if (/*$tag === 'font' || */$tag === 'audio' || $tag === 'video') {
                $path = [];
                foreach (static::$library[$tag]['ext'] as $ext) {
                    $path[$ext] = $mediaExt === $ext ? $baseDir.$args['code'].'/'.$media : '';
                }
            } else {
                $path = $baseDir.$args['code'].'/'.$media;
            }
            if ($tag === 'font' || $tag === 'audio') {
                $level = $args['metric']['basics'][$mediaData[4]];
                $size = $args['metric']['levels'][$level];
                $width = $args['metric']['levels']['percentages'][$level];
            } else /*if ($tag === 'img' || $tag === 'video')*/ {
                $size = null;
                $width = $mediaData[4];
            }
            $side = str_replace('p'.$page, '', $mediaData[0]);
            $library[$side][$tag][$mediaData[0].'_'.$mediaData[2]] = [
                'align' => $mediaData[1],
                'offset' => static::widthPercentToHtmlClass(100 - intval($width)),
                'path' => $path,
                'since' => intval(str_replace('t', '', $mediaData[2])),
                'size' => $size,
                'tag' => $tag,
                'till' => intval(str_replace('t', '', $mediaData[3])),
                'width' => static::widthPercentToHtmlClass(intval($width)),
            ];
        }

        return $library;
    }

    /**
     * @param string $percent
     *
     * @return string
     */
    private static function widthPercentToHtmlClass($percent = '100')
    {
        return static::$widthStyle[isset(static::$widthStyle[$percent]) ? $percent : 'auto'];
    }
}
