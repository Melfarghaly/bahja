<?php

namespace App\Services\Payments\Exceptions;

use RuntimeException;

/**
 * The payment gateway refused or failed a request (bad credentials, outage,
 * validation error on their side). The message is safe to log, not to show.
 */
class GatewayException extends RuntimeException {}
