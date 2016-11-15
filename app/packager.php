<?php

/** @url https://gist.github.com/azihassan/3093972 */
define('USAGE', 'Usage: php ' . $argv[0] . ' --package package_name --path ' . __DIR__ . ' [--dependencies] [--overwrite] [--nocache] [--help]');

if ($argc == 1) {
    \packager::getUsage(USAGE);
}
\packager::getTazPkg($argv, 'cooking');

/**
 * Class packager
 */
class packager
{
    /** @var array */
    private static $arguments;

    /** @var string */
    private static $version;

    /** @var array */
    private static $versions = ['backports' => 'b', 'cooking' => 'c', 'stable' => 's', 'tiny' => 't', 'undigest' => 'u', 'first' => '1', 'second' => '2', 'third' => '3'];

    /**
     * @param string $usage
     */
    public static function getUsage($usage)
    {
        echo PHP_EOL . "\t" . $usage . PHP_EOL;
        exit;
    }

    /**
     * @param array  $links
     * @param string $target_dir
     * @param bool   $overwrite
     */
    public static function getHttpResource($links, $target_dir, $overwrite)
    {
        echo sprintf('%sFound %s package(s)%s', "\r\t\t\t", count($links), PHP_EOL);
        foreach ($links as $link) {
            if (is_array($link)) {
                $link = implode($link);
            }
            echo PHP_EOL;
            try {
                static::downloadFile($link, $target_dir, $overwrite);
            } catch (Exception $e) {
                echo sprintf('%s%s', "\t", $e->getMessage());
                continue;
            }
        }
        echo PHP_EOL;
    }

    /**
     * @param array  $arguments
     * @param string $version
     *
     * @throws Exception
     */
    public static function getTazPkg($arguments, $version = 'stable')
    {
        static::$arguments = $arguments;
        static::$version = $version;
        try {
            list($package_name, $target_dir, $dependencies, $help, $overwrite, $no_cache, $with_depends) = static::parseTazArguments();
            $dependencies = $dependencies || $with_depends;
            if ($help === true) {
                static::getUsage(USAGE);
            }
            echo PHP_EOL . 'Extracting the links...' . PHP_EOL;
            $src = static::getTazPkgsSource('http://pkgs.slitaz.org/search.sh', $package_name, $dependencies, $no_cache);
            $links = static::extractTazPkgsLinks($src, $dependencies ? null : $package_name);
            if (!empty($links)) {
                if ($with_depends) {
                    $src_parent = static::getTazPkgsSource('http://pkgs.slitaz.org/search.sh', $package_name, null, $no_cache);
                    array_unshift($links, static::extractTazPkgsLinks($src_parent));
                }
                static::getHttpResource($links, $target_dir, $overwrite);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    private static function parseTazArguments()
    {
        if ($help = in_array('--help', static::$arguments)) {
            return [null, null, null, true, null, null];
        }

        if (($key = array_search('--package', static::$arguments)) !== false) {
            /** @url http://stackoverflow.com/a/3766319 */
            list($package_name) = explode(' ', trim(static::$arguments[$key + 1]));
        } else {
            throw new Exception(sprintf('%s%sERROR: Argument `--package` is mandatory. Type --help for usage.', PHP_EOL, "\t"));
        }

        if (($key = array_search('--path', static::$arguments)) !== false) {
            $target_dir = static::$arguments[$key + 1];
            if (!is_writable($target_dir) && mkdir($target_dir) === false) {
                throw new Exception(sprintf('%s%sERROR: `%s` is not writable. Try again with another path or leave it empty for the current path.', PHP_EOL, "\t", $target_dir));
            }
        } else {
            throw new Exception(sprintf('%s%sERROR: Argument `--path` is mandatory%s%s%s.', PHP_EOL, "\t", PHP_EOL, "\t", USAGE));
        }

        $dependencies = in_array('--dependencies', static::$arguments);
        $overwrite = in_array('--overwrite', static::$arguments);
        $no_cache = in_array('--nocache', static::$arguments);
        $with_depends = in_array('--withdepends', static::$arguments);

        return [$package_name, $target_dir, $dependencies, $help, $overwrite, $no_cache, $with_depends];
    }

    /**
     * @param string $url
     * @param string $package_name
     * @param bool   $dependencies
     * @param bool   $no_cache
     *
     * @return string
     *
     * @throws Exception
     */
    private static function getTazPkgsSource($url, $package_name, $dependencies = false, $no_cache = false)
    {
        $ch = curl_init();
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $package_name . ($dependencies ? '_dep' : '') . '.tmp';
        $query = $url . '?';
        $query .= $dependencies ? 'depends=' . $package_name : 'package=' . $package_name;
        $query .= '&version=' . static::$versions[static::$version];

        if (is_readable($tmp) && $no_cache === false) {
            curl_close($ch);

            return file_get_contents($tmp);
        }

        curl_setopt($ch, CURLOPT_URL, $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:13.0) Gecko/20100101 Firefox/13.0.1');

        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception(sprintf('%s%sERROR: Failed to retrieve the source%s%s', PHP_EOL, "\t", "\t", $err));
        }
        curl_close($ch);
        file_put_contents($tmp, $result);

        return $result;
    }

    /**
     * @param string      $string
     * @param string|null $package_name
     *
     * @return array
     */
    private static function extractTazPkgsStrings($string, $package_name = null)
    {
        $links = [];
        $dom = new DOMDocument();
        $dom->recover = true;
        $dom->strictErrorChecking = false;
        @$dom->loadHTML($string);
        $xpath = new DOMXPath($dom);

        $query = empty($package_name) ? '//pre[1]/a/@href' : '//table[1]/tr/td[3]/a/@href';
        $extract = $xpath->query($query);
        $standalone = false;

        foreach ($extract as $key => $link) {
            if (!empty($package_name) && strpos($link->nodeValue, '?receipt=') === 0 && str_replace('?receipt=', '', $link->nodeValue) === $package_name) {
                $standalone = $key;
            }
            if (pathinfo($link->nodeValue, PATHINFO_EXTENSION) == 'tazpkg') {
                if ($standalone !== false) {
                    return [$extract[--$standalone]->nodeValue];
                }
                $links[] = $link->nodeValue;
            }
        }

        return $links;
    }

    /**
     * @param string      $string
     * @param string|null $package_name
     *
     * @return array
     */
    private static function extractTazPkgsLinks($string, $package_name = null)
    {
        $links = static::extractTazPkgsStrings($string, $package_name);

        if (empty($links) && empty($package_name)) {
            $package_arg_key = array_search('--package', static::$arguments) + 1;
            $package_name = static::$arguments[$package_arg_key];
            $nocache = array_search('--nocache', static::$arguments) !== false;
            $src = static::getTazPkgsSource('http://pkgs.slitaz.org/search.sh', $package_name, false, $nocache);
            $links = static::extractTazPkgsStrings($src, $package_name);

            if (!empty($links)) {
                if (count($links) === 1) {
                    return [$package_name => $links[0]];
                }
                $message = array_search('--withdepends', static::$arguments) !== false ?
                    'Which of the following matches and its\' dependencies do you want to be downloaded? Type a number to continue:' :
                    'Which of the following matches\' dependencies do you want to be downloaded? Type a number to continue:';
                $choice = self::beautifyAndPromptCandidates($links, $message);
                static::$arguments[$package_arg_key] = static::beautifyPkgName($links[$choice]);

                return static::getTazPkg(static::$arguments, static::$version);
            }
        } elseif (!empty($links) && !empty($package_name)) {
            if (count($links) === 1) {
                return [$package_name => $links[0]];
            }
            $choice = self::beautifyAndPromptCandidates($links, 'Which of the following matches do you want to be downloaded? Type a number to continue:');

            return [$links[$choice]];
        }

        if (empty($links)) {
            echo sprintf('%s%sERROR: No links for `%s` were found.', PHP_EOL, "\t", $package_name);
        }

        return $links;
    }

    /**
     * @param string $link
     * @param string $target_dir
     * @param bool   $overwrite
     *
     * @return mixed
     *
     * @throws Exception
     */
    private static function downloadFile($link, $target_dir, $overwrite)
    {
        $ch = curl_init();
        $filename = basename($link);
        $path = $target_dir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path) && !$overwrite) {
            throw new Exception(sprintf('NOTICE: Package `%s` already exists.', $filename));
        }

        if (($f = fopen($path, 'wb')) === false) {
            throw new Exception(sprintf('ERROR: Folder `%s` is not writable.', $path));
        }

        curl_setopt($ch, CURLOPT_URL, $link);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_FILE, $f);
        /** @var float $microtime */
        $microtime = microtime(true) * 1000;
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function (
            /** @url http://stackoverflow.com/a/13668885 */
            $clientp,       // this is an unchanged pointer as set with CURLOPT_PROGRESSDATA
            $dlnowdltotal,  // the total bytes to be downloaded (or 0 if not downloading)
            $dlnowdlnow,    // the current download bytecount (or 0 if not downloading)
            $dlnowultotal,  // the total bytes to be uploaded (or 0 if not uploading)
            $dlnowulnow)    // the current upload bytecount (or 0 if not uploading)
        use ($filename, $microtime) {
            static $calls = 0;
            if (++$calls % 4 != 0) {
                /* The rest of the code will be executed only 1/4 times
                 * This fixes a bug where the progress was displayed three times
                 * when it goes near 100% */
                return;
            }
            if ($dlnowdltotal != 0) {
                $percentage = round($dlnowdlnow / $dlnowdltotal, 2) * 100;
                $human_size = static::bytesToSize($dlnowdltotal);
                $milisecs_offset = microtime(true) * 1000 - $microtime;
                $speed = $dlnowdlnow / $milisecs_offset * 1000;
                $human_speed = static::bytesToSize($speed) . '/s';

                echo sprintf('%sDownloading...%s`%s`%s%s%sof %s%s[ %s ]%s', "\t", "\t", $filename, "\t", $percentage . '%', "\t", $human_size, "\t", $human_speed, "\t");
                if ($dlnowdlnow <= $dlnowdltotal) {
                    echo "\r";
                }
            }
        });

        $result = curl_exec($ch);
        $err = curl_error($ch);
        fclose($f);
        curl_close($ch);

        if ($result === false) {
            if (is_file($path) && filesize($path) === 0) {
                unlink($path);
            }
            throw new Exception(sprintf('ERROR: %s%sWhile trying to download `%s`', $err, "\t", $filename));
        }

        return $result;
    }

    /**
     * @param float $bytes
     * @param int   $precision
     *
     * @return string
     */
    private static function bytesToSize($bytes, $precision = 2)
    {
        // human readable format -- powers of 1024
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

        return @round(
                $bytes / pow(1024, ($i = floor(log($bytes, 1024)))), $precision
            ) . ' ' . $unit[$i];
    }

    /**
     * @param string $name
     *
     * @return int
     */
    private static function beautifyPkgName($name)
    {
        $pkg = basename($name);

        return substr_replace($pkg, '', strrpos($pkg, '-'));
    }

    /**
     * @param array  $links
     * @param string $message
     *
     * @return int
     */
    private static function beautifyAndPromptCandidates(array $links, $message = 'Type a number to continue:')
    {
        echo PHP_EOL . "\t" . $message . PHP_EOL;
        foreach ($links as $key => $link) {
            echo PHP_EOL . "\t\t[" . ($key + 1) . "]\t" . static::beautifyPkgName($link);
        }
        echo PHP_EOL . PHP_EOL . "\t" . 'Number: ';

        $handle = fopen('php://stdin', 'r');
        $line = fgets($handle);
        if (($id = intval(trim($line))) >= 1 && $id <= count($links)) {
            return --$id;
        }

        return static::beautifyAndPromptCandidates($links, 'Type correct number to continue:');
    }
}
