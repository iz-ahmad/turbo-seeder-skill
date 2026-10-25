#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Regenerates the mechanical API blocks in references/*.md from a checkout of
 * iz-ahmad/laravel-turbo-seeder. Maintainer tooling; not shipped to agents.
 *
 * php scripts/generate-api.php --package=/path/to/package [--check]
 *
 * Exit codes: 0 in sync (or written), 1 out of sync (--check only), 2 tool error.
 * Run with PHP 8.3 to match CI; type rendering can differ across PHP versions.
 * The package's config file is executed, so only point this at trusted code.
 */

const NAMESPACE_PREFIX = 'IzAhmad\\TurboSeeder\\';

const UNDOCUMENTED_METHODS = [
    'Builder\\TurboSeederBuilder' => ['option', 'options', 'toConfiguration', 'getTable', 'getColumns', 'getCount', 'getStrategy', 'getOptions'],
    'Helpers\\TurboData' => ['resetNowOnce', 'resetHashedPasswords', 'reset', 'markGeneratorActive', 'isInsideGenerator'],
];

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'generate-api failed: '.$e->getMessage()."\n");
    exit(2);
});

$options = getopt('', ['package:', 'check']);
$package = isset($options['package']) ? rtrim((string) $options['package'], '/') : '';
$check = array_key_exists('check', $options);

if ($package === '' || ! is_file("{$package}/src/Builder/TurboSeederBuilder.php")) {
    fwrite(STDERR, "Usage: php scripts/generate-api.php --package=/path/to/laravel-turbo-seeder [--check]\n");
    exit(2);
}

spl_autoload_register(static function (string $class) use ($package): void {
    if (str_starts_with($class, NAMESPACE_PREFIX)) {
        $path = $package.'/src/'.str_replace('\\', '/', substr($class, strlen(NAMESPACE_PREFIX))).'.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
});

function storage_path(string $path = ''): string
{
    return 'storage_path/'.$path;
}

function base_path(string $path = ''): string
{
    return 'base_path/'.$path;
}

function env(string $key, mixed $default = null): mixed
{
    return $default;
}

function renderType(?ReflectionType $type): string
{
    return $type === null ? '' : (string) $type;
}

function renderValue(mixed $value): string
{
    return $value === null ? 'null' : str_replace(["\n", '  '], ' ', var_export($value, true));
}

function renderParameter(ReflectionParameter $parameter): string
{
    $type = renderType($parameter->getType());
    $text = $type !== '' ? $type.' ' : '';

    if ($parameter->isVariadic()) {
        $text .= '...';
    }

    $text .= '$'.$parameter->getName();

    if ($parameter->isDefaultValueAvailable()) {
        $default = $parameter->isDefaultValueConstant()
            ? (string) $parameter->getDefaultValueConstantName()
            : renderValue($parameter->getDefaultValue());

        $text .= ' = '.preg_replace('/^\\\\/', '', $default);
    }

    return $text;
}

function renderMethod(ReflectionMethod $method): string
{
    $parameters = implode(', ', array_map('renderParameter', $method->getParameters()));
    $return = renderType($method->getReturnType());

    return ($method->isStatic() ? 'static ' : '').$method->getName().'('.$parameters.')'.($return !== '' ? ': '.$return : '');
}

/**
 * @return array<int, string>
 */
function publicMethods(string $relativeClass): array
{
    $class = NAMESPACE_PREFIX.$relativeClass;
    $excluded = UNDOCUMENTED_METHODS[$relativeClass] ?? [];
    $methods = [];

    foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class || in_array($method->getName(), $excluded, true)) {
            continue;
        }

        $methods[] = renderMethod($method);
    }

    return $methods;
}

/**
 * @param  array<string, mixed>  $config
 * @return array<int, string>
 */
function flattenConfig(array $config, string $prefix = ''): array
{
    $lines = [];

    foreach ($config as $key => $value) {
        $path = $prefix.$key;

        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            array_push($lines, ...flattenConfig($value, $path.'.'));

            continue;
        }

        $rendered = renderValue($value);

        if (is_string($value) && str_starts_with($value, 'storage_path/')) {
            $rendered = "storage_path('".substr($value, strlen('storage_path/'))."')";
        }

        $lines[] = $path.' = '.str_replace(["\n", '  '], ' ', $rendered);
    }

    return $lines;
}

/**
 * @return array<int, string>
 */
function commandSignatures(string $package): array
{
    $signatures = [];
    $files = glob($package.'/src/Commands/*.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        if (preg_match('/\$(?:signature|name)\s*=\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s', $source, $match) !== 1) {
            continue;
        }

        $signatures[] = trim((string) preg_replace('/\s+/', ' ', $match[1]));
    }

    return $signatures;
}

/**
 * @return array<string, array<int, string>>
 */
function blocks(string $package): array
{
    $config = (static fn (): array => require $package.'/config/turbo-seeder.php')();

    return [
        'builder-methods' => publicMethods('Builder\\TurboSeederBuilder'),
        'turbodata-methods' => publicMethods('Helpers\\TurboData'),
        'config-keys' => flattenConfig($config),
        'commands' => commandSignatures($package),
    ];
}

function replaceBlock(string $content, string $name, string $body): string
{
    $pattern = '/(<!-- BEGIN GENERATED:'.preg_quote($name, '/').' -->\n).*?(<!-- END GENERATED:'.preg_quote($name, '/').' -->)/s';

    if (preg_match($pattern, $content) !== 1) {
        throw new RuntimeException("Marker block [{$name}] not found.");
    }

    return (string) preg_replace_callback($pattern, static fn (array $m): string => $m[1]."```\n{$body}\n```\n".$m[2], $content);
}

$targets = [
    'builder-api.md' => ['builder-methods', 'config-keys', 'commands'],
    'turbodata.md' => ['turbodata-methods'],
];

$generated = blocks($package);
$referencesDir = dirname(__DIR__).'/skills/laravel-turbo-seeder/references';
$drifted = [];

foreach ($targets as $file => $names) {
    $path = "{$referencesDir}/{$file}";
    $original = (string) file_get_contents($path);
    $updated = $original;

    foreach ($names as $name) {
        $updated = replaceBlock($updated, $name, implode("\n", $generated[$name]));
    }

    if ($updated === $original) {
        continue;
    }

    $drifted[] = $file;

    if (! $check) {
        file_put_contents($path, $updated);
    }
}

if ($drifted === []) {
    echo "References are in sync with the package.\n";
    exit(0);
}

echo ($check ? 'Out of sync: ' : 'Updated: ').implode(', ', $drifted)."\n";
exit($check ? 1 : 0);
