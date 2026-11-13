<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\SqlServerConnector as BaseSqlServerConnector;
use PDO;

/**
 * SQL Server connector compatible with pdo_sqlsrv builds that reject
 * PDO::ATTR_STRINGIFY_FETCHES (and other non-SQLSRV PDO attributes).
 */
class SqlServerConnector extends BaseSqlServerConnector
{
    /**
     * Keep only the attribute supported by pdo_sqlsrv across PHP versions.
     * PDO defaults already provide natural column casing and native values.
     *
     * @var array<int, mixed>
     */
    protected $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
}
