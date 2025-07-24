<?php

namespace UniSharp\LaravelFilemanager;

use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use UniSharp\LaravelFilemanager\Middlewares\CreateDefaultFolder;
use UniSharp\LaravelFilemanager\Middlewares\MultiUser;

class Lfm
{
    const PACKAGE_NAME = 'laravel-filemanager';
    const DS = '/';

    protected Config|null $config;
    protected Request|null $request;

    public function __construct(Config|null $config = null, Request|null $request = null)
    {
        $this->config = $config;
        $this->request = $request;
    }

    public function getStorage($storage_path): LfmStorageRepository
    {
        return new LfmStorageRepository($storage_path, $this);
    }

    public function input($key): string
    {
        return $this->translateFromUtf8($this->request->input($key));
    }

    public function config($key)
    {
        return $this->config->get('lfm.' . $key);
    }

    /**
     * Get only the file name.
     *
     * @param  string  $path  Real path of a file.
     * @return string
     */
    public function getNameFromPath($path): string
    {
        return $this->utf8Pathinfo($path, 'basename');
    }

    public function utf8Pathinfo($path, $part_name): string
    {
        // XXX: all locale work-around for issue: utf8 file name got emptified
        // if there's no '/', we're probably dealing with just a filename
        // so just put an 'a' in front of it
        if (!str_contains($path, '/')) {
            $path_parts = pathinfo('a' . $path);
        } else {
            $path = str_replace('/', '/a', $path);
            $path_parts = pathinfo($path);
        }

        return substr($path_parts[$part_name], 1);
    }

    public function allowFolderType($type): bool
    {
        if ($type === 'user') {
            return $this->allowMultiUser();
        }

        return $this->allowShareFolder();
    }

    public function getCategoryName()
    {
        $type = $this->currentLfmType();

        return $this->config->get('lfm.folder_categories.' . $type . '.folder_name', 'files');
    }

    /**
     * Get current lfm type.
     *
     * @return string
     */
    public function currentLfmType(): string
    {
        $lfm_type = 'file';

        $request_type = lcfirst(Str::singular($this->input('type') ?: ''));
        $available_types = array_keys($this->config->get('lfm.folder_categories') ?: []);

        if (in_array($request_type, $available_types, true)) {
            $lfm_type = $request_type;
        }

        return $lfm_type;
    }

    public function getDisplayMode()
    {
        $type_key = $this->currentLfmType();
        $startup_view = $this->config->get('lfm.folder_categories.' . $type_key . '.startup_view');

        $view_type = 'grid';
        $target_display_type = $this->input('show_list') ?: $startup_view;

        if (in_array($target_display_type, ['list', 'grid'])) {
            $view_type = $target_display_type;
        }

        return $view_type;
    }

    public function getUserSlug(): string
    {
        $config = $this->config->get('lfm.private_folder_name');

        if (is_callable($config)) {
            return $config();
        }

        if (class_exists($config)) {
            return app()->make($config)->userField();
        }

        return auth()->user()->$config ?? '';
    }

    public function getRootFolder($type = null): string
    {
        if (is_null($type)) {
            $type = 'share';
            if ($this->allowFolderType('user')) {
                $type = 'user';
            }
        }

        if ($type === 'user') {
            $folder = $this->getUserSlug();
        } else {
            $folder = $this->config->get('lfm.shared_folder_name');
        }

        // the slash is for url, dont replace it with directory seperator
        return '/' . $folder;
    }

    public function getThumbFolderName()
    {
        return $this->config->get('lfm.thumb_folder_name');
    }

    public function getFileType($ext)
    {
        return $this->config->get("lfm.file_type_array.{$ext}", 'File');
    }

    public function availableMimeTypes()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.valid_mime');
    }

    public function shouldCreateCategoryThumb()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.thumb');
    }

    public function categoryThumbWidth()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.thumb_width');
    }

    public function categoryThumbHeight()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.thumb_height');
    }

    public function maxUploadSize()
    {
        return $this->config->get('lfm.folder_categories.' . $this->currentLfmType() . '.max_size');
    }

    public function getPaginationPerPage()
    {
        return $this->config->get("lfm.paginator.perPage", 30);
    }

    /**
     * Check if users are allowed to use their private folders.
     *
     * @return bool
     */
    public function allowMultiUser(): bool
    {
        $type_key = $this->currentLfmType();

        if ($this->config->has('lfm.folder_categories.' . $type_key . '.allow_private_folder')) {
            return $this->config->get('lfm.folder_categories.' . $type_key . '.allow_private_folder') === true;
        }

        return $this->config->get('lfm.allow_private_folder') === true;
    }

    /**
     * Check if users are allowed to use the shared folder.
     * This can be disabled only when allowMultiUser() is true.
     *
     * @return bool
     */
    public function allowShareFolder(): bool
    {
        if (! $this->allowMultiUser()) {
            return true;
        }

        $type_key = $this->currentLfmType();

        if ($this->config->has('lfm.folder_categories.' . $type_key . '.allow_shared_folder')) {
            return $this->config->get('lfm.folder_categories.' . $type_key . '.allow_shared_folder') === true;
        }

        return $this->config->get('lfm.allow_shared_folder') === true;
    }

    /**
     * Translate file name to make it compatible on Windows.
     *
     * @param string $input  Any string.
     * @return string
     */
    public function translateFromUtf8(string $input): string
    {
        if ($this->isRunningOnWindows()) {
            $input = iconv('UTF-8', mb_detect_encoding($input), $input);
        }

        return $input;
    }

    /**
     * Get directory seperator of current operating system.
     *
     * @return string
     */
    public function ds(): string
    {
        $ds = Lfm::DS;
        if ($this->isRunningOnWindows()) {
            $ds = '\\';
        }

        return $ds;
    }

    /**
     * Check current operating system is Windows or not.
     *
     * @return bool
     */
    public function isRunningOnWindows(): bool
    {
        return stripos(PHP_OS, 'WIN') === 0;
    }

    /**
     * Shorter function of getting localized error message..
     *
     * @param mixed $error_type Key of message in lang file.
     * @param mixed $variables Variables the message needs.
     * @return string
     * @throws Exception
     */
    public function error(mixed $error_type, mixed $variables = []): string
    {
        throw new RuntimeException(trans(self::PACKAGE_NAME . '::lfm.error-' . $error_type, $variables));
    }

    /**
     * Generates routes of this package.
     *
     * @return void
     */
    public static function routes(): void
    {
        $middleware = [ CreateDefaultFolder::class, MultiUser::class ];
        $as = 'unisharp.lfm.';
        $namespace = '\\UniSharp\\LaravelFilemanager\\Controllers\\';

        Route::group(compact('middleware', 'as', 'namespace'), static function () {
            // display main layout
            Route::get('/', [
                'uses' => 'LfmController@show',
                'as' => 'show',
            ]);

            // display integration error messages
            Route::get('/errors', [
                'uses' => 'LfmController@getErrors',
                'as' => 'getErrors',
            ]);

            // upload
            Route::any('/upload', [
                'uses' => 'UploadController@upload',
                'as' => 'upload',
            ]);

            // list images & files
            Route::get('/jsonitems', [
                'uses' => 'ItemsController@getItems',
                'as' => 'getItems',
            ]);

            Route::get('/move', [
                'uses' => 'ItemsController@move',
                'as' => 'move',
            ]);

            Route::get('/domove', [
                'uses' => 'ItemsController@doMove',
                'as' => 'doMove'
            ]);

            // folders
            Route::get('/newfolder', [
                'uses' => 'FolderController@getAddfolder',
                'as' => 'getAddfolder',
            ]);

            // list folders
            Route::get('/folders', [
                'uses' => 'FolderController@getFolders',
                'as' => 'getFolders',
            ]);

            // crop
            Route::get('/crop', [
                'uses' => 'CropController@getCrop',
                'as' => 'getCrop',
            ]);
            Route::get('/cropimage', [
                'uses' => 'CropController@getCropImage',
                'as' => 'getCropImage',
            ]);
            Route::get('/cropnewimage', [
                'uses' => 'CropController@getNewCropImage',
                'as' => 'getNewCropImage',
            ]);

            // rename
            Route::get('/rename', [
                'uses' => 'RenameController@getRename',
                'as' => 'getRename',
            ]);

            // scale/resize
            Route::get('/resize', [
                'uses' => 'ResizeController@getResize',
                'as' => 'getResize',
            ]);
            Route::get('/doresize', [
                'uses' => 'ResizeController@performResize',
                'as' => 'performResize',
            ]);
            Route::get('/doresizenew', [
                'uses' => 'ResizeController@performResizeNew',
                'as' => 'performResizeNew',
            ]);
            // download
            Route::get('/download', [
                'uses' => 'DownloadController@getDownload',
                'as' => 'getDownload',
            ]);

            // delete
            Route::get('/delete', [
                'uses' => 'DeleteController@getDelete',
                'as' => 'getDelete',
            ]);

            Route::get('/demo', 'DemoController@index');
        });
    }
}
