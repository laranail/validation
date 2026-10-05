<?php

declare(strict_types=1);

/**
 * Every Illuminate component that src/ uses is declared in `require`.
 *
 * Testbench installs the whole framework, so an undeclared component passes every test here and
 * fails only for a consumer whose install lacks it. "Uses" is read three ways, because a facade or a
 * helper reaches a component without importing it:
 *
 * - an `Illuminate\<Component>\...` name, imported or fully qualified;
 * - a facade from `Illuminate\Support\Facades`, mapped to the component behind it;
 * - a global helper. Every global helper (`config()`, `app()`, `__()`, `now()`, `report()`, ...) is
 *   defined in `Illuminate/Foundation/helpers.php`, which only `laravel/framework` autoloads, so a
 *   helper call needs `laravel/framework` as well as the component it reaches.
 *
 * `Illuminate\Foundation` has no split package, so its declaration is `laravel/framework`, which
 * "replaces" every `illuminate/*` split and so is consistent with requiring them too (estate-followups
 * decision D3). A line carrying a `class_exists()`-style guard is skipped: that use is optional.
 * Measured 2026-10-05 (tooling-hygiene audit H6).
 */

/** @var array<string, string> facade => package */
const VALIDATION_FACADES = [
    'App'         => 'illuminate/container', 'Artisan' => 'illuminate/console', 'Auth' => 'illuminate/auth',
    'Blade'       => 'illuminate/view', 'Broadcast' => 'illuminate/broadcasting', 'Bus' => 'illuminate/bus',
    'Cache'       => 'illuminate/cache', 'Config' => 'illuminate/config', 'Context' => 'illuminate/log',
    'Cookie'      => 'illuminate/cookie', 'Crypt' => 'illuminate/encryption', 'Date' => 'illuminate/support',
    'DB'          => 'illuminate/database', 'Event' => 'illuminate/events', 'Facade' => 'illuminate/support',
    'File'        => 'illuminate/filesystem', 'Gate' => 'illuminate/auth', 'Hash' => 'illuminate/hashing',
    'Http'        => 'illuminate/http', 'Lang' => 'illuminate/translation', 'Log' => 'illuminate/log',
    'Mail'        => 'illuminate/mail', 'Notification' => 'illuminate/notifications', 'Password' => 'illuminate/auth',
    'Pipeline'    => 'illuminate/pipeline', 'Process' => 'illuminate/process', 'Queue' => 'illuminate/queue',
    'RateLimiter' => 'illuminate/cache', 'Redirect' => 'illuminate/routing', 'Redis' => 'illuminate/redis',
    'Request'     => 'illuminate/http', 'Response' => 'illuminate/routing', 'Route' => 'illuminate/routing',
    'Schedule'    => 'illuminate/console', 'Schema' => 'illuminate/database', 'Session' => 'illuminate/session',
    'Storage'     => 'illuminate/filesystem', 'URL' => 'illuminate/routing', 'Validator' => 'illuminate/validation',
    'View'        => 'illuminate/view', 'Vite' => 'laravel/framework',
];

/** @var array<string, string|null> helper => package it reaches (null: Foundation itself) */
const VALIDATION_HELPERS = [
    'abort'         => null, 'abort_if' => null, 'abort_unless' => null, 'app' => 'illuminate/container',
    'app_path'      => null, 'auth' => 'illuminate/auth', 'base_path' => null, 'bcrypt' => 'illuminate/hashing',
    'cache'         => 'illuminate/cache', 'config' => 'illuminate/config', 'config_path' => null,
    'database_path' => null, 'dispatch' => 'illuminate/bus', 'event' => 'illuminate/events',
    'info'          => 'illuminate/log', 'lang_path' => null, 'logger' => 'illuminate/log', 'now' => 'illuminate/support',
    'public_path'   => null, 'redirect' => 'illuminate/routing', 'report' => null, 'request' => 'illuminate/http',
    'rescue'        => null, 'resolve' => 'illuminate/container', 'resource_path' => null,
    'response'      => 'illuminate/routing', 'route' => 'illuminate/routing', 'session' => 'illuminate/session',
    'storage_path'  => null, 'to_route' => 'illuminate/routing', 'today' => 'illuminate/support',
    'trans'         => 'illuminate/translation', 'trans_choice' => 'illuminate/translation', '__' => 'illuminate/translation',
    'url'           => 'illuminate/routing', 'validator' => 'illuminate/validation', 'view' => 'illuminate/view',
];

/** Source with comments blanked out, line numbers kept: comments carry names that are not uses. */
function validationCodeWithoutComments(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (! is_array($token)) {
            $code .= $token;

            continue;
        }

        $code .= in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
            ? str_repeat("\n", substr_count($token[1], "\n"))
            : $token[1];
    }

    return $code;
}

/** @return list<string> packages one line of source reaches through an `Illuminate\...` name */
function validationNamedPackages(string $line): array
{
    preg_match_all('/Illuminate\\\\([A-Za-z]+)\\\\(?:Facades\\\\([A-Za-z]+))?/', $line, $names, PREG_SET_ORDER);

    $packages = [];

    foreach ($names as $name) {
        $facade = $name[2] ?? '';

        $packages[] = match (true) {
            $name[1] === 'Support' && $facade !== '' => VALIDATION_FACADES[$facade] ?? 'unmapped facade ' . $facade,
            $name[1] === 'Foundation'                => 'laravel/framework',
            default                                  => 'illuminate/' . strtolower($name[1]),
        };
    }

    return $packages;
}

/** @return list<string> global helpers one line of source calls */
function validationHelperCalls(string $line): array
{
    $bare = (string) preg_replace(["/'(?:\\\\.|[^'\\\\])*'/", '/"(?:\\\\.|[^"\\\\])*"/'], "''", $line);
    preg_match_all('/(?<![\w>:$\\\\])(?<!function )([a-z_]+)\s*\(/', $bare, $calls);

    return array_values(array_filter($calls[1], static fn (string $call): bool => array_key_exists($call, VALIDATION_HELPERS)));
}

/** @return array<string, list<string>> package => evidence ("file:line") */
function validationUsedIlluminatePackages(): array
{
    $root = dirname(__DIR__);
    $used = [];
    $files = 0;

    /** @var iterable<SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $files++;
        $relative = substr($file->getPathname(), strlen($root) + 1);

        foreach (explode("\n", validationCodeWithoutComments($file->getPathname())) as $index => $line) {
            // A guarded line is an optional use.
            if (preg_match('/\b(class|interface|trait|function)_exists\s*\(/', $line) === 1) {
                continue;
            }

            $where = $relative . ':' . ($index + 1);

            foreach (validationNamedPackages($line) as $package) {
                $used[$package][] = $where;
            }

            foreach (validationHelperCalls($line) as $helper) {
                $used['laravel/framework'][] = $where . ' (' . $helper . '())';

                if (VALIDATION_HELPERS[$helper] !== null) {
                    $used[VALIDATION_HELPERS[$helper]][] = $where . ' (' . $helper . '())';
                }
            }
        }
    }

    // Non-vacuity: a scan that found no source, or no use, proves nothing about it.
    expect($files)->toBeGreaterThanOrEqual(200)
        ->and(count($used))->toBeGreaterThanOrEqual(13);

    return $used;
}

it('declares every illuminate component that src/ uses', function (): void {
    /** @var array{require: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    $missing = [];

    foreach (validationUsedIlluminatePackages() as $package => $where) {
        if (! array_key_exists($package, $composer['require'])) {
            $missing[$package] = $where[0];
        }
    }

    expect($missing)->toBeEmpty('Used by src/ but not declared in require: ' . json_encode($missing, JSON_UNESCAPED_SLASHES));
});
