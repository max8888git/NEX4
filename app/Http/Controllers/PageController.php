<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    /**
     * Role folders in the URL and the shared view group each one renders.
     *
     * Pages that every role in a group shares live once in
     * resources/views/roles/{group}; role differences are @if ($role === ...)
     * blocks inside them. A file under resources/views/pages/{folder} still
     * wins, for the few pages that are genuinely different per role.
     */
    private const ROLE_GROUPS = [
        'admin' => 'staff',
        'super-admin' => 'staff',
        'viewing' => 'viewer',
        'viewing-with-approval' => 'viewer',
        'viewing-program-head' => 'viewer',
    ];

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
        // role-specific pages get $role too (the shared sidebar needs it)
        $folder = explode('/', $path, 2)[0];
        $data = isset(self::ROLE_GROUPS[$folder]) ? ['role' => $folder] : [];
        if (! view()->exists($name)) {
            [$name, $data] = $this->roleView($path);
        }

        return response(view($name, $data)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function roleView(string $path): array
    {
        [$role, $page] = array_pad(explode('/', $path, 2), 2, '');
        $group = self::ROLE_GROUPS[$role] ?? null;
        if ($group === null || $page === '') {
            abort(404);
        }
        $name = 'roles.'.$group.'.'.str_replace('/', '.', $page);
        if (! view()->exists($name)) {
            abort(404);
        }

        return [$name, ['role' => $role]];
    }
}
