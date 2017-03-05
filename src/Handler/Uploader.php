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

    /** @var string
     */
    private $filePersistencePath;

    /** @var string */
    protected static $uploadDirectory = UPLOADS_DIR;

    /**
     * @param $dir
     */
    public static function setUploadDirectory($dir)
    {
        static::$uploadDirectory = $dir;
    }

    /**
     * @return string
     */
    public static function getUploadDirectory()
    {
        if (static::$uploadDirectory === null) {
            throw new \RuntimeException('Trying to access upload directory for profile files');
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
     * @return HttpFoundation\File\File
     */
    public function getFile()
    {
        return new HttpFoundation\File\File(static::getUploadDirectory().DIRECTORY_SEPARATOR.$this->filePersistencePath);
    }

    /**
     * @return string
     */
    public function getFilePersistencePath()
    {
        return $this->filePersistencePath;
    }

    /**
     * @return bool
     */
    public function processFile()
    {
        if (!($this->file instanceof HttpFoundation\File\UploadedFile)) {
            return false;
        }
        $this->filePersistencePath = $this->moveUploadedFile($this->file, realpath(static::getUploadDirectory()));
    }

    /**
     * @param HttpFoundation\File\UploadedFile $file
     * @param $uploadBasePath
     *
     * @return mixed
     */
    public function moveUploadedFile(HttpFoundation\File\UploadedFile $file, $uploadBasePath)
    {
        $originalName = $file->getClientOriginalName();
        // use filemtime() to have a more determenistic way to determine the subpath, otherwise its hard to test.
        $relativePath = date('Y-m', filemtime($this->file->getPath()));
        $targetDir = $uploadBasePath.DIRECTORY_SEPARATOR.$relativePath;
        $targetFilePath = $targetDir.DIRECTORY_SEPARATOR.$originalName;
        if (!is_dir($targetDir) && !mkdir($targetDir, umask(), true)) {
            throw new \RuntimeException('Could not create target directory to move temporary file into.');
        } elseif (file_exists($targetFilePath) && md5_file($file->getPathName()) !== md5_file($targetFilePath)) {
            $newFilename = basename($originalName).'_'.uniqid();
            $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
            $originalName = empty($fileExtension) ? $newFilename : $newFilename.'.'.$fileExtension;
        }
        $file->move($targetDir, $originalName);

        return $relativePath.DIRECTORY_SEPARATOR.$originalName;
    }
}
