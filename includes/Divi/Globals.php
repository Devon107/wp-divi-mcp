<?php
/**
 * Divi 5 global colors and variables: read, safe merge, restore.
 *
 * @package WPDM
 */

namespace WPDM\Divi;

use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps ET\Builder\Packages\GlobalData\GlobalData (Divi 5.19.1,
 * includes/builder-5/server/Packages/GlobalData/GlobalData.php).
 *
 * Rules: merge by id; never overwrite an existing value without
 * overwrite=true; never touch the built-in (Customizer) colors; back up the
 * current globals before any write.
 */
final class Globals {

	/** Customizer colors, stored as theme options (GlobalData::$customizer_colors). Never touched. */
	const BUILTIN_COLORS = array( 'gcid-primary-color', 'gcid-secondary-color', 'gcid-heading-color', 'gcid-body-color', 'gcid-link-color' );

	/** Copy of the private GlobalData::GLOBAL_VARIABLE_TYPES (GlobalData.php:31). */
	const VARIABLE_TYPES = array( 'numbers', 'strings', 'images', 'links', 'fonts', 'gradients' );

	/**
	 * Current global colors and variables.
	 *
	 * Colors: GlobalData::get_global_colors() (GlobalData.php:373), keyed by gcid,
	 * Customizer colors included. Variables: GlobalData::get_global_variables()
	 * (GlobalData.php:1283), {type: {gvid: {...}}}, converted from objects to arrays.
	 *
	 * @return array{colors: array, variables: array}
	 * @throws ToolError When Divi 5 is not active.
	 */
	public static function read(): array {
		$global_data = self::global_data();
		$variables   = json_decode( (string) wp_json_encode( $global_data::get_global_variables() ), true );
		return array(
			'colors'    => $global_data::get_global_colors(),
			'variables' => is_array( $variables ) ? $variables : array(),
		);
	}

	/**
	 * Merge global colors and variables into the site, by id.
	 *
	 * Ported from the divi5-mcp-companion /globals/merge route; the report shape
	 * is identical, plus backup_id (int when a backup was taken, else null).
	 *
	 * @param array  $colors           List of [gcid, {color, label?}].
	 * @param array  $variables        List of {id, type, value, label?, status?}.
	 * @param bool   $overwrite        Replace existing values that differ.
	 * @param bool   $dry_run          Report only.
	 * @param string $connection_label Label recorded on the backup.
	 * @return array Report.
	 * @throws ToolError invalid_globals (nothing written), forbidden, globals_write_failed.
	 */
	public static function merge( array $colors, array $variables, bool $overwrite, bool $dry_run, string $connection_label = '' ): array {
		$global_data = self::global_data();
		self::validate_colors( $colors );
		self::validate_variables( $variables );

		$report = array(
			'colors'    => array(
				'added'           => array(),
				'same'            => array(),
				'conflicts'       => array(),
				'overwritten'     => array(),
				'skipped_builtin' => array(),
			),
			'variables' => array(
				'added'       => array(),
				'same'        => array(),
				'conflicts'   => array(),
				'overwritten' => array(),
			),
		);

		// ---- colors ----------------------------------------------------------
		$existing_colors = $global_data::get_global_colors();
		$to_import       = array();
		foreach ( $colors as $entry ) {
			$id    = sanitize_text_field( $entry[0] );
			$color = (string) $entry[1]['color'];
			if ( in_array( $id, self::BUILTIN_COLORS, true ) ) {
				$report['colors']['skipped_builtin'][] = $id;
				continue;
			}
			if ( isset( $existing_colors[ $id ] ) ) {
				$current = isset( $existing_colors[ $id ]['color'] ) ? (string) $existing_colors[ $id ]['color'] : '';
				if ( strtolower( $current ) === strtolower( $color ) ) {
					$report['colors']['same'][] = $id;
					continue;
				}
				if ( ! $overwrite ) {
					$report['colors']['conflicts'][] = array(
						'id'   => $id,
						'site' => $current,
						'file' => $color,
					);
					continue;
				}
				$report['colors']['overwritten'][] = $id;
			} else {
				$report['colors']['added'][] = $id;
			}
			$to_import[] = array(
				$id,
				array(
					'color'  => $color,
					'status' => 'active',
					'label'  => isset( $entry[1]['label'] ) ? (string) $entry[1]['label'] : $id,
				),
			);
		}

		// ---- variables -------------------------------------------------------
		$flat = array();
		foreach ( (array) $global_data::get_global_variables() as $group ) {
			foreach ( (array) $group as $vid => $v ) {
				$flat[ $vid ] = (array) $v;
			}
		}
		$vars_to_import = array();
		foreach ( $variables as $v ) {
			$id    = $v['id'];
			$value = (string) $v['value'];
			if ( isset( $flat[ $id ] ) ) {
				$current = isset( $flat[ $id ]['value'] ) ? (string) $flat[ $id ]['value'] : '';
				if ( $current === $value ) {
					$report['variables']['same'][] = $id;
					continue;
				}
				if ( ! $overwrite ) {
					$report['variables']['conflicts'][] = array(
						'id'   => $id,
						'site' => $current,
						'file' => $value,
					);
					continue;
				}
				$report['variables']['overwritten'][] = $id;
			} else {
				$report['variables']['added'][] = $id;
			}
			// Divi's sanitizer drops variables without a label (GlobalData.php:855-870), so default it.
			$label = isset( $v['label'] ) && '' !== $v['label'] ? $v['label'] : ( isset( $flat[ $id ]['label'] ) && '' !== (string) $flat[ $id ]['label'] ? (string) $flat[ $id ]['label'] : $id );

			$vars_to_import[] = array(
				'id'     => $id,
				'type'   => $v['type'],
				'value'  => $value,
				'label'  => $label,
				'status' => $v['status'] ?? 'active',
			);
		}

		$report['dry_run']   = $dry_run;
		$report['backup_id'] = null;

		if ( $dry_run || ( ! $to_import && ! $vars_to_import ) ) {
			return $report;
		}

		self::check_can_write( (bool) $vars_to_import );
		$report['backup_id'] = Backups::save( 'globals', 0, (string) wp_json_encode( self::read() ), array(), $connection_label );

		// Variables first: Divi may reject them (validation, lock); colors cannot fail.
		if ( $vars_to_import ) {
			$import_error = null;
			$global_data::import_global_variables( $vars_to_import, $import_error );
			if ( is_wp_error( $import_error ) ) {
				throw new ToolError(
					'Divi rejected the global variables: ' . $import_error->get_error_message() . ' Nothing was changed.',
					'globals_write_failed',
					array(
						'backup_id' => $report['backup_id'],
						'divi_code' => $import_error->get_error_code(),
					)
				);
			}
		}
		if ( $to_import ) {
			// already_sanitized=true merges into the existing colors (GlobalData.php:718-721).
			$global_data::set_global_colors( $global_data::get_imported_global_colors( $to_import ), true );
		}

		return $report;
	}

	/**
	 * Replace the site's globals with a snapshot from read() (e.g. a backup).
	 *
	 * Colors: GlobalData::set_global_colors($data, false) takes Divi's
	 * replace-all branch after its own sanitizer (GlobalData.php:691, 714-724;
	 * with true it would merge, :718-721, and could not remove colors added
	 * since the snapshot). Built-in Customizer colors are left out, so they are
	 * never touched.
	 * Variables: GlobalData::set_global_variables() (GlobalData.php:1649) writes
	 * the whole set, but Divi refuses any save that omits an existing gvid
	 * (validate_global_variable_ids, GlobalData.php:959): deletion is archiving.
	 * Variables created after the snapshot are therefore kept with
	 * status=archived.
	 *
	 * @param array  $snapshot         {colors, variables} as returned by read().
	 * @param string $connection_label Label recorded on the backup of the replaced state.
	 * @return array{backup_id: int, colors: int, variables: int, variables_archived: string[]}
	 * @throws ToolError invalid_globals, forbidden, globals_write_failed.
	 */
	public static function restore( array $snapshot, string $connection_label = '' ): array {
		$global_data = self::global_data();
		if ( ! isset( $snapshot['colors'], $snapshot['variables'] ) || ! is_array( $snapshot['colors'] ) || ! is_array( $snapshot['variables'] ) ) {
			throw new ToolError( 'A globals snapshot needs "colors" and "variables" objects.', 'invalid_globals' );
		}

		$colors = array();
		foreach ( $snapshot['colors'] as $id => $data ) {
			$id = (string) $id;
			if ( in_array( $id, self::BUILTIN_COLORS, true ) || 0 !== strpos( $id, 'gcid-' ) ) {
				continue;
			}
			if ( ! is_array( $data ) || ! isset( $data['color'] ) || ! is_string( $data['color'] ) ) {
				throw new ToolError( 'Snapshot color "' . $id . '" has no color value.', 'invalid_globals' );
			}
			$colors[ $id ] = $data;
		}

		$variables = array();
		$count     = 0;
		foreach ( self::VARIABLE_TYPES as $type ) {
			$items = $snapshot['variables'][ $type ] ?? array();
			if ( ! is_array( $items ) ) {
				throw new ToolError( 'Snapshot variables "' . $type . '" must be an object.', 'invalid_globals' );
			}
			foreach ( $items as $id => $data ) {
				$id = (string) $id;
				if ( 0 !== strpos( $id, 'gvid-' ) ) {
					continue; // Customizer fonts and other non-gvid entries are not stored here.
				}
				if ( ! is_array( $data ) ) {
					throw new ToolError( 'Snapshot variable "' . $id . '" is not an object.', 'invalid_globals' );
				}
				unset( $data['allowedActions'] );
				if ( ! isset( $data['status'] ) ) {
					$data['status'] = 'active';
				}
				$variables[ $type ][ $id ] = $data;
				++$count;
			}
		}

		// Archive variables that exist now but not in the snapshot.
		$archived = array();
		foreach ( (array) $global_data::get_global_variables() as $type => $items ) {
			if ( ! in_array( $type, self::VARIABLE_TYPES, true ) ) {
				continue;
			}
			foreach ( (array) $items as $id => $data ) {
				$id = (string) $id;
				if ( 0 !== strpos( $id, 'gvid-' ) || isset( $variables[ $type ][ $id ] ) ) {
					continue;
				}
				$data = (array) $data;
				unset( $data['allowedActions'] );
				$data['status']            = 'archived';
				$variables[ $type ][ $id ] = $data;
				$archived[]                = $id;
			}
		}

		self::check_can_write( true );
		$backup_id = Backups::save( 'globals', 0, (string) wp_json_encode( self::read() ), array( 'reason' => 'before_restore' ), $connection_label );

		$error = $global_data::set_global_variables( $variables );
		if ( is_wp_error( $error ) ) {
			throw new ToolError(
				'Divi rejected the restored global variables: ' . $error->get_error_message() . ' Nothing was changed.',
				'globals_write_failed',
				array(
					'backup_id' => $backup_id,
					'divi_code' => $error->get_error_code(),
				)
			);
		}
		$global_data::set_global_colors( $colors, false );

		return array(
			'backup_id'          => $backup_id,
			'colors'             => count( $colors ),
			'variables'          => $count,
			'variables_archived' => $archived,
		);
	}

	// ----- internals ---------------------------------------------------------

	/**
	 * @return string GlobalData class name.
	 * @throws ToolError When Divi 5 is not active.
	 */
	private static function global_data(): string {
		if ( ! Env::divi_active() ) {
			throw new ToolError( 'Divi 5 is not active on this site.', 'divi_not_active' );
		}
		return Env::GLOBAL_DATA;
	}

	/**
	 * Divi's set_global_variables() silently skips the write without these
	 * permissions (GlobalData.php:1650), so check them up front.
	 *
	 * @param bool $variables Whether variables will be written.
	 * @return void
	 * @throws ToolError forbidden.
	 */
	private static function check_can_write( bool $variables ): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new ToolError( 'Changing Divi global colors and variables needs the edit_theme_options capability.', 'forbidden' );
		}
		if ( $variables && function_exists( 'et_pb_is_allowed' ) && ! et_pb_is_allowed( 'variables_manager' ) ) {
			throw new ToolError( 'The Divi role editor does not allow this user to manage global variables.', 'forbidden' );
		}
	}

	/**
	 * @param array $colors Input colors.
	 * @return void
	 * @throws ToolError invalid_globals.
	 */
	private static function validate_colors( array $colors ): void {
		if ( ! self::is_list( $colors ) ) {
			throw new ToolError( 'colors must be a list of [gcid, {color, label?}] pairs.', 'invalid_globals' );
		}
		foreach ( $colors as $i => $entry ) {
			$where = 'colors[' . $i . ']';
			if ( ! is_array( $entry ) || ! self::is_list( $entry ) || 2 !== count( $entry ) || ! is_string( $entry[0] ) || ! is_array( $entry[1] ) ) {
				throw new ToolError( $where . ' must be a [gcid, {color, label?}] pair.', 'invalid_globals' );
			}
			if ( 0 !== strpos( $entry[0], 'gcid-' ) ) {
				throw new ToolError( $where . ': the id "' . $entry[0] . '" must start with "gcid-".', 'invalid_globals' );
			}
			if ( ! isset( $entry[1]['color'] ) || ! is_string( $entry[1]['color'] ) || '' === trim( $entry[1]['color'] ) ) {
				throw new ToolError( $where . ': color must be a non-empty string.', 'invalid_globals' );
			}
			if ( isset( $entry[1]['label'] ) && ! is_string( $entry[1]['label'] ) ) {
				throw new ToolError( $where . ': label must be a string.', 'invalid_globals' );
			}
		}
	}

	/**
	 * @param array $variables Input variables.
	 * @return void
	 * @throws ToolError invalid_globals.
	 */
	private static function validate_variables( array $variables ): void {
		if ( ! self::is_list( $variables ) ) {
			throw new ToolError( 'variables must be a list of {id, type, value} objects.', 'invalid_globals' );
		}
		foreach ( $variables as $i => $v ) {
			$where = 'variables[' . $i . ']';
			if ( ! is_array( $v ) || ! isset( $v['id'], $v['type'] ) || ! array_key_exists( 'value', $v ) || ! is_string( $v['id'] ) || ! is_string( $v['type'] ) ) {
				throw new ToolError( $where . ' must be an {id, type, value} object.', 'invalid_globals' );
			}
			if ( 0 !== strpos( $v['id'], 'gvid-' ) ) {
				throw new ToolError( $where . ': the id "' . $v['id'] . '" must start with "gvid-".', 'invalid_globals' );
			}
			if ( ! in_array( $v['type'], self::VARIABLE_TYPES, true ) ) {
				throw new ToolError( $where . ': type must be one of ' . implode( ', ', self::VARIABLE_TYPES ) . '.', 'invalid_globals' );
			}
			if ( ! is_string( $v['value'] ) && ! is_int( $v['value'] ) && ! is_float( $v['value'] ) ) {
				throw new ToolError( $where . ': value must be a string or a number.', 'invalid_globals' );
			}
			$status = $v['status'] ?? 'active';
			if ( ! in_array( $status, array( 'active', 'archived' ), true ) ) {
				throw new ToolError( $where . ': status must be "active" or "archived".', 'invalid_globals' );
			}
			if ( 'active' === $status && '' === (string) $v['value'] ) {
				throw new ToolError( $where . ': an active variable needs a non-empty value.', 'invalid_globals' );
			}
			if ( isset( $v['label'] ) && ! is_string( $v['label'] ) ) {
				throw new ToolError( $where . ': label must be a string.', 'invalid_globals' );
			}
		}
	}

	/**
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ): bool {
		return array_values( $value ) === $value;
	}

	private function __construct() {}
}
