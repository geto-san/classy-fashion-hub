<?php

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\Connectors\MariaDbConnector;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

$base = $argv[1] ?? '/var/www/html';
require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$app->bootstrapWith([LoadEnvironmentVariables::class, LoadConfiguration::class]);

try {
    $name = $app['config']->get('database.default');
    $config = (new ConfigurationUrlParser)->parseConfiguration($app['config']->get('database.connections.'.$name));

    if ($config['driver'] === 'pgsql' && ! in_array($config['sslmode'] ?? null, ['require', 'verify-ca', 'verify-full'], true)) {
        throw new RuntimeException('PostgreSQL must require client-side TLS.');
    }

    if (in_array($config['driver'], ['mysql', 'mariadb'], true)) {
        $config['options'][PDO::ATTR_TIMEOUT] = 5;
    }

    $connector = match ($config['driver']) {
        'pgsql' => new PostgresConnector,
        'mysql' => new MySqlConnector,
        'mariadb' => new MariaDbConnector,
        default => throw new RuntimeException('Unsupported database driver.'),
    };

    $connector->connect($config)->query('SELECT 1');
} catch (Throwable) {
    fwrite(STDERR, "Database readiness check failed. Verify the connection, database, credentials, and TLS settings.\n");

    exit(1);
}
