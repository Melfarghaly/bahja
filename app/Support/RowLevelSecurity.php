<?php

namespace App\Support;

use Closure;
use Illuminate\Database\DatabaseManager;

/**
 * Second isolation layer: drives the PostgreSQL session settings read by the
 * Row-Level Security policies on tenant tables (see the enable_row_level_security
 * migration). The policies deny every row unless either
 *
 *   - `app.current_tenant` matches the row's tenant_id, or
 *   - `app.bypass_rls` is 'on' (platform-wide work: super admin, seeders).
 *
 * So a query that runs without a tenant context returns nothing instead of
 * leaking — even when an application-level scope was forgotten or skipped.
 *
 * On drivers without RLS (SQLite in tests/local) every method is a no-op.
 */
class RowLevelSecurity
{
    public const TENANT_SETTING = 'app.current_tenant';

    public const BYPASS_SETTING = 'app.bypass_rls';

    public function __construct(private DatabaseManager $db) {}

    public function enabled(): bool
    {
        return $this->db->connection()->getDriverName() === 'pgsql';
    }

    public function setTenant(?int $tenantId): void
    {
        $this->set(self::TENANT_SETTING, $tenantId === null ? '' : (string) $tenantId);
    }

    public function enableBypass(): void
    {
        $this->set(self::BYPASS_SETTING, 'on');
    }

    public function disableBypass(): void
    {
        $this->set(self::BYPASS_SETTING, 'off');
    }

    public function bypassing(): bool
    {
        return $this->enabled() && $this->get(self::BYPASS_SETTING) === 'on';
    }

    /**
     * Run platform-wide work that must see every tenant's rows.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function bypass(Closure $callback): mixed
    {
        $wasBypassing = $this->bypassing();
        $this->enableBypass();

        try {
            return $callback();
        } finally {
            if (! $wasBypassing) {
                $this->disableBypass();
            }
        }
    }

    private function set(string $setting, string $value): void
    {
        if ($this->enabled()) {
            // Session-scoped (is_local = false) so it survives across transactions.
            $this->db->connection()->select('select set_config(?, ?, false)', [$setting, $value]);
        }
    }

    private function get(string $setting): ?string
    {
        $row = $this->db->connection()->selectOne('select current_setting(?, true) as value', [$setting]);

        return $row?->value;
    }
}
