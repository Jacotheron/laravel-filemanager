<?php

namespace UniSharp\LaravelFilemanager;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use UniSharp\LaravelFilemanager\Exceptions\DuplicateFileNameException;
use UniSharp\LaravelFilemanager\Exceptions\EmptyFileException;
use UniSharp\LaravelFilemanager\Exceptions\ExcutableFileException;
use UniSharp\LaravelFilemanager\Exceptions\FileFailedToUploadException;
use UniSharp\LaravelFilemanager\Exceptions\FileSizeExceedConfigurationMaximumException;
use UniSharp\LaravelFilemanager\Exceptions\FileSizeExceedIniMaximumException;
use UniSharp\LaravelFilemanager\Exceptions\InvalidExtensionException;
use UniSharp\LaravelFilemanager\Exceptions\InvalidMimeTypeException;
use UniSharp\LaravelFilemanager\LfmPath;

class LfmUploadValidator
{
    private $file;

    public function __construct(UploadedFile $file)
    {
        // if (! $file instanceof UploadedFile) {
        //     throw new \Exception(trans(self::PACKAGE_NAME . '::lfm.error-instance'));
        // }

        $this->file = $file;
    }

    // public function hasContent()
    // {
    //     if (empty($this->file)) {
    //         throw new EmptyFileException();
    //     }

    //     return $this;
    // }

    /**
     * @throws FileSizeExceedIniMaximumException
     */
    public function sizeLowerThanIniMaximum(): static
    {
        if ($this->file->getError() === UPLOAD_ERR_INI_SIZE) {
            throw new FileSizeExceedIniMaximumException();
        }

        return $this;
    }

    /**
     * @throws FileFailedToUploadException
     */
    public function uploadWasSuccessful(): static
    {
        if ($this->file->getError() !== UPLOAD_ERR_OK) {
            throw new FileFailedToUploadException($this->file->getError());
        }

        return $this;
    }

    /**
     * @throws DuplicateFileNameException
     */
    public function nameIsNotDuplicate($new_file_name, LfmPath $lfm_path): static
    {
        if ($lfm_path->setName($new_file_name)->exists()) {
            throw new DuplicateFileNameException();
        }

        return $this;
    }

    /**
     * @throws ExcutableFileException
     */
    public function mimetypeIsNotExcutable($excutable_mimetypes): static
    {
        $mimetype = $this->file->getMimeType();

        if (in_array($mimetype, $excutable_mimetypes, true)) {
            throw new ExcutableFileException();
        }

        return $this;
    }

    /**
     * @throws ExcutableFileException
     */
    public function extensionIsNotExcutable(): static
    {
        $extension = strtolower($this->file->getClientOriginalExtension());

        $excutable_extensions = ['php', 'html'];

        if (in_array($extension, $excutable_extensions)) {
            throw new ExcutableFileException();
        }

        if (str_starts_with($extension, 'php')) {
            throw new ExcutableFileException();
        }

        if (preg_match('/[a-z]html/', $extension) > 0) {
            throw new ExcutableFileException();
        }

        return $this;
    }

    /**
     * @throws InvalidMimeTypeException
     */
    public function mimeTypeIsValid($available_mime_types): static
    {
        $mimetype = $this->file->getMimeType();

        if (false === in_array($mimetype, $available_mime_types, true)) {
            throw new InvalidMimeTypeException($mimetype);
        }

        return $this;
    }

    /**
     * @throws InvalidExtensionException
     */
    public function extensionIsValid($disallowed_extensions): static
    {
        $extension = strtolower($this->file->getClientOriginalExtension());

        if (preg_match('/[^a-zA-Z0-9]/', $extension) > 0) {
            throw new InvalidExtensionException();
        }

        if (in_array($extension, $disallowed_extensions, true)) {
            throw new InvalidExtensionException();
        }

        return $this;
    }

    /**
     * @throws FileSizeExceedConfigurationMaximumException
     */
    public function sizeIsLowerThanConfiguredMaximum($max_size_in_kb): static
    {
        // size to kb unit is needed
        $file_size_in_kb = $this->file->getSize() / 1000;

        if ($file_size_in_kb > $max_size_in_kb) {
            throw new FileSizeExceedConfigurationMaximumException($file_size_in_kb);
        }

        return $this;
    }
}
