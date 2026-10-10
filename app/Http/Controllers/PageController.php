<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function show(?string $path = null)
    {
        $path = str_replace('\\', '/', (string) ($path ?? 'index'));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }
        if (str_ends_with(strtolower($path), '.html')) {
            $path = substr($path, 0, -5);
        }
        $name = 'pages.'.str_replace('/', '.', $path);
        if (! view()->exists($name)) {
            abort(404);
        }

        return response(view($name)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
