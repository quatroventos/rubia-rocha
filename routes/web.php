<?php

use App\Http\Controllers\OgCaptureController;
use Illuminate\Support\Facades\Route;

Route::get('/__og/{token}', OgCaptureController::class)->where('token', '[A-Za-z0-9]{40}')->name('og-capture');
