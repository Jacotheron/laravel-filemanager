<?php

namespace UniSharp\LaravelFilemanager\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager as InterventionImageV3;
use UniSharp\LaravelFilemanager\Events\ImageIsCropping;
use UniSharp\LaravelFilemanager\Events\ImageWasCropped;

class CropController extends LfmController
{
    /**
     * Show crop page.
     *
     * @return mixed
     * @throws BindingResolutionException
     */
    public function getCrop(): mixed
    {
        return view('laravel-filemanager::crop')
            ->with([
                'working_dir' => request('working_dir'),
                'img' => $this->lfm->pretty(request('img'))
            ]);
    }

    /**
     * Crop the image (called via ajax).
     * @throws BindingResolutionException
     */
    public function getCropImage($overWrite = true): void
    {
        $image_name = request('img');
        $image_path = $this->lfm->setName($image_name)->path('absolute');
        $crop_path = $image_path;

        if (! $overWrite) {
            $fileParts = explode('.', $image_name);
            $fileParts[count($fileParts) - 2] .= '_cropped_' . time();
            $crop_path = $this->lfm->setName(implode('.', $fileParts))->path('absolute');
        }

        event(new ImageIsCropping($image_path));

        $crop_info = request()->only('dataWidth', 'dataHeight', 'dataX', 'dataY');

        // crop image

        $manager = new InterventionImageV3(
            new Driver()
        );
        $manager->read($image_path)
            ->crop(...array_values($crop_info))
            ->save($crop_path);

        // make new thumbnail
        $this->lfm->generateThumbnail($image_name);

        event(new ImageWasCropped($image_path));
    }

    /**
     * @throws BindingResolutionException
     */
    public function getNewCropImage(): void
    {
        $this->getCropimage(false);
    }
}
