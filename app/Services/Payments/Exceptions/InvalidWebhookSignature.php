<?php

namespace App\Services\Payments\Exceptions;

use RuntimeException;

/**
 * A webhook whose signature does not match: never trust its content.
 */
class InvalidWebhookSignature extends RuntimeException {}
