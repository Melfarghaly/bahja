<?php

namespace Tests\Postman;

/**
 * Builds a Postman v2.1 collection from request definitions and the real
 * responses recorded by GeneratePostmanCollectionTest.
 */
class PostmanCollection
{
    /**
     * @var array<string, array{description: string, requests: array<int, string>}>
     */
    private array $folders = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $requests = [];

    /**
     * @param  array<int, array{key: string, value: string, description?: string}>  $variables
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $variables,
    ) {}

    public function folder(string $name, string $description): void
    {
        $this->folders[$name] = ['description' => $description, 'requests' => []];
    }

    /**
     * @param  array{folder: string, name: string, method: string, path: string, auth: ?string, tenant?: bool, query?: array<string, string>, body?: ?array<string, mixed>, description: string, script?: string, prerequest?: string, multipart?: bool}  $spec
     */
    public function request(string $key, array $spec): void
    {
        $this->requests[$key] = $spec + ['examples' => [], 'tenant' => true, 'query' => [], 'body' => null, 'script' => null, 'prerequest' => null, 'expect' => null];
        $this->folders[$spec['folder']]['requests'][] = $key;
    }

    /**
     * @param  array{name: string, auth: ?string, tenant: bool, query: array<string, string>, body: ?array<string, mixed>, status: int, headers: array<string, string>, response: string}  $example
     */
    public function example(string $key, array $example): void
    {
        $this->requests[$key]['examples'][] = $example;
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'requests' => count($this->requests),
            'examples' => array_sum(array_map(fn ($r) => count($r['examples']), $this->requests)),
        ];
    }

    public function write(string $path): void
    {
        file_put_contents($path, json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'info' => [
                '_postman_id' => '6b1f3c2a-7d1e-4b6a-9a3c-bahga0api0v1',
                'name' => $this->name,
                'description' => $this->description,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => ['type' => 'noauth'],
            'event' => [[
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => [
                    '// Every response is JSON (except 204); every error has { message, code }.',
                    'if (pm.response.code !== 204) {',
                    "    pm.test('response is JSON', () => pm.response.to.be.json);",
                    '}',
                    'if (pm.response.code >= 400) {',
                    "    pm.test('error has message + code', () => {",
                    '        const body = pm.response.json();',
                    "        pm.expect(body).to.have.property('message');",
                    "        pm.expect(body).to.have.property('code');",
                    '    });',
                    '}',
                ]],
            ]],
            'variable' => $this->variables,
            'item' => array_values(array_map(fn (string $folder) => [
                'name' => $folder,
                'description' => $this->folders[$folder]['description'],
                'item' => array_map(fn (string $key) => $this->item($this->requests[$key]), $this->folders[$folder]['requests']),
            ], array_keys($this->folders))),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function item(array $spec): array
    {
        $item = [
            'name' => $spec['name'],
            'request' => $this->requestBlock($spec, $spec['auth'], $spec['tenant'], $spec['query'], $spec['body']) + ['description' => $spec['description']],
            'response' => array_map(fn (array $example) => [
                'name' => $example['name'],
                'originalRequest' => $this->requestBlock($spec, $example['auth'], $example['tenant'], $example['query'], $example['body']),
                'status' => $this->reason($example['status']),
                'code' => $example['status'],
                '_postman_previewlanguage' => $example['response'] === '' ? 'text' : 'json',
                'header' => array_map(fn ($k, $v) => ['key' => $k, 'value' => $v], array_keys($example['headers']), $example['headers']),
                'cookie' => [],
                'body' => $example['response'],
            ], $spec['examples']),
        ];

        // Running the collection in order is a smoke test of the server: every
        // request asserts the status its main (happy-path) scenario returns.
        $script = [];
        if ($spec['expect'] ?? null) {
            $codes = implode(', ', $spec['expect']);
            $script[] = "pm.test('status is one of [{$codes}]', () => pm.expect(pm.response.code).to.be.oneOf([{$codes}]));";
        }
        if ($spec['script']) {
            array_push($script, ...explode("\n", $spec['script']));
        }
        if ($spec['prerequest']) {
            $item['event'][] = [
                'listen' => 'prerequest',
                'script' => ['type' => 'text/javascript', 'exec' => explode("\n", $spec['prerequest'])],
            ];
        }
        if ($script !== []) {
            $item['event'][] = [
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => $script],
            ];
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, string>  $query
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function requestBlock(array $spec, ?string $auth, bool $tenant, array $query, ?array $body): array
    {
        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Accept-Language', 'value' => '{{lang}}', 'description' => 'ar (default) or en'],
        ];

        if ($tenant) {
            $headers[] = ['key' => 'X-Tenant-Id', 'value' => '{{tenant_id}}', 'description' => 'The nursery to act in (from GET /v1/me)'];
        }

        $multipart = (bool) ($spec['multipart'] ?? false);

        if ($body !== null && ! $multipart) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
        }

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $raw = '{{base_url}}/'.$spec['path'].($queryString !== '' ? '?'.rawurldecode($queryString) : '');

        $block = [
            'method' => $spec['method'],
            'header' => $headers,
            'url' => [
                'raw' => $raw,
                'host' => ['{{base_url}}'],
                'path' => explode('/', $spec['path']),
                'query' => array_map(fn ($k, $v) => ['key' => $k, 'value' => (string) $v], array_keys($query), $query),
            ],
            'auth' => $auth === null
                ? ['type' => 'noauth']
                : ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => '{{'.$auth.'_token}}', 'type' => 'string']]],
        ];

        if ($query === []) {
            unset($block['url']['query']);
        }

        if ($body !== null && $multipart) {
            $block['body'] = ['mode' => 'formdata', 'formdata' => self::formData($body)];
        } elseif ($body !== null) {
            $block['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        return $block;
    }

    /**
     * Flattens a body into Postman form fields: lists become `key[]`, nested
     * arrays `key[sub]`, and "@file:<path>" values file fields.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, string>>
     */
    public static function formData(array $body, string $prefix = ''): array
    {
        $fields = [];

        foreach ($body as $key => $value) {
            $name = $prefix === '' ? (string) $key : (array_is_list($body) ? "{$prefix}[]" : "{$prefix}[{$key}]");

            if (is_array($value)) {
                array_push($fields, ...self::formData($value, $name));
            } elseif (is_string($value) && str_starts_with($value, '@file:')) {
                $fields[] = ['key' => $name, 'type' => 'file', 'src' => substr($value, 6)];
            } else {
                $fields[] = ['key' => $name, 'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'type' => 'text'];
            }
        }

        return $fields;
    }

    private function reason(int $status): string
    {
        return [
            200 => 'OK', 201 => 'Created', 204 => 'No Content', 401 => 'Unauthorized', 402 => 'Payment Required',
            403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 422 => 'Unprocessable Content',
            429 => 'Too Many Requests', 500 => 'Internal Server Error',
        ][$status] ?? 'Status '.$status;
    }
}
