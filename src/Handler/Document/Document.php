<?php

namespace App\Handler\Document;

use Symfony\Component\HttpFoundation;

/**
 * Class Document
 *
 * @see https://gist.github.com/beberlei/978346
 */
class Document
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
        $this->filePersistencePath = $this->moveUploadedFile($this->file, static::getUploadDirectory());
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
        $targetFileName = $relativePath.DIRECTORY_SEPARATOR.$originalName;
        $targetFilePath = $uploadBasePath.DIRECTORY_SEPARATOR.$targetFileName;
        $ext = $this->file->getExtension();

        $i = 1;
        while (file_exists($targetFilePath) && md5_file($file->getPathName()) !== md5_file($targetFilePath)) {
            if ($ext) {
                $prev = $i === 1 ? '' : $i;
                $targetFilePath = $targetFilePath.str_replace($prev.$ext, $i++.$ext, $targetFilePath);
            } else {
                $targetFilePath = $targetFilePath.$i++;
            }
        }

        $targetDir = $uploadBasePath.DIRECTORY_SEPARATOR.$relativePath;
        if (!is_dir($targetDir)) {
            $ret = mkdir($targetDir, umask(), true);
            if (!$ret) {
                throw new \RuntimeException('Could not create target directory to move temporary file into.');
            }
        }
        $file->move($targetDir, basename($targetFilePath));

        return str_replace($uploadBasePath.DIRECTORY_SEPARATOR, '', $targetFilePath);
    }
}
