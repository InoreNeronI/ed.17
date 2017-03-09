<?php

namespace App\Handler;

use Symfony\Component\HttpFoundation;

/**
 * Class Uploader
 *
 * @see https://gist.github.com/beberlei/978346
 */
class Uploader
{
    /** @var HttpFoundation\File\File - not a persistent field! */
    private $file;

    /** @var array */
    private $fileDuplicatePath = [];

    /** @var array */
    private $filePersistencePath = [];

    /** @var string */
    protected static $uploadDirectory = UPLOADS_DIR.DIRECTORY_SEPARATOR.'tmp';

    /** @param $dir */
    public function setUploadDirectory($dir)
    {
        static::$uploadDirectory = $dir;
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getUploadDirectory()
    {
        error_log('upload dir: ');
        error_log(print_r(static::$uploadDirectory,1));
        static::$uploadDirectory = realpath(static::$uploadDirectory);
        error_log('upload dir: ');
        error_log(print_r(static::$uploadDirectory,1));
        if (!is_dir(static::$uploadDirectory) && !mkdir(static::$uploadDirectory, 0755, true)) {
            throw new \RuntimeException('Trying to access to invalid upload directory path');
        }

        return static::$uploadDirectory;
    }

    /**
     * @param HttpFoundation\File\File $file
     */
    public function setFile(HttpFoundation\File\File $file)
    {
        $this->file = $file;
    }

    /**
     * @return array
     */
    public function getFileDuplicatePath()
    {
        return $this->fileDuplicatePath;
    }

    /**
     * @return array
     */
    public function getFilePersistencePath()
    {
        return $this->filePersistencePath;
    }

    /**
     * @param string $clientIp
     * @param string $user
     * @param string $time
     * @param string $token
     *
     * @return bool
     *
     * @throws HttpFoundation\File\Exception\FileException|\RuntimeException
     */
    public function processFile($clientIp, $user, $time, $token)
    {
        if (!($this->file instanceof HttpFoundation\File\UploadedFile)) {
            throw new HttpFoundation\File\Exception\FileException($this->file);
        }
        $targetDir = $this->getUploadDirectory();
        if (!is_dir($targetDir) && !mkdir($targetDir, umask(), true)) {
            throw new \RuntimeException('Could not create target directory to move temporary file into.');
        }
        $result = $this->doMove($this->file, $targetDir, $clientIp, $user, $time, $token);

        return $result;
    }

    /**
     * @param HttpFoundation\File\UploadedFile $file
     *
     * @return bool
     */
    private function isNewFile(HttpFoundation\File\UploadedFile $file)
    {
        $mimeType = $this->file->getMimeType();
        if ($mimeType === 'text/plain') {
            return true;
        } elseif ($mimeType === 'application/zip') {
            $count = 0;
            foreach (static::getTargetDirs($this->getUploadDirectory()) as $dir) {
                $files = static::getTargetFiles($dir);
                $count += count($files);
                foreach ($files as $file) {
                    if (md5_file($this->file->getPathname()) === md5_file($file)) {
                        $this->fileDuplicatePath[] = [md5($file) => realpath($file)];
                    } else {
                        --$count;
                    }
                }
            }
            //dump($count);

            return $count === 0 ? true : false;
        }

        return false;
    }

    /**
     * @param HttpFoundation\File\UploadedFile $uploadedFile
     * @param string                           $targetDir
     * @param string                           $clientIp
     * @param string                           $user
     * @param string                           $time
     * @param string                           $token
     *
     * @return string|false
     */
    private function doMove(HttpFoundation\File\UploadedFile $uploadedFile, $targetDir, $clientIp, $user, $time, $token): string
    {
        $targetDir .= DIRECTORY_SEPARATOR.date('Y-m-d+H-i-s', $time).'+'.$clientIp.'+'.$user.'+'.$token;
        $originalName = $uploadedFile->getClientOriginalName();
        $file = $targetDir.DIRECTORY_SEPARATOR.$originalName;
        //dump($file);
        if ($this->isNewFile($uploadedFile) && !in_array(md5($file), array_keys($this->fileDuplicatePath))) {
            if (is_file($file)) {
                return uniqid();
            }
            $uploadedFile->move($targetDir, $originalName);
            $this->filePersistencePath[] = [md5($file) => $file];

            return 1;
        }

        return 0;
    }

    /**
     * @return array
     */
    public function doPurge(): array
    {
        /** @var array $result */
        $purges = [];
        foreach (static::getTargetDirs(static::getUploadDirectory()) as $dir) {
            $files = static::getTargetFiles($dir);
            //dump(count($files));
            if (count($files) === 1 && ($file = realpath($files[0])) && mime_content_type($file) === 'text/plain' && unlink($file) && $folder = dirname($file)) {
                $exists = false;
                foreach ($this->getFilePersistencePath() as $key => $fresh) {
                    if (strpos(array_pop($fresh), $folder)) {
                        unset($this->filePersistencePath[$key]);
                        $exists = true;
                    }
                }
                if (!$exists) {
                    foreach ($this->getFileDuplicatePath() as $key => $dupe) {
                        //dump(array_pop($fresh));
                        /*dump($fresh);
                        dump(array_pop($fresh));
                        dump($folder);*/
                        if (!empty($fresh) && strpos(array_pop($fresh), $folder)) {
                            unset($this->fileDuplicatePath[$key]);
                            $exists = true;
                        }
                    }
                    if (!$exists) {
                        array_push($purges, $file);
                    }
                }
            }
        }

        return ['fresh' => $this->getFilePersistencePath(), 'dupe' => $this->getFileDuplicatePath(), 'garb' => $purges];
    }

    /**
     * @param $dir
     *
     * @return array
     */
    private function getTargetDirs($dir): array
    {
        return array_merge([$dir], glob($dir.'/*', GLOB_ONLYDIR));
    }

    /**
     * @param $dir
     *
     * @return array|null
     */
    private static function getTargetFiles($dir)
    {
        return array_filter(glob($dir.'/*'), 'is_file');
    }

    /**
     * @return string
     */
    public static function publishUploadDirectory()
    {
        $filename = static::zipUploadDirectory(static::getUploadDirectory());

        return '<a target="_blank" href="'.$filename.'">'.$filename.'</a>';
    }

    /**
     * @param string $srcDir
     * @param string $targetDir
     *
     * @return string
     */
    public static function zipUploadDirectory($srcDir, $targetDir = PUBLIC_DIR)
    {
        // Get real path for our folder
        $rootPath = realpath($srcDir);
        $filename = date('Y-m-d+H-i-s', time()).'_'.(uniqid()).'.zip';

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($targetDir.DIRECTORY_SEPARATOR.$filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var \SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $total = 0;
        foreach ($files as $name => $file) {
            $path = realpath($file);
            if (empty($path)) {
                continue;
            }
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                if ($zip->addFile($filePath, $relativePath)) {
                    ++$total;
                }
            }
        }
        // Zip archive will be created only after closing object
        if ($total) {
            $set = $zip->setPassword('r11t17');
        }
        $zip->close();

        return $total ? $filename : '';
    }
}
