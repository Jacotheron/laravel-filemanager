<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends LfmController
{
    public function getDownload(): StreamedResponse
    {
        $file = $this->lfm->setName(request('file'));

        if (!Storage::disk($this->helper->config('disk'))->exists($file->path('storage'))) {
            abort(404);
        }

        return Storage::disk($this->helper->config('disk'))->download($file->path('storage'));
    }
}
