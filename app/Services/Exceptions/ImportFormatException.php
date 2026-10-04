<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when an import file is empty or missing required columns.
 */
class ImportFormatException extends RuntimeException
{
}
