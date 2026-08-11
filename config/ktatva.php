<?php

return [
    'base_url' => env('KTATVA_STORAGE_BASE_URL', 'https://storage.ktatva.com/api/v1/storage'),
    'api_key' => env('KTATVA_STORAGE_API_KEY'),
    'bucket_id' => env('KTATVA_STORAGE_BUCKET_ID'),
    'prefix' => env('KTATVA_STORAGE_PREFIX', 'articles'),
    // Cache signed download URLs (seconds)
    // Signed URLs expire in ~15m — cache under that window
    'url_cache_ttl' => (int) env('KTATVA_STORAGE_URL_CACHE_TTL', 600),
];
