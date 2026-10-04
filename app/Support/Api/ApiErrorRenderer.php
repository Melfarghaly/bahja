<?php

namespace App\Support\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * One error shape for the whole API:
 *
 *   { "message": "<human, localized>", "code": "<stable machine code>", "errors": {…} }
 *
 * `code` is what apps branch on; `message` is safe to show to the user and
 * never leaks internals (model class names, SQL, stack traces).
 */
class ApiErrorRenderer
{
    /**
     * English defaults thrown by the framework; replaced by our localized text.
     */
    private const FRAMEWORK_MESSAGES = ['This action is unauthorized.', 'Unauthorized.', 'Forbidden', 'Unauthenticated.', 'Payment Required'];

    public static function render(Throwable $e): JsonResponse
    {
        [$status, $code, $message, $errors, $headers] = self::describe($e);

        $body = ['message' => $message, 'code' => $code];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        if ($status >= 500 && config('app.debug')) {
            $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        return new JsonResponse($body, $status, $headers, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: ?array<string, mixed>, 4: array<string, string>}
     */
    private static function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', __('api.errors.validation_failed'), $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'unauthenticated', __('api.errors.unauthenticated'), null, []],
            $e instanceof AuthorizationException => [403, 'forbidden', self::custom($e->getMessage(), 'forbidden', self::FRAMEWORK_MESSAGES), null, []],
            $e instanceof ModelNotFoundException => [404, 'not_found', __('api.errors.not_found'), null, []],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', __('api.errors.too_many_requests'), null, self::stringHeaders($e->getHeaders())],
            $e instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', __('api.errors.method_not_allowed'), null, self::stringHeaders($e->getHeaders())],
            $e instanceof NotFoundHttpException => [404, 'not_found', self::notFoundMessage($e), null, []],
            $e instanceof HttpExceptionInterface => self::http($e),
            default => [500, 'server_error', __('api.errors.server_error'), null, []],
        };
    }

    /**
     * @return array{0: int, 1: string, 2: string, 3: null, 4: array<string, string>}
     */
    private static function http(HttpExceptionInterface $e): array
    {
        $status = $e->getStatusCode();
        $code = match ($status) {
            401 => 'unauthenticated',
            402 => 'plan_upgrade_required',
            403 => 'forbidden',
            409 => 'conflict',
            419 => 'session_expired',
            429 => 'too_many_requests',
            default => $status >= 500 ? 'server_error' : 'http_error',
        };

        $message = $status >= 500
            ? __('api.errors.server_error')
            : self::custom($e->getMessage(), $code, self::FRAMEWORK_MESSAGES);

        return [$status, $code, $message, null, self::stringHeaders($e->getHeaders())];
    }

    /**
     * Keep our own (localized) abort messages; replace empty or framework
     * defaults with the localized message for the code.
     *
     * @param  array<int, string>  $frameworkDefaults
     */
    private static function custom(string $message, string $code, array $frameworkDefaults = []): string
    {
        $fallback = __('api.errors.'.$code);

        if ($message === '' || in_array($message, $frameworkDefaults, true)) {
            return $fallback === 'api.errors.'.$code ? __('api.errors.server_error') : $fallback;
        }

        return $message;
    }

    private static function notFoundMessage(NotFoundHttpException $e): string
    {
        // Model binding failures wrap ModelNotFoundException; unknown URLs don't.
        return $e->getPrevious() instanceof ModelNotFoundException
            ? __('api.errors.not_found')
            : __('api.errors.route_not_found');
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private static function stringHeaders(array $headers): array
    {
        return array_map(fn ($value) => is_array($value) ? implode(', ', $value) : (string) $value, $headers);
    }
}
