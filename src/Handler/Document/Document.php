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
    protected static $uploadDirectory = null;

    public static function setUploadDirectory($dir)
    {
        static::$uploadDirectory = $dir;
    }

    public static function getUploadDirectory()
    {
        if (static::$uploadDirectory === null) {
            throw new \RuntimeException('Trying to access upload directory for profile files');
        }

        return static::$uploadDirectory;
    }

    /**
     * Assumes 'type' => 'file'
     */
    public function setFile(HttpFoundation\File\File $file)
    {
        $this->file = $file;
    }

    public function getFile()
    {
        return new HttpFoundation\File\File(static::getUploadDirectory().'/'.$this->filePersistencePath);
    }

    public function getFilePersistencePath()
    {
        return $this->filePersistencePath;
    }

    public function processFile()
    {
        if (!($this->file instanceof HttpFoundation\File\UploadedFile)) {
            return false;
        }
        $this->filePersistencePath = $this->moveUploadedFile($this->file, static::getUploadDirectory());
    }

    public function moveUploadedFile(HttpFoundation\File\UploadedFile $file, $uploadBasePath)
    {
        $originalName = $file->getClientOriginalName();
        /*$originalNameParts = pathinfo($file->getClientOriginalName());
        $originalName = $originalNameParts['filename'].'_'.uniqid().'.'.$originalNameParts['extension'];*/

        // use filemtime() to have a more determenistic way to determine the subpath, otherwise its hard to test.
        $relativePath = date('Y-m', filemtime($this->file->getPath()));
        $targetFileName = $relativePath.DIRECTORY_SEPARATOR.$originalName;
        $targetFilePath = $uploadBasePath.DIRECTORY_SEPARATOR.$targetFileName;
        $ext = $this->file->getExtension();

        $i = 1;
        while (file_exists($targetFilePath) && md5_file($file->getPath()) !== md5_file($targetFilePath)) {
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
