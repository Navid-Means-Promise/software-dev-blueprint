<?php

declare(strict_types=1);

namespace FactDb\Persistence;

use FactDb\FactDbException;
use FactDb\Failure;
use PDO;
use Throwable;

final class SqliteSchema
{
    public static function install(PDO $pdo): void
    {
        self::connection($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS factdb_migrations (version INTEGER PRIMARY KEY, checksum TEXT NOT NULL)');
        $applied = $pdo->query('SELECT version, checksum FROM factdb_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        $migrations = self::migrations();
        if (array_diff_key($applied, $migrations) !== []) {
            throw new FactDbException(Failure::Schema, 'FactDB schema is newer than this library.');
        }
        foreach ($migrations as $version => $file) {
            $checksum = hash_file('sha256', $file);
            if (isset($applied[$version])) {
                if ($applied[$version] !== $checksum) {
                    throw new FactDbException(Failure::Schema, 'An applied FactDB migration changed.');
                }
                continue;
            }
            $pdo->beginTransaction();
            try {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new FactDbException(Failure::Schema, 'FactDB migration cannot be read.');
                }
                $pdo->exec($sql);
                $statement = $pdo->prepare('INSERT INTO factdb_migrations (version, checksum) VALUES (?, ?)');
                $statement->execute([$version, $checksum]);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new FactDbException(Failure::Schema, 'FactDB migration failed.', $error);
            }
        }
        self::assertCurrent($pdo);
    }

    public static function assertCurrent(PDO $pdo): void
    {
        self::connection($pdo);
        try {
            $applied = $pdo->query('SELECT version, checksum FROM factdb_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $error) {
            throw new FactDbException(Failure::Schema, 'Install FactDB migrations explicitly before constructing its repository.', $error);
        }
        $expected = [];
        foreach (self::migrations() as $version => $file) {
            $expected[$version] = hash_file('sha256', $file);
        }
        ksort($applied);
        if ($applied !== $expected) {
            throw new FactDbException(Failure::Schema, 'FactDB schema is missing, changed, or newer than this library.');
        }
    }

    private static function connection(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' || (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 1
            || $pdo->inTransaction() || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new FactDbException(Failure::Schema, 'FactDB requires SQLite, foreign keys, exception mode, and ownership of its transaction.');
        }
    }

    /** @return array<int, string> */
    private static function migrations(): array
    {
        $result = [];
        foreach (glob(dirname(__DIR__, 2) . '/schema/*.sql') ?: [] as $file) {
            if (preg_match('/\A(\d+)_/', basename($file), $match) !== 1 || isset($result[(int) $match[1]])) {
                throw new FactDbException(Failure::Schema, 'Invalid FactDB migration sequence.');
            }
            $result[(int) $match[1]] = $file;
        }
        ksort($result);
        if ($result === []) {
            throw new FactDbException(Failure::Schema, 'FactDB migration files are missing.');
        }
        return $result;
    }
}
