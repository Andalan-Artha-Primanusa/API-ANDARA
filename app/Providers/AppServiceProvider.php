<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Connection;
use Illuminate\Database\SqlServerConnection;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Binding Repository ke Interface
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel has no built-in dblib connector. Use PDO_DBLIB/FreeTDS
        // while keeping Laravel's SQL Server grammar and query builder.
        Connection::resolverFor('dblib', function ($connection, $database, $prefix = '', $config = []) {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 1433;
            $tdsVersion = $config['tds_version'] ?? '7.4';
            $dsn = "dblib:host={$host}:{$port};dbname={$database};version={$tdsVersion}";

            $pdo = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $config['options'] ?? []
            );

            return new SqlServerConnection($pdo, $database, $prefix, $config);
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

            return $frontendUrl . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
