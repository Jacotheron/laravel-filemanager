<?php

namespace UniSharp\LaravelFilemanager\Controllers;

class DemoController extends LfmController
{
    public function index(): \Illuminate\View\View
    {
        return view('laravel-filemanager::demo');
    }
}
