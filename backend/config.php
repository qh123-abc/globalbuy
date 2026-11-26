<?php
declare(strict_types=1);

function gf_admin_password(): string
{
    $fromEnvironment = getenv('GLOBALFIND_ADMIN_PASSWORD');
    if ($fromEnvironment !== false && $fromEnvironment !== '') {
        return $fromEnvironment;
    }

    $localConfig = __DIR__ . '/config.local.php';
    if (is_file($localConfig)) {
        $config = require $localConfig;
        if (is_array($config) && isset($config['admin_password'])) {
            return (string) $config['admin_password'];
        }
    }

    return '';
}

function gf_data_directory(): string
{
    $configured = getenv('GLOBALFIND_DATA_DIR');
    $directory = ($configured !== false && $configured !== '')
        ? $configured
        : __DIR__ . '/data';

    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the product data directory.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('The product data directory is not writable by PHP.');
    }

    return rtrim($directory, DIRECTORY_SEPARATOR);
}
