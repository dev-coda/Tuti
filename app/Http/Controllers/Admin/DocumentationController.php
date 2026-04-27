<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    private const GUIDE_ROOT = 'docs/guias';

    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const FOLDER_ORDER = [
        'b2b-tienda' => [
            'label' => 'Tienda B2B y flujo de compra',
            'description' => 'Búsqueda, carrito, registro, plazos de entrega, órdenes',
        ],
        'admin' => [
            'label' => 'Administración del panel',
            'description' => 'Catálogo, inventario, promociones, ajustes, reportes, etc.',
        ],
        'roles' => [
            'label' => 'Rol vendedor y módulo tendero',
            'description' => 'Rutas y buenas prácticas por rol',
        ],
    ];

    public function index()
    {
        $base = base_path(self::GUIDE_ROOT);
        if (! is_dir($base)) {
            return view('admin.documentation.index', [
                'sections' => [],
                'readmeFull' => null,
            ]);
        }

        $sections = [];
        foreach (self::FOLDER_ORDER as $folderName => $meta) {
            $dir = $base.DIRECTORY_SEPARATOR.$folderName;
            if (! is_dir($dir)) {
                continue;
            }
            $files = [];
            foreach (File::allFiles($dir) as $f) {
                if ($f->getExtension() !== 'md') {
                    continue;
                }
                $filename = $f->getFilename();
                $raw = File::get($f->getPathname());
                $rel = $folderName.'/'.$filename;
                $files[] = [
                    'path' => $rel,
                    'name' => $filename,
                    'title' => self::documentDisplayTitle($raw, $filename),
                ];
            }
            self::sortByFilename($files);
            if ($files !== []) {
                $sections[] = [
                    'id' => $folderName,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'files' => $files,
                ];
            }
        }

        $readmePath = $base.'/README.md';
        $readmeFull = File::isFile($readmePath)
            ? Str::markdown(File::get($readmePath), ['html_input' => 'allow'])
            : null;

        return view('admin.documentation.index', [
            'sections' => $sections,
            'readmeFull' => $readmeFull,
        ]);
    }

    public function show(Request $request)
    {
        $relative = (string) $request->query('f', '');
        if ($relative === '' || ! preg_match('#^[a-z0-9_/-]+\.md$#i', $relative) || str_contains($relative, '..')) {
            abort(404);
        }

        $full = self::allowedPath($relative);
        if ($full === null) {
            abort(404);
        }

        $raw = File::get($full);
        $html = Str::markdown($raw, ['html_input' => 'allow']);
        $h1 = self::firstH1($raw);
        $fallback = self::titleFromFilename(basename($relative, '.md'));

        return view('admin.documentation.show', [
            'pageTitle' => $h1 ?? $fallback,
            'slug' => $relative,
            'html' => $html,
        ]);
    }

    private static function firstH1(string $raw): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $raw, $m)) {
            return trim($m[1], " \t");
        }

        return null;
    }

    private static function documentDisplayTitle(string $raw, string $filename): string
    {
        $h1 = self::firstH1($raw);
        if ($h1) {
            return $h1;
        }

        return self::titleFromFilename(pathinfo($filename, PATHINFO_FILENAME));
    }

    private static function titleFromFilename(string $name): string
    {
        if (preg_match('/^(\d+)[\-_](.+)$/', $name, $m)) {
            $rest = str_replace(['-', '_'], ' ', $m[2]);

            return (int) $m[1].' — '.$rest;
        }

        return str_replace(['-', '_'], ' ', $name);
    }

    private static function sortByFilename(array &$items): void
    {
        usort($items, function (array $a, array $b) {
            $na = (string) $a['name'];
            $nb = (string) $b['name'];
            if (preg_match('/^(\d+)[\-_].+$/', $na, $ia) && preg_match('/^(\d+)[\-_].+$/', $nb, $ib)) {
                $d = (int) $ia[1] - (int) $ib[1];
                if ($d !== 0) {
                    return $d;
                }
            }

            return strnatcasecmp($na, $nb);
        });
    }

    private static function allowedPath(string $relative): ?string
    {
        $base = realpath(base_path(self::GUIDE_ROOT));
        if ($base === false) {
            return null;
        }
        $candidate = realpath($base.DIRECTORY_SEPARATOR.$relative);
        if ($candidate === false || ! is_file($candidate)) {
            return null;
        }
        if ($candidate !== $base && ! str_starts_with($candidate, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }
}
