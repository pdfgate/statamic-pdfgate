<?php

use Illuminate\Support\Facades\Route;
use PdfGate\Statamic\Http\Controllers\EntryPdfController;

$throttle = (int) config('pdfgate-statamic.entries.throttle', 10);

Route::get('pdfgate/entries/{entry}', EntryPdfController::class)
    ->name('pdfgate.entries.pdf')
    ->middleware(['signed', 'throttle:' . $throttle . ',1']);
