<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use UniSharp\LaravelFilemanager\Lfm;
use UniSharp\LaravelFilemanager\LfmPath;

class LfmController extends Controller
{
    protected static string $success_response = 'OK';

    public function __construct()
    {
        $this->applyIniOverrides();
    }

    /**
     * Set up needed functions.
     *
     * @return object|null
     */
    public function __get($var_name)
    {
        if ($var_name === 'lfm') {
            return app(LfmPath::class);
        }

        if ($var_name === 'helper') {
            return app(Lfm::class);
        }
    }

    /**
     * Show the filemanager.
     *
     * @return mixed
     */
    public function show(): mixed
    {
        return view('laravel-filemanager::index')
            ->withHelper($this->helper);
    }

    /**
     * Check if any extension or config is missing.
     *
     * @return array
     */
    public function getErrors(): array
    {
        $arr_errors = [];

        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            $arr_errors[] = trans('laravel-filemanager::lfm.message-extension_not_found');
        }

        if (! extension_loaded('exif')) {
            $arr_errors[] = 'EXIF extension not found.';
        }

        if (! extension_loaded('fileinfo')) {
            $arr_errors[] = 'Fileinfo extension not found.';
        }

        $mine_config_key = 'lfm.folder_categories.'
            . $this->helper->currentLfmType()
            . '.valid_mime';

        if (! is_array(config($mine_config_key))) {
            $arr_errors[] = 'Config : ' . $mine_config_key . ' is not a valid array.';
        }

        return $arr_errors;
    }

    /**
     * Overrides settings in php.ini.
     *
     * @return null
     */
    private function applyIniOverrides(): void
    {
        $overrides = config('lfm.php_ini_overrides', []);

        if ($overrides && is_array($overrides) && count($overrides) === 0) {
            return;
        }

        foreach ($overrides as $key => $value) {
            if ($value && $value !== 'false') {
                ini_set($key, $value);
            }
        }
    }

    // TODO: remove this after refactoring RenameController and DeleteController
    protected function error($error_type, $variables = [])
    {
        return trans(Lfm::PACKAGE_NAME . '::lfm.error-' . $error_type, $variables);
    }
}
