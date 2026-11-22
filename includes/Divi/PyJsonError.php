<?php
/**
 * Invalid JSON, with Python's json.JSONDecodeError message text.
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Raised by PyJson::loads (Python's ValueError / JSONDecodeError).
 */
class PyJsonError extends \InvalidArgumentException {
}
