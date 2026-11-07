<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Pure-PHP MySQL dump to a .sql.gz file.
 *
 * Needs no mysqldump, exec() or shell access, so it works on shared hosts
 * such as InfinityFree where those are disabled.
 */
class DatabaseDumper
{
    private const ROWS_PER_SELECT = 500;
    private const ROWS_PER_INSERT = 100;

    public static function dumpToGzip(string $filepath): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $database = $connection->getDatabaseName();

        $gz = gzopen($filepath, 'wb9');
        if ($gz === false) {
            throw new RuntimeException('Cannot write the backup file.');
        }

        try {
            gzwrite($gz, "-- SDCC database backup\n");
            gzwrite($gz, "-- Database: {$database}\n");
            gzwrite($gz, '-- Created: ' . now()->toDateTimeString() . "\n\n");
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
                ->fetchAll(PDO::FETCH_NUM);

            foreach ($tables as $row) {
                self::dumpTable($pdo, $gz, $row[0]);
            }

            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }
    }

    private static function dumpTable(PDO $pdo, $gz, string $table): void
    {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';

        $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_NUM);
        gzwrite($gz, "-- Table: {$table}\n");
        gzwrite($gz, "DROP TABLE IF EXISTS {$quotedTable};\n");
        gzwrite($gz, $create[1] . ";\n\n");

        $offset = 0;
        do {
            $rows = $pdo->query(
                "SELECT * FROM {$quotedTable} LIMIT " . self::ROWS_PER_SELECT . " OFFSET {$offset}"
            )->fetchAll(PDO::FETCH_NUM);

            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                $values = [];
                foreach ($chunk as $record) {
                    $values[] = '(' . implode(',', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        $record
                    )) . ')';
                }
                gzwrite($gz, "INSERT INTO {$quotedTable} VALUES " . implode(",\n", $values) . ";\n");
            }

            $offset += self::ROWS_PER_SELECT;
        } while (count($rows) === self::ROWS_PER_SELECT);

        gzwrite($gz, "\n");
    }
}
