<?php

namespace ClassyFashion\Repositories;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transfer an upgraded MySQL snapshot into an empty application-created PostgreSQL schema.
 */
class DatabaseTransferRepository
{
    protected const SCHEMA_DEFAULTS = ['attributes', 'marketing_events'];

    /**
     * Fail atomically on unknown tables, nonempty targets, or violated relationships.
     */
    public function transfer(bool $execute, bool $replaceSchemaDefaults = false): array
    {
        $source = DB::connection('migration_source');
        $target = DB::connection();

        if ($source->getDriverName() !== 'mysql' || $target->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Transfer requires a MySQL source and PostgreSQL target.');
        }

        if (str_contains((string) $target->getConfig('host'), '-pooler.')) {
            throw new RuntimeException('Transfer requires the direct PostgreSQL endpoint.');
        }

        $sourceTables = collect($source->getSchemaBuilder()->getTables($source->getDatabaseName()))->pluck('name')->reject(fn ($table) => $table === 'migrations')->unique()->values();
        $report = [];

        foreach ($sourceTables as $table) {
            if (! $target->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException('Target schema is missing table: '.$table);
            }

            if ($target->table($table)->exists() && (! $replaceSchemaDefaults || ! in_array($table, self::SCHEMA_DEFAULTS, true))) {
                throw new RuntimeException('Target contains data in table: '.$table);
            }

            $columns = $source->getSchemaBuilder()->getColumnListing($table);
            $unknown = array_diff($columns, $target->getSchemaBuilder()->getColumnListing($table));

            if ($unknown !== []) {
                throw new RuntimeException('Target is missing columns in '.$table.': '.implode(', ', $unknown));
            }

            $report[$table] = $source->table($table)->count();
        }

        foreach ($target->getSchemaBuilder()->getTables('public') as $table) {
            if ($table['name'] !== 'migrations' && ! $sourceTables->contains($table['name']) && $target->table($table['name'])->exists()) {
                throw new RuntimeException('Unexpected target data in '.$table['name']);
            }
        }

        if (! $execute) {
            return $report;
        }

        $source->beginTransaction();

        try {
            $target->transaction(function () use ($source, $target, $sourceTables, $replaceSchemaDefaults, &$report) {
                $constraints = $target->select("SELECT c.conname, t.relname, c.condeferrable, c.condeferred FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace WHERE c.contype = 'f' AND n.nspname = 'public'");
                $grammar = $target->getQueryGrammar();

                foreach ($constraints as $constraint) {
                    $target->statement('ALTER TABLE '.$grammar->wrapTable($constraint->relname).' ALTER CONSTRAINT '.$grammar->wrap($constraint->conname).' DEFERRABLE INITIALLY DEFERRED');
                }

                $target->statement('SET CONSTRAINTS ALL DEFERRED');

                if ($replaceSchemaDefaults) {
                    foreach (self::SCHEMA_DEFAULTS as $table) {
                        $target->table($table)->delete();
                    }
                }

                foreach ($sourceTables as $table) {
                    $booleans = collect($target->getSchemaBuilder()->getColumns($table))->where('type_name', 'bool')->pluck('name')->all();
                    $rows = [];
                    $count = 0;

                    foreach ($source->table($table)->cursor() as $row) {
                        $row = (array) $row;

                        foreach ($booleans as $column) {
                            if (array_key_exists($column, $row) && $row[$column] !== null) {
                                if (! in_array($row[$column], [0, 1, '0', '1', false, true], true)) {
                                    throw new RuntimeException('Invalid boolean in '.$table.'.'.$column);
                                }

                                $row[$column] = (bool) $row[$column];
                            }
                        }

                        $rows[] = $row;
                        $count++;

                        if (count($rows) === 100) {
                            $target->table($table)->insert($rows);
                            $rows = [];
                        }
                    }

                    if ($rows !== []) {
                        $target->table($table)->insert($rows);
                    }

                    if ($target->table($table)->count() !== $count) {
                        throw new RuntimeException('Row count differs for '.$table);
                    }

                    $report[$table] = $count;
                }

                $target->statement('SET CONSTRAINTS ALL IMMEDIATE');

                foreach ($constraints as $constraint) {
                    $definition = $constraint->condeferrable
                        ? 'DEFERRABLE INITIALLY '.($constraint->condeferred ? 'DEFERRED' : 'IMMEDIATE')
                        : 'NOT DEFERRABLE';
                    $target->statement('ALTER TABLE '.$grammar->wrapTable($constraint->relname).' ALTER CONSTRAINT '.$grammar->wrap($constraint->conname).' '.$definition);
                }

                foreach (['admins', 'customers'] as $table) {
                    $expected = $source->table($table)->orderBy('id')->pluck('password', 'id')->all();
                    $actual = $target->table($table)->orderBy('id')->pluck('password', 'id')->all();

                    if ($expected !== $actual) {
                        throw new RuntimeException('Password hash reconciliation failed for '.$table);
                    }
                }

                foreach (['orders', 'invoices', 'refunds'] as $table) {
                    foreach (['grand_total', 'base_grand_total'] as $column) {
                        if (bccomp((string) $source->table($table)->sum($column), (string) $target->table($table)->sum($column), 4) !== 0) {
                            throw new RuntimeException('Monetary reconciliation failed for '.$table.'.'.$column);
                        }
                    }
                }

                $sequences = $target->select("SELECT table_name, column_name, pg_get_serial_sequence(quote_ident(table_schema) || '.' || quote_ident(table_name), column_name) AS sequence FROM information_schema.columns WHERE table_schema = 'public' AND (column_default LIKE 'nextval(%' OR is_identity = 'YES')");

                foreach ($sequences as $column) {
                    if (! in_array($column->table_name, $sourceTables->all(), true) || ! $column->sequence) {
                        continue;
                    }

                    $maximum = $target->table($column->table_name)->max($column->column_name);
                    $target->select('SELECT setval(CAST(? AS regclass), ?, ?)', [$column->sequence, max(1, (int) $maximum), $maximum !== null]);
                }
            });
        } finally {
            $source->rollBack();
        }

        return $report;
    }
}
