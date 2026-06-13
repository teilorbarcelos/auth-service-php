<?php

declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php scripts/generate.php ModuleName\n";
    exit(1);
}

$moduleName = ucfirst($argv[1]);
$moduleLower = strtolower($moduleName);
$moduleLowerPlural = $moduleLower . 's';
$tableName = camelToSnake($moduleName) . 's';

if (str_ends_with($moduleLower, 'y')) {
    $moduleLowerPlural = substr($moduleLower, 0, -1) . 'ies';
    $tableName = camelToSnake(substr($moduleName, 0, -1)) . 'ies';
}

$moduleDir = __DIR__ . '/../src/Modules/' . $moduleName;
$templateDir = __DIR__ . '/templates';

if (is_dir($moduleDir)) {
    echo "Error: Module $moduleName already exists.\n";
    exit(1);
}

$templates = [
    'Model.tpl' => "src/Modules/{$moduleName}/{$moduleName}.php",
    'Controller.tpl' => "src/Modules/{$moduleName}/{$moduleName}Controller.php",
    'Repository.tpl' => "src/Modules/{$moduleName}/{$moduleName}Repository.php",
    'Service.tpl' => "src/Modules/{$moduleName}/{$moduleName}Service.php",
    'routes.tpl' => "src/Modules/{$moduleName}/routes.php",
    'Transformer.tpl' => "src/Core/Transformers/{$moduleName}Transformer.php",
];

foreach ($templates as $tpl => $relativePath) {
    $fullPath = __DIR__ . '/../' . $relativePath;
    $dir = dirname($fullPath);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!file_exists("$templateDir/$tpl")) {
        echo "Warning: Template $tpl not found. Skipping.\n";
        continue;
    }

    $content = file_get_contents("$templateDir/$tpl");
    $content = str_replace(
        ['{{MODULE_NAME}}', '{{MODULE_LOWER}}', '{{MODULE_LOWER_PLURAL}}', '{{TABLE_NAME}}'],
        [$moduleName, $moduleLower, $moduleLowerPlural, $tableName],
        $content
    );
    file_put_contents($fullPath, $content);
    echo "Created: $relativePath\n";
}

// 1. Register in config/routes.php
$routesPath = __DIR__ . '/../config/routes.php';
$routesContent = file_get_contents($routesPath);

if (!str_contains($routesContent, "/$moduleLower'")) {
    $routesContent = preg_replace(
        '/^([ \t]*)\/\/ \[GENERATOR_ROUTES\]/m',
        "$1\$protectedGroup->group('/$moduleLower', require __DIR__ . '/../src/Modules/$moduleName/routes.php');\n$1// [GENERATOR_ROUTES]",
        $routesContent
    );
    file_put_contents($routesPath, $routesContent);
    echo "Registered: Routes in config/routes.php\n";
}

// 2. Register in config/container.php
$containerPath = __DIR__ . '/../config/container.php';
$containerContent = file_get_contents($containerPath);

if (!str_contains($containerContent, "\\App\\Modules\\$moduleName\\")) {
    $containerContent = preg_replace(
        '/^([ \t]*)\/\/ \[GENERATOR_SERVICES\]/m',
        "$1\\App\\Modules\\$moduleName\\{$moduleName}Controller::class => \\DI\\autowire(),\n" .
        "$1\\App\\Modules\\$moduleName\\{$moduleName}Service::class => \\DI\\autowire(),\n" .
        "$1\\App\\Modules\\$moduleName\\{$moduleName}Repository::class => \\DI\\autowire(),\n" .
        "$1\\App\\Core\\Transformers\\{$moduleName}Transformer::class => \\DI\\autowire(),\n" .
        "$1// [GENERATOR_SERVICES]",
        $containerContent
    );
    file_put_contents($containerPath, $containerContent);
    echo "Registered: Services and Transformer in config/container.php\n";
}

echo "\nModule $moduleName generated successfully!\n";
echo "Next steps:\n";
echo "1. Create a migration: php vendor/bin/phinx create Create{$moduleName}Table\n";
echo "2. Run the migration: make migrate\n";

function camelToSnake($input)
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
}
