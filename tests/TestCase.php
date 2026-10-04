<?php

namespace Tests;

use App\Support\RowLevelSecurity;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fixtures are created without a tenant context, which PostgreSQL
     * Row-Level Security would reject. The bypass is therefore on while a test
     * arranges and asserts, and switched off for every HTTP request so the
     * application runs exactly as in production (no-op on SQLite).
     */
    protected function setUpTraits(): array
    {
        $uses = parent::setUpTraits();

        $this->app->make(RowLevelSecurity::class)->enableBypass();

        // Symfony's test client sends "Accept-Language: en-us" by default; the
        // apps send their UI language, which is Arabic unless chosen otherwise.
        $this->withHeader('Accept-Language', 'ar');

        return $uses;
    }

    /**
     * {@inheritdoc}
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null): TestResponse
    {
        $rls = $this->app->make(RowLevelSecurity::class);
        $rls->disableBypass();

        try {
            return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        } finally {
            $rls->enableBypass();
        }
    }
}
