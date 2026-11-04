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
    | by the tag work, and drafts are never served. "throttle" limits requests
    | per minute per IP. "cache_minutes" keeps a rendered PDF and serves it
    | again to other visitors for that long, so a popular page does not spend
    | one PDFGate conversion per click; 0 renders on every request. Only url
    | renders are cached: html renders run inside the visitor's request and
    | may contain their data, and protected entries are never cached.
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
        'cache_minutes' => 60,
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
