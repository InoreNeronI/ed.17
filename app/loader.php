<?php

/** @author Martin Mozos <martinmozos@gmail.com> */
if (PHP_VERSION_ID < 50400) {
    /* @throw \Exception */
    throw new \Exception('At least PHP 5.4 is required; using the latest version is highly recommended.');
} elseif (is_file(__DIR__ . '/../vendor/autoload.php')) {
    /** @var \Composer\Autoload\ClassLoader $autoload */
    $autoload = require __DIR__ . '/../vendor/autoload.php';
    /**
     * @param string      $path
     * @param string|null $filename
     *
     * @return array
     */
    function parseConfig($path, $filename = null)
    {
        is_null($filename) ?: $path = "$path/$filename.yml";
        if (is_file($path)) {
            /** @var array $config */
            $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($path));
        } else {
            return [];
        }

        return $filename === 'parameters' ? $config[$filename] : $config;
    }
    // Require constants and return autoload
    try {
        require __DIR__ . '/define.php';
        /* @return \Composer\Autoload\ClassLoader */
        return $autoload;
    } catch (Exception $e) {
        /* @throw \Exception */
        throw new \Exception(sprintf('Internal error: %s', $e->getMessage()));
    }
} else {
    /* @throw \Exception */
    throw new \Exception('Vendor files not found, please run "Composer" dependency manager: https://getcomposer.org/');
}
