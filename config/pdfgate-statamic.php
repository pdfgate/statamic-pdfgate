<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entries
    |--------------------------------------------------------------------------
    |
    | "source" decides what PDFGate renders when an entry is exported.
    |
    |   url   PDFGate loads the entry's public URL, so the site must be
    |         reachable from the internet and the entry must be published.
    |   html  Statamic renders the entry's template and sends the HTML.
    |         Works for drafts and for sites that are not public. Stylesheets
    |         and images are still loaded from APP_URL by PDFGate.
    |
    | "options" are passed to the generate PDF endpoint on every export.
    | See https://pdfgate.com/documentation for the full list.
    |
    | "download_route" registers GET /pdfgate/entries/{id}, used by the
    | {{ pdfgate:download_url }} tag. Links are signed, so only URLs created
    | by the tag work. "throttle" limits requests per minute per IP.
    |
    */

    'entries' => [
        'source' => env('PDFGATE_STATAMIC_SOURCE', 'url'),
        'options' => [
            'pageSizeType' => 'a4',
            'printBackground' => true,
        ],
        'download_route' => true,
        'throttle' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | PDF assets are uploaded to PDFGate, processed, and the result is
    | written back to the asset container, either replacing the file or
    | saved as a new asset next to it. The editor picks this per run.
    |
    */

    'assets' => [
        'compress' => [
            'linearize' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote documents
    |--------------------------------------------------------------------------
    |
    | Every export and asset operation creates documents in your PDFGate
    | account. When true, they are deleted as soon as the file has been
    | delivered to the browser or written back to Statamic.
    |
    */

    'delete_remote_documents' => true,

];
