<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegacyApiController extends Controller
{
    public function handle(Request $request, string $script): Response
    {
        $name = basename($script);
        if (! preg_match('/^[A-Za-z0-9_-]+\.php$/', $name)) {
            return response()->json(['ok' => false, 'error' => 'Unknown API script.'], 404);
        }
        $root = rtrim((string) config('registrar.legacy_api'), '/\\');
        $path = $root.DIRECTORY_SEPARATOR.$name;
        if ($root === '' || ! is_file($path)) {
            return response()->json(['ok' => false, 'error' => 'That part of the registrar API is not available from Laravel yet.'], 404);
        }

        $dir = storage_path('app/legacy');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $id = bin2hex(random_bytes(8));
        $metaPath = $dir.DIRECTORY_SEPARATOR.'meta-'.$id.'.json';
        $outPath = $dir.DIRECTORY_SEPARATOR.'out-'.$id.'.json';
        file_put_contents($metaPath, json_encode([
            'script' => $path,
            'method' => $request->getMethod(),
            'get' => $request->query(),
            'cookie' => $request->cookies->all(),
            'out' => $outPath,
            'save' => storage_path('framework/sessions'),
        ], JSON_UNESCAPED_UNICODE));

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, base_path('legacy-runner.php'), $metaPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );
        if (! is_resource($process)) {
            @unlink($metaPath);

            return response()->json(['ok' => false, 'error' => 'Could not run the registrar API.'], 500);
        }
        fwrite($pipes[0], $request->getContent());
        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $raw = is_file($outPath) ? file_get_contents($outPath) : false;
        @unlink($metaPath);
        @unlink($outPath);
        $result = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($result)) {
            return response()->json([
                'ok' => false,
                'error' => 'The registrar API did not return a response.',
                'detail' => trim((string) $stderr),
                'exit' => $exit,
            ], 500);
        }

        $response = response((string) ($result['body'] ?? ''), (int) ($result['code'] ?: 200));
        foreach ($result['headers'] ?? [] as $header) {
            if (! is_string($header) || ! str_contains($header, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $header, 2);
            $key = trim($key);
            if (strcasecmp($key, 'Set-Cookie') === 0) {
                $response->headers->set($key, trim($value), false);
            }
        }
        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        }

        return $response;
    }
}
