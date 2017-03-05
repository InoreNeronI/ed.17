<?php

namespace App\Cache;

use App\Cache;
use Tholu\Packer;

/**
 * Class CachedMinifier.
 *
 * Cached minified content
 */
class CachedMinifier implements Cache\CacheInterface
{
    /**
     * @var string Cache dir
     */
    private static $cacheDir;

    /**
     * @param $cacheDir
     */
    public function __construct($cacheDir = null)
    {
        static::$cacheDir = $cacheDir;
    }

    /**
     * {@inheritdoc}
     */
    public static function uglify($content/*, $options*/)
    {
        /** @var Packer\Packer $packer */
        $packer = new Packer\Packer($content, 'Normal', true, false, true);
        $content = $packer->pack();
        if (static::$cacheDir) {
            if (!file_exists(static::$cacheDir)) {
                mkdir(static::$cacheDir, 0777, true);
            }
            $hash = md5($content);
            $file = implode(DIRECTORY_SEPARATOR, [static::$cacheDir, $hash]);
            if (!file_exists($file)) {
                file_put_contents($file, $content/*\JShrink\Minifier::uglify($content, $options)*/);
            }

            return file_get_contents($file);
        }

        return $content/*\JShrink\Minifier::uglify($content, $options)*/;
    }
}
