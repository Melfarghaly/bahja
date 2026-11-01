<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A unique index that only covers some rows, e.g. "one active enrollment
 * per child": unique(child_id) where status = 'active'.
 *
 * PostgreSQL and SQLite have partial indexes. MySQL and MariaDB do not, and
 * `create unique index ... where ...` is a syntax error there. On those, each
 * key column gets a stored generated twin that holds the value only for
 * matching rows and NULL otherwise; the unique index goes on the twins, and
 * NULLs never clash. Same guarantee, same UniqueConstraintViolationException.
 *
 * Always use this instead of a raw `where` index in migrations.
 */
final class PartialUnique
{
    /**
     * @param  list<string>  $columns  the unique key, e.g. ['child_id']
     * @param  string  $when  the column that selects the rows, e.g. 'status'
     * @param  string  $equals  the value it must have, e.g. 'active'
     */
    public static function create(string $table, string $name, array $columns, string $when, string $equals): void
    {
        $db = DB::connection();
        $grammar = $db->getQueryGrammar();
        $value = $db->getPdo()->quote($equals);
        $condition = $grammar->wrap($when).' = '.$value;

        if (! self::isMysql()) {
            $db->statement(sprintf(
                'create unique index %s on %s (%s) where %s',
                $grammar->wrap($name),
                $grammar->wrapTable($table),
                $grammar->columnize($columns),
                $condition,
            ));

            return;
        }

        $types = collect(Schema::getColumns($table))->pluck('type', 'name');
        $twins = [];
        foreach ($columns as $column) {
            $twin = self::twin($name, $column);
            $twins[] = $twin;
            $db->statement(sprintf(
                'alter table %s add column %s %s as (case when %s then %s end) stored',
                $grammar->wrapTable($table),
                $grammar->wrap($twin),
                $types[$column],
                $condition,
                $grammar->wrap($column),
            ));
        }

        $db->statement(sprintf(
            'create unique index %s on %s (%s)',
            $grammar->wrap($name),
            $grammar->wrapTable($table),
            $grammar->columnize($twins),
        ));
    }

    /** @param  list<string>  $columns */
    public static function drop(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $db = DB::connection();
        $grammar = $db->getQueryGrammar();

        if (! self::isMysql()) {
            $db->statement('drop index if exists '.$grammar->wrap($name));

            return;
        }

        $db->statement(sprintf('drop index %s on %s', $grammar->wrap($name), $grammar->wrapTable($table)));
        foreach ($columns as $column) {
            $db->statement(sprintf('alter table %s drop column %s', $grammar->wrapTable($table), $grammar->wrap(self::twin($name, $column))));
        }
    }

    private static function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** Hidden helper column; MySQL names are capped at 64 characters. */
    private static function twin(string $name, string $column): string
    {
        return substr('_pu_'.substr(md5($name), 0, 8).'_'.$column, 0, 64);
    }
}
