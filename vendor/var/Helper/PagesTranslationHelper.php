<?php

namespace Helper;

/**
 * Class PagesTranslationHelper
 */
final class PagesTranslationHelper
{
    /** @var array */
    private static $library = [
        'audio' => ['ext' => ['mp3', 'ogg']],
        'img' => ['ext' => ['jpg', 'png']],
        'video' => ['ext' => ['3gp', 'mp4', 'ogg', 'ogv', 'webm']], ];

    /** @var array */
    public static $widthStyle = [];

    /**
     * @param array  $args
     * @param array  $config
     * @param array  $options
     * @param string $page
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function load(array $args, $config, $options, $page)
    {
        /** @var array $widthStyle */
        static::$widthStyle = \def::metric()['widths'];

        return array_merge($args, [
            'page' => $page,
            'pageMedia' => static::processLibrary($page, $config, $args),
            'pageOptions' => $options = empty($options['p'.$page]) ? [] : $options['p'.$page],
            'pageTitles' => $titles = empty($config['pageTitles']) ? [] : $config['pageTitles'],
            'pageOptionAttributes' => static::processAttributes($page, $config, $options),
            'pageOptionReplaces' => static::processReplaces($page, $config, 'Option'),
            'pageTextReplaces' => static::processReplaces($page, $config),
            'totalPages' => $config['totalPages'],
        ]);
    }

    /**
     * @param string $page
     * @param array  $config
     * @param array  $args
     * @param array  $library
     *
     * @return array
     */
    private static function processLibrary($page, array $config, array $args, array $library = [])
    {
        foreach (empty($config['pageAudios']['p'.$page]) ? [] : $config['pageAudios']['p'.$page] as $media) {
            $library = static::processMedia($args, $page, 'audio', $media, $library);
        }
        foreach (empty($config['pageImages']['p'.$page]) ? [] : $config['pageImages']['p'.$page] as $media) {
            $library = static::processMedia($args, $page, 'img', $media, $library);
        }
        foreach (empty($config['pageVideos']['p'.$page]) ? [] : $config['pageVideos']['p'.$page] as $media) {
            $library = static::processMedia($args, $page, 'video', $media, $library);
        }

        return $library;
    }

    /**
     * @param string $page
     * @param array  $config
     * @param array  $options
     *
     * @return array
     */
    private static function processAttributes($page, array $config, array $options)
    {
        $pageOptionAttributes = empty($config['pageOptionAttributes']['p'.$page]) ? [] : $config['pageOptionAttributes']['p'.$page];
        $pageOptionRequired = empty($config['pageOptionRequired']['p'.$page]) ? [] : $config['pageOptionRequired']['p'.$page];

        foreach ($pageOptionRequired as $item) {
            foreach ($options as $optionType => $list) {
                if (in_array($item, $list)) {
                    isset($pageOptionAttributes[$item]) ?: $pageOptionAttributes[$item] = [];
                    $pageOptionAttributes[$item] = array_merge($pageOptionAttributes[$item], ['required' => 'required']);
                    break;
                }
            }
        }

        return $pageOptionAttributes;
    }

    /**
     * @param string $page
     * @param array  $config
     * @param string $brand
     *
     * @return array
     */
    private static function processReplaces($page, array $config, $brand = 'Text')
    {
        $replaces = empty($config['page'.$brand.'Replaces']['p'.$page]) ? [] : $config['page'.$brand.'Replaces']['p'.$page];
        foreach ($replaces as $code => $replace) {
            empty($replaces[$code]['parameters']['width']) ?:
                $replaces[$code]['parameters']['width'] =
                    static::processWidthPercent($replaces[$code]['parameters']['width']);
        }

        return $replaces;
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
    private static function processMedia(array $args, $page, $tag, $media, array $library = [])
    {
        $mediaData = explode('.', $media);
        $mediaExt = strtolower($mediaData[1]);
        $mediaData = explode('_', $mediaData[0]);
        if (in_array($mediaExt, static::$library[$tag]['ext']) && ($mediaData[5] === $args['lengua'] || $mediaData[5] === $tag)) {
            $baseDir = 'img' === $tag ? '/images/' : '/media/';
            if (/*$tag === 'font' || */'audio' === $tag || 'video' === $tag) {
                $path = [];
                foreach (static::$library[$tag]['ext'] as $ext) {
                    $path[$ext] = $mediaExt === $ext ? $baseDir.$args['code'].'/'.$media : '';
                }
            } else {
                $path = $baseDir.$args['code'].'/'.$media;
            }
            if ('font' === $tag || 'audio' === $tag) {
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
                'offset' => static::processWidthPercent(100 - intval($width)),
                'path' => $path,
                'since' => intval(str_replace('t', '', $mediaData[2])),
                'size' => $size,
                'tag' => $tag,
                'till' => intval(str_replace('t', '', $mediaData[3])),
                'width' => static::processWidthPercent(intval($width)),
            ];
        }

        return $library;
    }

    /**
     * @param string $percent
     *
     * @return string
     */
    private static function processWidthPercent($percent = '100')
    {
        return static::$widthStyle[isset(static::$widthStyle[$percent]) ? $percent : 'auto'];
    }
}
