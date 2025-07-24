<?php

namespace UniSharp\LaravelFilemanager;

use Illuminate\Support\Facades\Storage;

class LfmStorageRepository
{
    private $disk;
    private $path;
    private $helper;

    public function __construct($storage_path, $helper)
    {
        $this->helper = $helper;
        $this->disk = Storage::disk($this->helper->config('disk'));
        $this->path = $storage_path;
    }

    public function __call($function_name, $arguments)
    {
        // TODO: check function exists
        return $this->disk->$function_name($this->path, ...$arguments);
    }

    public function rootPath(): string
    {
        return $this->disk->path('');
    }

    public function move($new_lfm_path): bool
    {
        return $this->disk->move($this->path, $new_lfm_path->path('storage'));
    }

    public function save($file): void
    {
        $nameint = strrpos($this->path, "/");
        $nameclean = substr($this->path, $nameint + 1);
        $pathclean = substr_replace($this->path, "", $nameint);
        $this->disk->putFileAs($pathclean, $file, $nameclean, 'public');
    }

    public function url($path): string
    {
        return $this->disk->url($path);
    }

    public function makeDirectory(): void
    {
        $this->disk->makeDirectory($this->path, ...func_get_args());

        // some filesystems (e.g. Google Storage, S3?) don't let you set ACLs on directories (because they don't exist)
        // https://cloud.google.com/storage/docs/naming#object-considerations
        if ($this->disk->has($this->path)) {
            $this->disk->setVisibility($this->path, 'public');
        }
    }

    public function extension(): array|string
    {
        setlocale(LC_ALL, 'en_US.UTF-8');
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }
}
