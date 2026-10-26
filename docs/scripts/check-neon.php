<?php

declare(strict_types=1);

// Parses every neon/yaml code block in the docs and every .neon file in
// examples/ with nette/neon, so a broken config snippet fails the build.
// Run from docs/ after `composer install` in the repository root.

require __DIR__ . '/../../vendor/autoload.php';

use Nette\Neon\Neon;

$root = dirname(__DIR__);
$failed = 0;
$checked = 0;

$examples = dirname(__DIR__, 2) . '/examples';
$iterator = new AppendIterator();
$iterator->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)));
$iterator->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($examples, FilesystemIterator::SKIP_DOTS)));
$docBlocks = 0;
$merged = 0;

// The config/api.neon from Getting started; every guide snippet is pasted into
// it below, so a snippet with other indentation than the skeleton (tabs) fails.
$gs = file_get_contents($root . '/guide/getting-started.md');
preg_match('~^```yaml \[config/api\.neon\]\n(.*?)^```~ms', $gs, $gsMatch) || exit("config/api.neon block not found in guide/getting-started.md\n");
$apiNeon = $gsMatch[1];

$exampleFiles = 0;
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if (preg_match('~/(vendor|node_modules|\.vitepress/(cache|dist))/~', $path)) {
        continue;
    }
    $inExamples = str_starts_with($path, $examples . '/');
    if ($inExamples && !str_ends_with($path, '.neon')) {
        continue;
    }
    $blocks = [];
    if (str_ends_with($path, '.md')) {
        preg_match_all('~^```(?:neon|ya?ml)\b[^\n]*\n(.*?)^```~ms', file_get_contents($path), $m, PREG_OFFSET_CAPTURE);
        foreach ($m[1] as [$code, $offset]) {
            $line = substr_count(file_get_contents($path), "\n", 0, $offset) + 1;
            $blocks[] = [$code, $line];
        }
    } elseif (str_ends_with($path, '.neon')) {
        $blocks[] = [file_get_contents($path), 1];
    }
    if ($inExamples) {
        $exampleFiles += count($blocks);
    } else {
        $docBlocks += count($blocks);
    }
    foreach ($blocks as [$code, $line]) {
        $checked++;
        try {
            Neon::decode($code);
            if (preg_match('~^ +\S~m', $code)) {
                throw new RuntimeException('indented with spaces; use tabs like the nette/web-project skeleton');
            }
            if (!$inExamples && str_contains($path, '/guide/')) {
                // Rename top-level keys so they do not collide, then parse both as one file.
                Neon::decode($apiNeon . "\n" . preg_replace('~^(\w+):~m', '$1_snippet:', $code));
                $merged++;
            }
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, substr($path, strlen(dirname($root)) + 1) . ":$line: " . $e->getMessage() . "\n");
        }
    }
}

echo ($failed ? 'FAIL' : 'ok') . " $checked parsed ($docBlocks docs blocks, $exampleFiles example files), $merged merged into getting-started config/api.neon, $failed failing\n";
exit($failed ? 1 : 0);
