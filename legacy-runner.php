<?php

$metaPath = $argv[1] ?? '';
$meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
if (! is_array($meta) || empty($meta['script']) || empty($meta['out'])) {
    fwrite(STDERR, "Missing legacy request.\n");
    exit(1);
}

if (! empty($meta['save'])) {
    if (! is_dir($meta['save'])) {
        mkdir($meta['save'], 0775, true);
    }
    session_save_path($meta['save']);
}

$_SERVER['REQUEST_METHOD'] = (string) ($meta['method'] ?? 'GET');
$_GET = is_array($meta['get'] ?? null) ? $meta['get'] : [];
$_COOKIE = is_array($meta['cookie'] ?? null) ? $meta['cookie'] : [];
$GLOBALS['MCU_RAW_BODY'] = stream_get_contents(STDIN);
if (! is_string($GLOBALS['MCU_RAW_BODY'])) {
    $GLOBALS['MCU_RAW_BODY'] = '';
}

ob_start();
register_shutdown_function(function () use ($meta): void {
    $body = '';
    if (ob_get_level() > 0) {
        $body = (string) ob_get_clean();
    }
    file_put_contents($meta['out'], json_encode([
        'code' => http_response_code() ?: 200,
        'headers' => headers_list(),
        'body' => $body,
    ], JSON_UNESCAPED_UNICODE));
});

require $meta['script'];
