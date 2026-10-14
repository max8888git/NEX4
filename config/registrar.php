<?php

return [
    // A blank LEGACY_API_PATH= (as .env.example ships it) means "use the
    // bundled folder"; env()'s default only applies when the key is missing.
    'legacy_api' => env('LEGACY_API_PATH') ?: base_path('legacy-api'),
];
