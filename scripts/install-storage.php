<?php

declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: php scripts/install-storage.php <driver-name>\n";
    echo "Supported drivers: s3, gcs, azure, local\n";
    exit(1);
}

$driver = strtolower($argv[1]);
$driverName = ucfirst($driver);

$configs = [
    's3' => [
        'package' => 'league/flysystem-aws-s3-v3:^3.0',
        'adapter' => 'AwsS3V3\AwsS3V3Adapter',
        'extra_uses' => "use Aws\S3\S3Client;",
        'setup' => "
        \$client = new S3Client([
            'credentials' => [
                'key'    => \$_ENV['S3_KEY'] ?? '',
                'secret' => \$_ENV['S3_SECRET'] ?? '',
            ],
            'region' => \$_ENV['S3_REGION'] ?? 'us-east-1',
            'version' => 'latest',
        ]);
        \$adapter = new AwsS3V3Adapter(\$client, \$_ENV['S3_BUCKET'] ?? '');",
        'env' => [
            'S3_KEY' => '',
            'S3_SECRET' => '',
            'S3_REGION' => 'us-east-1',
            'S3_BUCKET' => '',
        ],
        'test_env' => "
        \$_ENV['S3_KEY'] = 'test';
        \$_ENV['S3_SECRET'] = 'test';
        \$_ENV['S3_REGION'] = 'us-east-1';
        \$_ENV['S3_BUCKET'] = 'test';"
    ],
    'gcs' => [
        'package' => 'league/flysystem-google-cloud-storage:^3.0',
        'adapter' => 'GoogleCloudStorage\GoogleCloudStorageAdapter',
        'extra_uses' => "use Google\Cloud\Storage\StorageClient;",
        'setup' => "
        \$client = new StorageClient([
            'keyFilePath' => \$_ENV['GCS_KEY_FILE'] ?? '',
            'projectId' => \$_ENV['GCS_PROJECT_ID'] ?? '',
        ]);
        \$bucket = \$client->bucket(\$_ENV['GCS_BUCKET'] ?? '');
        \$adapter = new GoogleCloudStorageAdapter(\$bucket, \$_ENV['GCS_PREFIX'] ?? '');",
        'env' => [
            'GCS_KEY_FILE' => '',
            'GCS_PROJECT_ID' => '',
            'GCS_BUCKET' => '',
            'GCS_PREFIX' => '',
        ],
        'test_env' => "
        \$_ENV['GCS_KEY_FILE'] = 'test.json';
        \$_ENV['GCS_PROJECT_ID'] = 'test';
        \$_ENV['GCS_BUCKET'] = 'test';"
    ],
    'azure' => [
        'package' => 'league/flysystem-azure-blob-storage:^3.0',
        'adapter' => 'AzureBlobStorage\AzureBlobStorageAdapter',
        'extra_uses' => "use MicrosoftAzure\Storage\Blob\BlobRestProxy;",
        'setup' => "
        \$accountName = \$_ENV['AZURE_ACCOUNT_NAME'] ?? '';
        \$accountKey = \$_ENV['AZURE_ACCOUNT_KEY'] ?? '';
        \$dsn = \"DefaultEndpointsProtocol=https;AccountName={\$accountName};AccountKey={\$accountKey};EndpointSuffix=core.windows.net\";
        \$client = BlobRestProxy::createBlobService(\$dsn);
        \$adapter = new AzureBlobStorageAdapter(\$client, \$_ENV['AZURE_CONTAINER'] ?? '');",
        'env' => [
            'AZURE_ACCOUNT_NAME' => '',
            'AZURE_ACCOUNT_KEY' => '',
            'AZURE_CONTAINER' => '',
        ],
        'test_env' => "
        \$_ENV['AZURE_ACCOUNT_NAME'] = 'test';
        \$_ENV['AZURE_ACCOUNT_KEY'] = 'test';
        \$_ENV['AZURE_CONTAINER'] = 'test';"
    ],
    'local' => [
        'package' => 'league/flysystem-local:^3.0',
        'adapter' => 'Local\LocalFilesystemAdapter',
        'extra_uses' => "",
        'setup' => "
        \$storagePath = \$_ENV['STORAGE_PATH'] ?? __DIR__ . '/../../../../storage/app';
        \$adapter = new LocalFilesystemAdapter(\$storagePath);",
        'env' => [
            'STORAGE_PATH' => '/app/storage/app',
        ],
        'test_env' => "
        \$_ENV['STORAGE_PATH'] = __DIR__ . '/../../../temp_storage';"
    ]
];

if (!isset($configs[$driver])) {
    echo "Error: Driver [{$driver}] not supported.\n";
    exit(1);
}

$config = $configs[$driver];

// 1. Install Package
echo "📦 Installing {$config['package']}...\n";
passthru("docker compose exec -T app composer require {$config['package']}");

// 2. Generate Driver Class
echo "📝 Generating {$driverName}Driver.php...\n";
$driverTpl = file_get_contents(__DIR__ . '/templates/storage/Driver.php.tpl');
$driverContent = str_replace(
    ['{{DRIVER_NAME}}', '{{ADAPTER_CLASS}}', '{{EXTRA_USES}}', '{{ADAPTER_SETUP}}'],
    [$driverName, $config['adapter'], $config['extra_uses'], $config['setup']],
    $driverTpl
);

$driverPath = __DIR__ . "/../src/Infrastructure/Storage/Drivers/{$driverName}Driver.php";
if (!is_dir(dirname($driverPath))) {
    mkdir(dirname($driverPath), 0777, true);
}
file_put_contents($driverPath, $driverContent);

// 3. Generate Test Class
echo "🧪 Generating {$driverName}DriverTest.php...\n";
$testTpl = file_get_contents(__DIR__ . '/templates/storage/Test.php.tpl');
$testContent = str_replace(
    ['{{DRIVER_NAME}}', '{{DRIVER_LOWER}}', '{{ENV_SETUP}}'],
    [$driverName, $driver, $config['test_env']],
    $testTpl
);

$testPath = __DIR__ . "/../tests/Infrastructure/Storage/Drivers/{$driverName}DriverTest.php";
if (!is_dir(dirname($testPath))) {
    mkdir(dirname($testPath), 0777, true);
}
file_put_contents($testPath, $testContent);

// 4. Update .env and .env.example
echo "⚙️ Updating .env and .env.example...\n";
foreach (['.env', '.env.example'] as $envFile) {
    $filePath = __DIR__ . "/../{$envFile}";
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        foreach ($config['env'] as $key => $value) {
            if (strpos($content, "{$key}=") === false) {
                $content .= "\n{$key}={$value}";
            }
        }
        file_put_contents($filePath, $content);
    }
}

echo "✅ Storage driver [{$driverName}] installed and configured successfully!\n";
echo "💡 To use it, set STORAGE_DISK={$driver} in your .env file.\n";
echo "🧪 Run 'make test' to verify the new driver.\n";
