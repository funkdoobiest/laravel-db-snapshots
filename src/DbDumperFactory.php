<?php

namespace Spatie\DbSnapshots;

use Spatie\DbDumper\DbDumper;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbSnapshots\Exceptions\CannotCreateDbDumper;

class DbDumperFactory
{
    public static function createForConnection(string $connectionName): DbDumper
    {
        $dbConfig = config("database.connections.{$connectionName}");

        if (is_null($dbConfig)) {
            throw CannotCreateDbDumper::connectionDoesNotExist($connectionName);
        }

        $dbHost = array_get($dbConfig, 'read.host', array_get($dbConfig, 'host'));

        $dbDumper = static::forDriver($dbConfig['driver'])
            ->setHost($dbHost ?? '')
            ->setDbName($dbConfig['database'])
            ->setUserName($dbConfig['username'] ?? '')
            ->setPassword($dbConfig['password'] ?? '');

        if (isset($dbConfig['port'])) {
            $dbDumper = $dbDumper->setPort($dbConfig['port']);
        }

        if (isset($dbConfig['dump'])) {
            $dbDumper = static::processExtraDumpParameters($dbConfig['dump'], $dbDumper);
        }

        return $dbDumper;
    }

    protected static function forDriver($dbDriver): DbDumper
    {
        $driver = strtolower($dbDriver);

        if ($driver === 'mysql') {
            return new MySql();
        }

        if ($driver === 'mariadb') {
            return new MySql();
        }

        if ($driver === 'pgsql') {
            return (new PostgreSql())->useInserts();
        }

        if ($driver === 'sqlite') {
            return new Sqlite();
        }

        throw CannotCreateDbDumper::unsupportedDriver($driver);
    }

    /**
     * @param array $dumpConfiguration
     *
     * @param $dbDumper
     *
     * @return mixed
     */
    protected static function processExtraDumpParameters(array $dumpConfiguration, $dbDumper): DbDumper
    {
        collect($dumpConfiguration)->each(function ($configValue, $configName) use ($dbDumper) {
            $methodName = lcfirst(studly_case(is_numeric($configName) ? $configValue : $configName));
            $methodValue = is_numeric($configName) ? null : $configValue;

            $methodName = static::determineValidMethodName($dbDumper, $methodName);

            if (method_exists($dbDumper, $methodName)) {
                static::callMethodOnDumper($dbDumper, $methodName, $methodValue);
            }
        });

        return $dbDumper;
    }

    /**
     * @param \Spatie\DbDumper\DbDumper $dbDumper
     * @param string $methodName
     * @param string|null $methodValue
     *
     * @return \Spatie\DbDumper\DbDumper
     */
    protected static function callMethodOnDumper(DbDumper $dbDumper, string $methodName, $methodValue): DbDumper
    {
        if (! $methodValue) {
            $dbDumper->$methodName();

            return $dbDumper;
        }

        $dbDumper->$methodName($methodValue);

        return $dbDumper;
    }

    protected static function determineValidMethodName(DbDumper $dbDumper, string $methodName): string
    {
        return collect([$methodName, 'set'.ucfirst($methodName)])
            ->first(function (string $methodName) use ($dbDumper) {
                return method_exists($dbDumper, $methodName);
            }, '');
    }
}
