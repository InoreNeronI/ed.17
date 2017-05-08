<?php

namespace Cache;

/**
 * Interface CacheInterface
 */
interface CacheInterface
{
    /**
     * @param string $content
     * @param array  $options
     *
     * @return string
     */
    public static function minify($content, $options);
}
