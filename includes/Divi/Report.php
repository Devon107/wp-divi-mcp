<?php
/**
 * Validation report (port of validate.py's Report).
 *
 * Pure PHP, no WordPress dependency.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

/**
 * Errors break the import; warnings and info are advice. Each list keeps the order
 * the checks ran in, which matches validate.py.
 */
final class Report {

	/**
	 * ERROR messages.
	 *
	 * @var string[]
	 */
	public array $errors = array();

	/**
	 * WARNING messages.
	 *
	 * @var string[]
	 */
	public array $warnings = array();

	/**
	 * INFO messages.
	 *
	 * @var string[]
	 */
	public array $info = array();

	/**
	 * Block name => number of blocks, in order of first appearance.
	 *
	 * @var array<string, int>
	 */
	public array $counts = array();

	/**
	 * @param string $msg Message.
	 */
	public function err( string $msg ): void {
		$this->errors[] = $msg;
	}

	/**
	 * @param string $msg Message.
	 */
	public function warn( string $msg ): void {
		$this->warnings[] = $msg;
	}

	/**
	 * @param string $msg Message.
	 */
	public function note( string $msg ): void {
		$this->info[] = $msg;
	}

	/**
	 * No errors (warnings and info may exist).
	 */
	public function ok(): bool {
		return array() === $this->errors;
	}

	/**
	 * @return array{errors: string[], warnings: string[], info: string[], counts: array<string, int>, ok: bool}
	 */
	public function to_array(): array {
		return array(
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
			'info'     => $this->info,
			'counts'   => $this->counts,
			'ok'       => $this->ok(),
		);
	}
}
