<?php
/**
 * Translatable segments of post content, and their in-place replacement.
 *
 * extract() lists every translatable string with a stable id; apply() writes translations back
 * without re-serializing the document: only the attribute JSON of the blocks that change (and
 * the text of changed core blocks / classic content) is rewritten, at the same offsets, so all
 * other bytes stay identical (other blocks, text between blocks, non-divi blocks, {} vs []).
 *
 * Segment ids:
 * - `b<n>.<element>[.<key>][.<breakpoint>]` for Divi blocks. n is the index of the block among
 *   `divi/*` opening and void blocks in document order (closers, non-divi blocks and children of
 *   a divi/global-layout are not counted). element is the attr path to `innerContent`, key the
 *   prose key when the value is an object, breakpoint is added for non-desktop variants that
 *   differ from desktop. Ids that would collide fall back to the full attr path.
 * - `gb<n>` for the HTML of a core (non-divi) block, n counting non-divi opening and void
 *   blocks from 0; `gb<n>.<k>` for its k-th HTML run when it also has inner blocks.
 * - `c` for classic (non-block) content, `c.<k>` for loose HTML between top-level blocks.
 *
 * `html` segments are masked with Protect (⟦n⟧ markers). A translation of a segment is used
 * only when it keeps every marker exactly once and the same tag-name sequence as the source;
 * otherwise the source text stays and the id is reported in `fallbacks`.
 *
 * Pure PHP, no WordPress or language-plugin dependency.
 *
 * @package WPDM
 */

namespace WPDM\I18n;

use WPDM\Divi\BlockScanner;
use WPDM\Divi\D5;

/**
 * Segments engine.
 */
final class Segments {

	/**
	 * Keys of an object value that hold prose.
	 */
	const PROSE_KEYS = array( 'text', 'title', 'alt', 'caption', 'label', 'placeholder', 'titleText' );

	/**
	 * Keys that never hold prose.
	 */
	const NEVER_PROSE = array( 'src', 'linkUrl', 'url', 'id', 'width', 'height', 'sizes', 'srcset', 'icon', 'linkTarget' );

	/**
	 * Names of custom HTML attributes (module.decoration.attributes) that hold prose.
	 */
	const PROSE_ATTRIBUTES = array( 'alt', 'title', 'aria-label', 'placeholder' );

	/**
	 * Attr keys holding links that $link_map / $post_map rewrite.
	 */
	const LINK_KEYS = array( 'linkUrl', 'url' );

	/**
	 * Translatable segments of $content.
	 *
	 * @param string $content Post content (valid UTF-8).
	 * @return array{segments: array<int, array{id: string, kind: string, context: string, text: string}>, globals: array<string, int[]>, code_blocks: int, legacy: bool}
	 *         globals maps each global module library id to the b-indexes of the
	 *         divi/global-layout blocks that use it (PHP turns numeric keys into ints).
	 */
	public static function extract( string $content ): array {
		$a        = self::analyze( $content );
		$segments = array();
		foreach ( $a['segments'] as $seg ) {
			$segments[] = array(
				'id'      => $seg['id'],
				'kind'    => $seg['kind'],
				'context' => $seg['context'],
				'text'    => $seg['text'],
			);
		}
		return array(
			'segments'    => $segments,
			'globals'     => $a['globals'],
			'code_blocks' => $a['code_blocks'],
			'legacy'      => false !== strpos( $content, '[et_pb_' ),
		);
	}

	/**
	 * Write translations into $content in place.
	 *
	 * @param string        $content      Source post content.
	 * @param array         $translations Segment id => translated text (masked for html segments).
	 * @param array         $global_map   Source library id => [target library id, target library content].
	 * @param callable|null $link_map     fn(string $url): ?string, for hrefs in translated html
	 *                                    segments and linkUrl / url attrs (null keeps the original).
	 * @param callable|null $post_map     fn(int $id): ?int, for the post_id of `$variable(...)$`
	 *                                    post links (null keeps the original).
	 * @param callable|null $html_filter  fn(string $html): string, run on each translated html
	 *                                    segment after its protected parts are restored (kses).
	 * @return array{content: string, fallbacks: string[], unknown: string[]}
	 *         fallbacks: ids whose translation was rejected (source kept); unknown: ids that are
	 *         not segments of $content.
	 */
	public static function apply( string $content, array $translations, array $global_map = array(), ?callable $link_map = null, ?callable $post_map = null, ?callable $html_filter = null ): array {
		$a         = self::analyze( $content );
		$blocks    = $a['blocks'];
		$attrs     = $a['attrs'];
		$changed   = array();
		$edits     = array();
		$fallbacks = array();
		$known     = array();

		foreach ( $a['segments'] as $seg ) {
			$known[ $seg['id'] ] = true;
			if ( ! array_key_exists( $seg['id'], $translations ) ) {
				continue;
			}
			$value = self::resolve( $seg, $translations[ $seg['id'] ], $link_map, $post_map, $html_filter );
			if ( null === $value ) {
				$fallbacks[] = $seg['id'];
				continue;
			}
			if ( isset( $seg['block'] ) ) {
				$i = $seg['block'];
				foreach ( array_merge( array( $seg['path'] ), $seg['fills'] ) as $path ) {
					$attrs[ $i ] = self::set_path( $attrs[ $i ], $path, $value );
				}
				$changed[ $i ] = true;
			} else {
				$edits[] = array( $seg['start'], $seg['end'], $value );
			}
		}

		$unknown = array();
		foreach ( array_keys( $translations ) as $id ) {
			if ( ! isset( $known[ (string) $id ] ) ) {
				$unknown[] = (string) $id;
			}
		}

		if ( null !== $link_map || null !== $post_map ) {
			foreach ( $a['link_blocks'] as $i ) {
				$dirty       = false;
				$attrs[ $i ] = self::map_links( $attrs[ $i ], $link_map, $post_map, $dirty );
				if ( $dirty ) {
					$changed[ $i ] = true;
				}
			}
		}

		foreach ( $a['global_layouts'] as $i => $closer ) {
			$gm = $attrs[ $i ]->globalModule ?? null;
			if ( ! is_scalar( $gm ) || ! isset( $global_map[ (string) $gm ] ) ) {
				continue;
			}
			$target    = array_values( (array) $global_map[ (string) $gm ] );
			$target_id = (string) ( $target[0] ?? '' );
			if ( '' === $target_id ) {
				continue;
			}
			$attrs[ $i ]->globalModule = is_int( $gm ) ? (int) $target_id : $target_id;
			$changed[ $i ]             = true;
			if ( null !== $closer && isset( $target[1] ) && is_string( $target[1] ) ) {
				$block_name = isset( $attrs[ $i ]->blockName ) && is_string( $attrs[ $i ]->blockName ) ? $attrs[ $i ]->blockName : 'divi/section';
				$start      = $blocks[ $i ]['end'];
				$end        = $blocks[ $closer ]['start'];
				$inner      = self::global_children( $target[1], $block_name, $target_id );
				$edits[]    = array( $start, $end, self::keep_padding( substr( $content, $start, $end - $start ), $inner ) );
			}
		}

		foreach ( array_keys( $changed ) as $i ) {
			$edits[] = self::attrs_edit( $content, $blocks[ $i ], $attrs[ $i ] );
		}

		return array(
			'content'   => self::splice( $content, $edits ),
			'fallbacks' => $fallbacks,
			'unknown'   => $unknown,
		);
	}

	// ------------------------------------------------------------------ //
	// Analysis
	// ------------------------------------------------------------------ //

	/**
	 * Walk the content once: segments with their locations, globals, code blocks, link blocks.
	 *
	 * @param string $content Content.
	 * @return array
	 */
	private static function analyze( $content ) {
		$st = array(
			'blocks'         => BlockScanner::scan( $content ),
			'attrs'          => array(),
			'segments'       => array(),
			'ids'            => array(),
			'globals'        => array(),
			'global_layouts' => array(),
			'link_blocks'    => array(),
			'code_blocks'    => 0,
		);

		$stack    = array(); // Open blocks: [scan index, name, core index|null, runs].
		$top_runs = 0;
		$skip     = null;    // Stack depth of the open global-layout whose children are skipped.
		$n        = 0;
		$gb       = 0;
		$section  = 0;
		$prev     = 0;

		foreach ( $st['blocks'] as $i => $b ) {
			if ( null === $skip ) {
				self::run( $st, $content, $prev, $b['start'], $stack, $top_runs );
			}
			$prev = $b['end'];

			if ( $b['closer'] ) {
				for ( $d = count( $stack ) - 1; $d >= 0; $d-- ) {
					if ( $stack[ $d ][1] === $b['name'] ) {
						if ( null !== $skip && $d <= $skip ) {
							$open = $stack[ $skip ][0];
							if ( $d === $skip && array_key_exists( $open, $st['global_layouts'] ) ) {
								$st['global_layouts'][ $open ] = $i;
							}
							$skip = null;
						}
						array_splice( $stack, $d );
						break;
					}
				}
				continue;
			}

			$core = null;
			if ( null === $skip ) {
				if ( 0 === strpos( $b['name'], 'divi/' ) ) {
					$idx   = $n++;
					$attrs = self::decode( $b['raw'] );
					if ( 'divi/section' === $b['name'] ) {
						++$section;
					}
					if ( 'divi/global-layout' === $b['name'] ) {
						if ( null !== $attrs ) {
							$st['attrs'][ $i ] = $attrs;
							$gm                = $attrs->globalModule ?? null;
							if ( is_scalar( $gm ) && '' !== (string) $gm ) {
								$st['globals'][ (string) $gm ][] = $idx;
							}
							if ( 'divi/section' === ( $attrs->blockName ?? null ) ) {
								++$section;
							}
							$st['global_layouts'][ $i ] = null;
						}
						if ( ! $b['void'] ) {
							$skip = count( $stack );
						}
					} else {
						if ( 'divi/code' === $b['name'] ) {
							++$st['code_blocks'];
						}
						if ( null !== $attrs ) {
							$st['attrs'][ $i ]     = $attrs;
							$st['link_blocks'][]   = $i;
							$label                 = $attrs->module->meta->adminLabel->desktop->value ?? null;
							$where                 = $b['name'] . ( is_string( $label ) && '' !== $label ? " '" . $label . "'" : '' );
							$where                .= $section > 0 ? ' in section ' . $section : '';
							self::walk_attrs( $st, $i, $idx, $where, $attrs, array() );
							self::custom_attributes( $st, $i, $idx, $where, $attrs );
						}
					}
				} else {
					$core = $gb++;
				}
			}
			if ( ! $b['void'] ) {
				$stack[] = array( $i, $b['name'], $core, 0 );
			}
		}
		if ( null === $skip ) {
			self::run( $st, $content, $prev, strlen( $content ), $stack, $top_runs );
		}
		return $st;
	}

	/**
	 * HTML between two block comments: a segment when it belongs to a core block or to no block.
	 *
	 * @param array  $st       State.
	 * @param string $content  Content.
	 * @param int    $start    Start offset.
	 * @param int    $end      End offset.
	 * @param array  $stack    Open blocks.
	 * @param int    $top_runs Runs seen outside any block.
	 * @return void
	 */
	private static function run( array &$st, $content, $start, $end, array &$stack, &$top_runs ) {
		if ( $stack ) {
			$top = count( $stack ) - 1;
			if ( null === $stack[ $top ][2] ) {
				return; // Inside a divi block: only whitespace between child blocks.
			}
			$k       = $stack[ $top ][3]++;
			$base    = 'gb' . $stack[ $top ][2];
			$context = ( false === strpos( $stack[ $top ][1], '/' ) ? 'core/' : '' ) . $stack[ $top ][1];
		} else {
			$k       = $top_runs++;
			$base    = 'c';
			$context = 'classic content';
		}
		$raw  = substr( $content, $start, $end - $start );
		$lead = strspn( $raw, " \t\r\n" );
		$html = trim( $raw, " \t\r\n" );
		if ( '' === $html ) {
			return;
		}
		$m = Protect::mask( $html );
		if ( ! self::has_text( $m['text'] ) ) {
			return;
		}
		$id                  = $k > 0 ? $base . '.' . $k : $base;
		$st['ids'][ $id ]    = true;
		$st['segments'][]    = array(
			'id'      => $id,
			'kind'    => 'html',
			'context' => $context,
			'text'    => $m['text'],
			'source'  => $html,
			'parts'   => $m['parts'],
			'start'   => $start + $lead,
			'end'     => $start + $lead + strlen( $html ),
		);
	}

	/**
	 * Find innerContent values in a block's attrs.
	 *
	 * @param array  $st    State.
	 * @param int    $i     Scan index.
	 * @param int    $idx   Block index (b<n>).
	 * @param string $where Context.
	 * @param mixed  $node  Attr node.
	 * @param array  $path  Path to $node.
	 * @return void
	 */
	private static function walk_attrs( array &$st, $i, $idx, $where, $node, array $path ) {
		if ( ! is_object( $node ) && ! is_array( $node ) ) {
			return;
		}
		foreach ( $node as $key => $value ) {
			$sub   = $path;
			$sub[] = $key;
			if ( 'innerContent' === $key && is_object( $value ) ) {
				self::inner_content( $st, $i, $idx, $where, $value, $path );
			} else {
				self::walk_attrs( $st, $i, $idx, $where, $value, $sub );
			}
		}
	}

	/**
	 * Segments of one innerContent value: desktop first, then the other breakpoints.
	 *
	 * @param array     $st      State.
	 * @param int       $i       Scan index.
	 * @param int       $idx     Block index.
	 * @param string    $where   Context.
	 * @param \stdClass $ic      innerContent.
	 * @param array     $element Path to the element (the key holding innerContent).
	 * @return void
	 */
	private static function inner_content( array &$st, $i, $idx, $where, $ic, array $element ) {
		$values = array();
		foreach ( $ic as $bp => $state ) {
			if ( is_object( $state ) && property_exists( $state, 'value' ) ) {
				$values[ (string) $bp ] = $state->value;
			}
		}
		if ( isset( $values['desktop'] ) ) {
			$values = array( 'desktop' => $values['desktop'] ) + $values;
		}
		$desktop = array(); // key ('' for a string value) => segment index.
		foreach ( $values as $bp => $value ) {
			$base = array_merge( $element, array( 'innerContent', $bp, 'value' ) );
			if ( is_string( $value ) ) {
				$last = end( $element );
				if ( in_array( (string) $last, self::NEVER_PROSE, true ) ) {
					continue;
				}
				self::value( $st, $i, $idx, $where, $element, null, $bp, $value, $base, $values, $desktop );
			} elseif ( is_object( $value ) ) {
				foreach ( $value as $key => $text ) {
					if ( is_string( $text ) && in_array( (string) $key, self::PROSE_KEYS, true ) ) {
						$path   = $base;
						$path[] = $key;
						self::value( $st, $i, $idx, $where, $element, (string) $key, $bp, $text, $path, $values, $desktop );
					}
				}
			}
		}
	}

	/**
	 * Segments of custom HTML attributes whose name is prose (alt, title, aria-label,
	 * placeholder), stored as module.decoration.attributes.<bp>.value.attributes[] and as a
	 * legacy top-level copy module.decoration.attributes.attributes[]. Ids are
	 * `b<n>.attr.<entry id>` (the entry index when it has no id), plus `.<bp>` for other
	 * breakpoints and `.legacy` for the legacy copy when they differ from desktop; equal
	 * values are filled by the desktop translation.
	 *
	 * @param array     $st    State.
	 * @param int       $i     Scan index.
	 * @param int       $idx   Block index.
	 * @param string    $where Context.
	 * @param \stdClass $attrs Block attrs.
	 * @return void
	 */
	private static function custom_attributes( array &$st, $i, $idx, $where, $attrs ) {
		$node = $attrs->module->decoration->attributes ?? null;
		if ( ! is_object( $node ) ) {
			return;
		}
		$base  = array( 'module', 'decoration', 'attributes' );
		$lists = array(); // suffix => [path to the list, list].
		foreach ( $node as $bp => $state ) {
			if ( 'attributes' !== $bp && is_object( $state ) && isset( $state->value->attributes ) && is_array( $state->value->attributes ) ) {
				$lists[ (string) $bp ] = array( array_merge( $base, array( $bp, 'value', 'attributes' ) ), $state->value->attributes );
			}
		}
		if ( isset( $lists['desktop'] ) ) {
			$lists = array( 'desktop' => $lists['desktop'] ) + $lists;
		}
		if ( isset( $node->attributes ) && is_array( $node->attributes ) ) {
			$lists['legacy'] = array( array_merge( $base, array( 'attributes' ) ), $node->attributes );
		}
		$desktop = array(); // entry key => [segment index, desktop value].
		foreach ( $lists as $suffix => $list ) {
			foreach ( $list[1] as $k => $entry ) {
				if ( ! is_object( $entry ) || ! isset( $entry->name, $entry->value ) || ! is_string( $entry->name ) || ! is_string( $entry->value ) ) {
					continue;
				}
				$name = strtolower( $entry->name );
				if ( ! in_array( $name, self::PROSE_ATTRIBUTES, true ) ) {
					continue;
				}
				$key  = isset( $entry->id ) && is_scalar( $entry->id ) && '' !== (string) $entry->id ? (string) $entry->id : (string) $k;
				$path = array_merge( $list[0], array( $k, 'value' ) );
				if ( 'desktop' !== $suffix && isset( $desktop[ $key ] ) && $desktop[ $key ][1] === $entry->value ) {
					$st['segments'][ $desktop[ $key ][0] ]['fills'][] = $path;
					continue;
				}
				if ( self::skip( $entry->value ) ) {
					continue;
				}
				$id = 'b' . $idx . '.attr.' . $key . ( 'desktop' === $suffix ? '' : '.' . $suffix );
				if ( isset( $st['ids'][ $id ] ) ) {
					$id = 'b' . $idx . '.' . implode( '.', $path );
				}
				$st['ids'][ $id ] = true;
				$st['segments'][] = array(
					'id'      => $id,
					'kind'    => 'text',
					'context' => $where . ' custom attribute ' . $name . ( 'desktop' === $suffix ? '' : ' (' . $suffix . ')' ),
					'text'    => $entry->value,
					'source'  => $entry->value,
					'parts'   => array(),
					'block'   => $i,
					'path'    => $path,
					'fills'   => array(),
				);
				if ( 'desktop' === $suffix ) {
					$desktop[ $key ] = array( count( $st['segments'] ) - 1, $entry->value );
				}
			}
		}
	}

	/**
	 * Add one string value as a segment, or as a fill of the equal desktop segment.
	 *
	 * @param array       $st      State.
	 * @param int         $i       Scan index.
	 * @param int         $idx     Block index.
	 * @param string      $where   Context.
	 * @param array       $element Element path.
	 * @param string|null $key     Prose key of an object value, null for a string value.
	 * @param string      $bp      Breakpoint.
	 * @param string      $text    Value.
	 * @param array       $path    Full attr path of the value.
	 * @param array       $values  Breakpoint => value of this innerContent.
	 * @param array       $desktop Key => desktop segment index.
	 * @return void
	 */
	private static function value( array &$st, $i, $idx, $where, array $element, $key, $bp, $text, array $path, array $values, array &$desktop ) {
		$k = null === $key ? '' : $key;
		if ( 'desktop' !== $bp && isset( $desktop[ $k ] ) ) {
			$dv = $values['desktop'];
			$dv = null === $key ? $dv : ( is_object( $dv ) ? ( $dv->{$key} ?? null ) : null );
			if ( $dv === $text ) {
				$st['segments'][ $desktop[ $k ] ]['fills'][] = $path;
				return;
			}
		}
		if ( self::skip( $text ) ) {
			return;
		}
		$m    = Protect::mask( $text );
		$html = ( null === $key && 'content' === (string) end( $element ) ) || false !== strpos( $text, '<' ) || $m['parts'];
		if ( $html && ! self::has_text( $m['text'] ) ) {
			return;
		}
		$id = 'b' . $idx . '.' . ( $element ? implode( '.', $element ) : 'innerContent' ) . ( null === $key ? '' : '.' . $key ) . ( 'desktop' === $bp ? '' : '.' . $bp );
		if ( isset( $st['ids'][ $id ] ) ) {
			$id = 'b' . $idx . '.' . implode( '.', $path );
		}
		$st['ids'][ $id ] = true;
		$st['segments'][] = array(
			'id'      => $id,
			'kind'    => $html ? 'html' : 'text',
			'context' => $where . ( null === $key ? '' : ' ' . $key ) . ( 'desktop' === $bp ? '' : ' (' . $bp . ')' ),
			'text'    => $html ? $m['text'] : $text,
			'source'  => $text,
			'parts'   => $html ? $m['parts'] : array(),
			'block'   => $i,
			'path'    => $path,
			'fills'   => array(),
		);
		if ( 'desktop' === $bp ) {
			$desktop[ $k ] = count( $st['segments'] ) - 1;
		}
	}

	/**
	 * Values that are not prose: empty or whitespace only, a URL, a number, a color, or a
	 * Divi variable reference.
	 *
	 * @param string $text Value.
	 * @return bool
	 */
	private static function skip( $text ) {
		$t = self::trim_ws( $text );
		if ( '' === $t ) {
			return true;
		}
		if ( preg_match( '/^\$variable\(.*\)\$$/s', $t ) ) {
			return true;
		}
		if ( ! preg_match( '/\s/', $t ) && preg_match( '#^(?:[a-z][a-z0-9+.-]*://|//|/|\#|\?|mailto:|tel:|sms:|www\.)#i', $t ) ) {
			return true;
		}
		if ( preg_match( '/^[+-]?[\d\s.,]*\d[\d\s.,]*[%+]?$/', $t ) ) {
			return true;
		}
		return (bool) preg_match( '/^(?:#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})|(?:rgba?|hsla?)\([^()]*\)|transparent)$/i', $t );
	}

	/**
	 * Whether masked HTML has anything to translate: text outside tags and markers, or an
	 * alt / title attribute.
	 *
	 * @param string $masked Masked HTML.
	 * @return bool
	 */
	private static function has_text( $masked ) {
		$t = (string) preg_replace( Protect::MARKER_RE, '', $masked );
		if ( preg_match( '/<[A-Za-z][^>]*\s(?:alt|title)\s*=\s*["\']?[^\s"\'>]/i', $t ) ) {
			return true;
		}
		$t = (string) preg_replace( '/<\/?[A-Za-z][^>]*+>/', '', $t );
		return '' !== self::trim_ws( html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Trim Unicode whitespace (and zero-width spaces).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function trim_ws( $text ) {
		$t = preg_replace( '/^[\s\x{00A0}\x{1680}\x{2000}-\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+|[\s\x{00A0}\x{1680}\x{2000}-\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+$/u', '', $text );
		return null === $t ? trim( $text ) : $t;
	}

	/**
	 * Decode block attrs keeping {} as objects; null when absent or invalid.
	 *
	 * @param string|null $raw Attribute group.
	 * @return \stdClass|null
	 */
	private static function decode( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		$v = json_decode( $raw, false );
		return $v instanceof \stdClass ? $v : null;
	}

	// ------------------------------------------------------------------ //
	// Apply
	// ------------------------------------------------------------------ //

	/**
	 * The text to write for a segment, or null when the translation is rejected.
	 *
	 * @param array         $seg      Segment.
	 * @param mixed         $tr       Translation.
	 * @param callable|null $link_map Link map.
	 * @param callable|null $post_map Post map.
	 * @param callable|null $filter   HTML filter, run after unmasking (html segments only).
	 * @return string|null
	 */
	private static function resolve( array $seg, $tr, $link_map, $post_map, $filter = null ) {
		if ( ! is_string( $tr ) && ! is_int( $tr ) && ! is_float( $tr ) ) {
			return null;
		}
		$tr = (string) $tr;
		if ( '' === self::trim_ws( $tr ) || Protect::tags( $tr ) !== Protect::tags( $seg['text'] ) ) {
			return null;
		}
		if ( 'html' === $seg['kind'] ) {
			$tr = self::map_hrefs( $tr, $link_map );
			$tr = self::map_posts( $tr, $post_map );
		}
		$out = Protect::unmask( $tr, $seg['parts'] );
		if ( null !== $out && null !== $filter && 'html' === $seg['kind'] ) {
			$out = (string) $filter( $out );
		}
		return $out;
	}

	/**
	 * Rewrite href values in (masked) HTML with $link_map.
	 *
	 * @param string        $html     HTML.
	 * @param callable|null $link_map Link map.
	 * @return string
	 */
	private static function map_hrefs( $html, $link_map ) {
		if ( null === $link_map ) {
			return $html;
		}
		$out = preg_replace_callback(
			'/(\bhref\s*=\s*)(?:"([^"]*)"|\'([^\']*)\')/i',
			static function ( $m ) use ( $link_map ) {
				$double = ! isset( $m[3] );
				$url    = $double ? $m[2] : $m[3];
				$new    = '' === $url ? null : $link_map( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( ! is_string( $new ) ) {
					return $m[0];
				}
				$new = htmlspecialchars( $new, ENT_QUOTES, 'UTF-8', false );
				return $m[1] . ( $double ? '"' . $new . '"' : "'" . $new . "'" );
			},
			$html
		);
		return null === $out ? $html : $out;
	}

	/**
	 * Map the post_id of `$variable(...)$` post links with $post_map.
	 *
	 * @param string        $text     Text.
	 * @param callable|null $post_map Post map.
	 * @return string
	 */
	private static function map_posts( $text, $post_map ) {
		if ( null === $post_map || false === strpos( $text, '$variable(' ) ) {
			return $text;
		}
		$out = preg_replace_callback(
			'/\$variable\((\{.*?\})\)\$/s',
			static function ( $m ) use ( $post_map ) {
				if ( ! preg_match( '/"name"\s*:\s*"post_link_url_/', $m[1] ) ) {
					return $m[0];
				}
				$inner = preg_replace_callback(
					'/("post_id"\s*:\s*)("?)(\d+)\2/',
					static function ( $p ) use ( $post_map ) {
						$new = $post_map( (int) $p[3] );
						return is_int( $new ) && $new > 0 ? $p[1] . $p[2] . $new . $p[2] : $p[0];
					},
					$m[1]
				);
				return null === $inner ? $m[0] : '$variable(' . $inner . ')$';
			},
			$text
		);
		return null === $out ? $text : $out;
	}

	/**
	 * Rewrite linkUrl / url values of an attr tree (styling under `decoration` is left alone).
	 *
	 * @param mixed         $node     Attr node.
	 * @param callable|null $link_map Link map.
	 * @param callable|null $post_map Post map.
	 * @param bool          $dirty    Set when something changed.
	 * @return mixed
	 */
	private static function map_links( $node, $link_map, $post_map, &$dirty ) {
		if ( ! is_object( $node ) && ! is_array( $node ) ) {
			return $node;
		}
		foreach ( $node as $key => $value ) {
			if ( 'decoration' === $key ) {
				continue;
			}
			if ( is_string( $value ) && in_array( (string) $key, self::LINK_KEYS, true ) ) {
				if ( false !== strpos( $value, '$variable(' ) ) {
					$new = self::map_posts( $value, $post_map );
				} else {
					$new = null !== $link_map && '' !== $value ? $link_map( $value ) : null;
				}
				if ( ! is_string( $new ) || $new === $value ) {
					continue;
				}
				$dirty = true;
			} else {
				$new = self::map_links( $value, $link_map, $post_map, $dirty );
			}
			if ( is_object( $node ) ) {
				$node->{$key} = $new;
			} else {
				$node[ $key ] = $new;
			}
		}
		return $node;
	}

	/**
	 * Set a value at a path of a decoded attr tree.
	 *
	 * @param mixed $node  Node.
	 * @param array $path  Keys.
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function set_path( $node, array $path, $value ) {
		if ( ! $path ) {
			return $value;
		}
		$key = array_shift( $path );
		if ( is_object( $node ) ) {
			$node->{$key} = self::set_path( $node->{$key}, $path, $value );
		} elseif ( is_array( $node ) ) {
			$node[ $key ] = self::set_path( $node[ $key ], $path, $value );
		}
		return $node;
	}

	/**
	 * The edit that rewrites one block's attribute JSON (leading whitespace kept).
	 *
	 * @param string    $content Content.
	 * @param array     $block   Scanned block.
	 * @param \stdClass $attrs   New attrs.
	 * @return array{0: int, 1: int, 2: string}
	 */
	private static function attrs_edit( $content, array $block, $attrs ) {
		$json = D5::encode_attrs( $attrs );
		if ( null === $block['raw'] ) {
			$at = strpos( $content, 'wp:' . $block['name'], $block['start'] ) + 3 + strlen( $block['name'] );
			return array( $at, $at, ' ' . $json );
		}
		$comment = substr( $content, $block['start'], $block['end'] - $block['start'] );
		$at      = $block['start'] + strpos( $comment, $block['raw'] );
		$ws      = substr( $block['raw'], 0, strpos( $block['raw'], '{' ) ); // raw is `\s+{...}`.
		return array( $at, $at + strlen( $block['raw'] ), $ws . $json );
	}

	/**
	 * Apply non-overlapping edits [start, end, replacement], from the end backwards.
	 *
	 * @param string $content Content.
	 * @param array  $edits   Edits.
	 * @return string
	 */
	private static function splice( $content, array $edits ) {
		usort(
			$edits,
			static function ( $x, $y ) {
				return $y[0] <=> $x[0];
			}
		);
		foreach ( $edits as $e ) {
			$content = substr_replace( $content, $e[2], $e[0], $e[1] - $e[0] );
		}
		return $content;
	}

	// ------------------------------------------------------------------ //
	// Global modules
	// ------------------------------------------------------------------ //

	/**
	 * Inline children for a divi/global-layout from the target library item's content: the
	 * divi/placeholder wrapper and the item's own $block_name block are unwrapped, and every
	 * divi block gets "globalParent" set to the target id.
	 *
	 * @param string $content    Library item content.
	 * @param string $block_name The global-layout's blockName.
	 * @param string $target_id  Target library id.
	 * @return string
	 */
	private static function global_children( $content, $block_name, $target_id ) {
		$inner = self::inner_of_single( $content, 'divi/placeholder' );
		if ( null !== $inner ) {
			$content = $inner;
		}
		$inner = self::inner_of_single( $content, $block_name );
		if ( null !== $inner ) {
			$content = $inner;
		}
		$content = trim( $content, " \t\r\n" );
		$edits   = array();
		foreach ( BlockScanner::scan( $content ) as $b ) {
			if ( $b['closer'] || 0 !== strpos( $b['name'], 'divi/' ) ) {
				continue;
			}
			$attrs = null === $b['raw'] ? new \stdClass() : self::decode( $b['raw'] );
			if ( null === $attrs ) {
				continue;
			}
			$attrs->globalParent = $target_id;
			$edits[]             = self::attrs_edit( $content, $b, $attrs );
		}
		return self::splice( $content, $edits );
	}

	/**
	 * Inner markup when $content is exactly one $name block (surrounding whitespace allowed).
	 *
	 * @param string $content Content.
	 * @param string $name    Block name.
	 * @return string|null
	 */
	private static function inner_of_single( $content, $name ) {
		$blocks = BlockScanner::scan( $content );
		$lead   = strspn( $content, " \t\r\n" );
		if ( ! $blocks || $blocks[0]['start'] !== $lead || $blocks[0]['closer'] || $blocks[0]['void'] || $blocks[0]['name'] !== $name ) {
			return null;
		}
		$depth = 0;
		foreach ( $blocks as $j => $b ) {
			if ( $b['void'] ) {
				continue;
			}
			$depth += $b['closer'] ? -1 : 1;
			if ( 0 === $depth ) {
				if ( $b['name'] !== $name || '' !== trim( substr( $content, $b['end'] ), " \t\r\n" ) ) {
					return null;
				}
				return substr( $content, $blocks[0]['end'], $b['start'] - $blocks[0]['end'] );
			}
		}
		return null;
	}

	/**
	 * Replace the middle of $old with $inner, keeping $old's leading and trailing whitespace.
	 *
	 * @param string $old   Old children markup.
	 * @param string $inner New children markup.
	 * @return string
	 */
	private static function keep_padding( $old, $inner ) {
		$lead = strspn( $old, " \t\r\n" );
		if ( $lead === strlen( $old ) ) {
			return '' === $inner ? $old : $old . $inner . $old;
		}
		$trail = strlen( $old ) - strlen( rtrim( $old, " \t\r\n" ) );
		return substr( $old, 0, $lead ) . $inner . substr( $old, strlen( $old ) - $trail );
	}
}
