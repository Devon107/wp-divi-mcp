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
 * - `b<n>.ld<k>.<path>` for a prose value of the k-th `<script type="application/ld+json">` (k
 *   from 1, counted over the strings of block n's attrs in order), path being the dot path in
 *   the JSON (`@graph.0.headline`). The rest of the script stays protected; apply() also sets
 *   `inLanguage` to the target language and maps the site's own URLs with $link_map.
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
	 * JSON-LD keys whose string values are prose.
	 */
	const LD_PROSE_KEYS = array( 'headline', 'alternativeHeadline', 'name', 'description', 'abstract', 'caption', 'text', 'articleBody', 'reviewBody', 'disambiguatingDescription', 'keywords' );

	/**
	 * JSON-LD keys whose string values are links $link_map rewrites.
	 */
	const LD_LINK_KEYS = array( 'url', '@id', 'item', 'mainEntityOfPage' );

	/**
	 * A JSON-LD script tag: opening tag, body, closing tag.
	 */
	const LD_RE = '/(<script\b[^>]*?\btype\s*=\s*["\']?application\/ld\+json["\']?[^>]*>)(.*?)(<\/script\s*>)/is';

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
	 * @param callable|null $html_filter  fn(string $value, string $kind): string, run on every
	 *                                    translated segment (kind html or text) after its
	 *                                    protected parts are restored (kses).
	 * @param string|null   $language     BCP 47 tag (fr-CA) written to every JSON-LD inLanguage
	 *                                    (null keeps the original).
	 * @return array{content: string, fallbacks: string[], unknown: string[]}
	 *         fallbacks: ids whose translation was rejected (source kept); unknown: ids that are
	 *         not segments of $content.
	 */
	public static function apply( string $content, array $translations, array $global_map = array(), ?callable $link_map = null, ?callable $post_map = null, ?callable $html_filter = null, ?string $language = null ): array {
		$a         = self::analyze( $content );
		$blocks    = $a['blocks'];
		$attrs     = $a['attrs'];
		$changed   = array();
		$edits     = array();
		$fallbacks = array();
		$known     = array();
		$ld        = array(); // Scan index => k => dot path => translated value.

		foreach ( $a['segments'] as $seg ) {
			$known[ $seg['id'] ] = true;
			if ( ! array_key_exists( $seg['id'], $translations ) ) {
				continue;
			}
			if ( isset( $seg['ld'] ) ) {
				$tr = $translations[ $seg['id'] ];
				if ( ! is_string( $tr ) || '' === self::trim_ws( $tr ) ) {
					$fallbacks[] = $seg['id'];
					continue;
				}
				$ld[ $seg['block'] ][ $seg['ld'][0] ][ $seg['ld'][1] ] = null === $html_filter ? $tr : (string) $html_filter( $tr, 'text' );
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

		foreach ( $a['ld_blocks'] as $i ) {
			$k           = 0;
			$dirty       = false;
			$block_ld    = $ld[ $i ] ?? array();
			$attrs[ $i ] = self::filter_strings(
				$attrs[ $i ],
				static function ( $s ) use ( &$k, $block_ld, $language, $link_map ) {
					return self::rewrite_ld( $s, $k, $block_ld, $language, $link_map );
				},
				$dirty
			);
			if ( $dirty ) {
				$changed[ $i ] = true;
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

	/**
	 * Run $filter (kses) over every string of every block's attributes and over the HTML between
	 * block comments, in place: only the attribute JSON of blocks whose strings change, and the
	 * HTML runs that change, are rewritten (with D5::encode_attrs, as apply() does). Core's
	 * content kses cannot be used on Divi markup: it escapes block comments whose attributes hold
	 * HTML.
	 *
	 * @param string   $content Content.
	 * @param callable $filter  fn(string): string.
	 * @return string
	 */
	public static function sanitize( string $content, callable $filter ): string {
		$edits = array();
		$prev  = 0;
		foreach ( BlockScanner::scan( $content ) as $b ) {
			self::sanitize_run( $content, $prev, $b['start'], $filter, $edits );
			$prev = $b['end'];
			if ( $b['closer'] ) {
				continue;
			}
			$attrs = self::decode( $b['raw'] );
			if ( null === $attrs ) {
				continue;
			}
			$dirty = false;
			$attrs = self::filter_strings( $attrs, $filter, $dirty );
			if ( $dirty ) {
				$edits[] = self::attrs_edit( $content, $b, $attrs );
			}
		}
		self::sanitize_run( $content, $prev, strlen( $content ), $filter, $edits );
		return self::splice( $content, $edits );
	}

	/**
	 * Whether sanitize() with this filter would damage the content's markup: a <script>, <style>
	 * or <svg> tag in any block attribute string or in the HTML between blocks, or a divi/code
	 * block with a string the filter changes (an iframe, a form...).
	 *
	 * @param string   $content Content.
	 * @param callable $filter  fn(string): string, the filter sanitize() would run.
	 * @return bool
	 */
	public static function has_unsafe_markup( string $content, callable $filter ): bool {
		$tag  = '/<\s*(?:script|style|svg)\b/i';
		$prev = 0;
		foreach ( BlockScanner::scan( $content ) as $b ) {
			if ( preg_match( $tag, substr( $content, $prev, $b['start'] - $prev ) ) ) {
				return true;
			}
			$prev = $b['end'];
			if ( $b['closer'] ) {
				continue;
			}
			$attrs = self::decode( $b['raw'] );
			if ( null === $attrs ) {
				continue;
			}
			$code      = 'divi/code' === $b['name'];
			$found     = false;
			$unchanged = false;
			self::filter_strings(
				$attrs,
				static function ( $s ) use ( $tag, $code, $filter, &$found ) {
					if ( ! $found && ( preg_match( $tag, $s ) || ( $code && (string) $filter( $s ) !== $s ) ) ) {
						$found = true;
					}
					return $s;
				},
				$unchanged
			);
			if ( $found ) {
				return true;
			}
		}
		return (bool) preg_match( $tag, substr( $content, $prev ) );
	}

	/**
	 * Filter the HTML between two block comments (surrounding whitespace kept).
	 *
	 * @param string   $content Content.
	 * @param int      $start   Start offset.
	 * @param int      $end     End offset.
	 * @param callable $filter  Filter.
	 * @param array    $edits   Edits (appended to).
	 * @return void
	 */
	private static function sanitize_run( $content, $start, $end, $filter, array &$edits ) {
		$raw  = substr( $content, $start, $end - $start );
		$lead = strspn( $raw, " \t\r\n" );
		$html = trim( $raw, " \t\r\n" );
		if ( '' === $html ) {
			return;
		}
		$clean = (string) $filter( $html );
		if ( $clean !== $html ) {
			$edits[] = array( $start + $lead, $start + $lead + strlen( $html ), $clean );
		}
	}

	/**
	 * Filter every string of an attr tree.
	 *
	 * @param mixed    $node   Node.
	 * @param callable $filter Filter.
	 * @param bool     $dirty  Set when something changed.
	 * @return mixed
	 */
	private static function filter_strings( $node, $filter, &$dirty ) {
		if ( is_string( $node ) ) {
			$clean = (string) $filter( $node );
			if ( $clean !== $node ) {
				$dirty = true;
			}
			return $clean;
		}
		if ( is_object( $node ) ) {
			foreach ( $node as $key => $value ) {
				$node->{$key} = self::filter_strings( $value, $filter, $dirty );
			}
		} elseif ( is_array( $node ) ) {
			foreach ( $node as $key => $value ) {
				$node[ $key ] = self::filter_strings( $value, $filter, $dirty );
			}
		}
		return $node;
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
			'ld_blocks'      => array(),
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
							self::json_ld( $st, $i, $idx, $where, $attrs );
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
	 * Prose segments of the JSON-LD scripts in a block's attr strings.
	 *
	 * @param array     $st    State.
	 * @param int       $i     Scan index.
	 * @param int       $idx   Block index.
	 * @param string    $where Context.
	 * @param \stdClass $attrs Block attrs.
	 * @return void
	 */
	private static function json_ld( array &$st, $i, $idx, $where, $attrs ) {
		$k     = 0;
		$found = array(); // [k, data].
		$dirty = false;
		self::filter_strings(
			$attrs,
			static function ( $s ) use ( &$k, &$found ) {
				if ( false === stripos( $s, 'ld+json' ) || ! preg_match_all( self::LD_RE, $s, $m ) ) {
					return $s;
				}
				foreach ( $m[2] as $body ) {
					++$k;
					$data = json_decode( $body );
					if ( null !== $data ) {
						$found[] = array( $k, $data );
					}
				}
				return $s;
			},
			$dirty
		);
		if ( ! $k ) {
			return;
		}
		$st['ld_blocks'][] = $i;
		foreach ( $found as list( $n, $data ) ) {
			self::ld_walk(
				$data,
				array(),
				'',
				static function ( $path, $key, $value, $type ) use ( &$st, $i, $idx, $where, $n ) {
					if ( ! in_array( $key, self::LD_PROSE_KEYS, true ) || ! self::has_text( $value ) || preg_match( '#^\s*https?://\S*\s*$#i', $value ) ) {
						return null;
					}
					$dot                = implode( '.', $path );
					$id                 = 'b' . $idx . '.ld' . $n . '.' . $dot;
					$st['ids'][ $id ]   = true;
					$st['segments'][]   = array(
						'id'      => $id,
						'kind'    => 'text',
						'context' => $where . ': JSON-LD ' . ( '' !== $type ? $type . ' ' : '' ) . $key,
						'text'    => $value,
						'source'  => $value,
						'parts'   => array(),
						'block'   => $i,
						'ld'      => array( $n, $dot ),
					);
					return null;
				}
			);
		}
	}

	/**
	 * Visit every string of a decoded JSON-LD value; $visit may return a replacement.
	 *
	 * @param mixed    $node  Node (objects are changed in place).
	 * @param array    $path  Path to $node.
	 * @param string   $type  Nearest @type.
	 * @param callable $visit fn(array $path, string $key, string $value, string $type): ?string.
	 * @param bool     $dirty Set when something changed.
	 * @return mixed
	 */
	private static function ld_walk( $node, array $path, $type, callable $visit, &$dirty = false ) {
		if ( is_object( $node ) ) {
			if ( isset( $node->{'@type'} ) ) {
				$type = is_array( $node->{'@type'} ) ? implode( ',', array_filter( $node->{'@type'}, 'is_string' ) ) : ( is_string( $node->{'@type'} ) ? $node->{'@type'} : $type );
			}
			foreach ( $node as $key => $value ) {
				$key = (string) $key;
				if ( is_string( $value ) ) {
					$new = $visit( array_merge( $path, array( $key ) ), $key, $value, $type );
					if ( is_string( $new ) && $new !== $value ) {
						$node->{$key} = $new;
						$dirty        = true;
					}
				} else {
					$node->{$key} = self::ld_walk( $value, array_merge( $path, array( $key ) ), $type, $visit, $dirty );
				}
			}
		} elseif ( is_array( $node ) ) {
			$key = (string) ( $path ? end( $path ) : '' );
			foreach ( $node as $n => $value ) {
				if ( is_string( $value ) ) {
					$new = $visit( array_merge( $path, array( (string) $n ) ), $key, $value, $type );
					if ( is_string( $new ) && $new !== $value ) {
						$node[ $n ] = $new;
						$dirty      = true;
					}
				} else {
					$node[ $n ] = self::ld_walk( $value, array_merge( $path, array( (string) $n ) ), $type, $visit, $dirty );
				}
			}
		}
		return $node;
	}

	/**
	 * Rewrite the JSON-LD scripts of one attr string: translations, inLanguage, links. Scripts
	 * that do not change keep their bytes.
	 *
	 * @param string        $s        Attr string.
	 * @param int           $k        Scripts seen so far in this block (advanced).
	 * @param array         $tr       k => dot path => translation.
	 * @param string|null   $language BCP 47 tag.
	 * @param callable|null $link_map Link map.
	 * @return string
	 */
	private static function rewrite_ld( $s, &$k, array $tr, $language, $link_map ) {
		if ( false === stripos( $s, 'ld+json' ) ) {
			return $s;
		}
		$out = preg_replace_callback(
			self::LD_RE,
			static function ( $m ) use ( &$k, $tr, $language, $link_map ) {
				++$k;
				$data = json_decode( $m[2] );
				if ( null === $data ) {
					return $m[0];
				}
				$mine  = $tr[ $k ] ?? array();
				$dirty = false;
				$data  = self::ld_walk(
					$data,
					array(),
					'',
					static function ( $path, $key, $value ) use ( $mine, $language, $link_map ) {
						$dot = implode( '.', $path );
						if ( isset( $mine[ $dot ] ) ) {
							return $mine[ $dot ];
						}
						if ( 'inLanguage' === $key && null !== $language && '' !== $language ) {
							return $language;
						}
						if ( null !== $link_map && in_array( $key, self::LD_LINK_KEYS, true ) ) {
							return self::map_ld_url( $value, $link_map );
						}
						return null;
					},
					$dirty
				);
				if ( ! $dirty ) {
					return $m[0];
				}
				$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
				if ( false !== strpos( $m[2], "\n" ) ) {
					$flags |= JSON_PRETTY_PRINT;
				}
				$json = json_encode( $data, $flags );
				if ( false === $json ) {
					return $m[0];
				}
				$lead  = substr( $m[2], 0, strspn( $m[2], " \t\r\n" ) );
				$trail = substr( $m[2], strlen( rtrim( $m[2], " \t\r\n" ) ) );
				return $m[1] . $lead . $json . $trail . $m[3];
			},
			$s
		);
		return null === $out ? $s : $out;
	}

	/**
	 * Map a JSON-LD link, keeping its #fragment (schema @id values often carry one).
	 *
	 * @param string   $url      URL.
	 * @param callable $link_map Link map.
	 * @return string|null
	 */
	private static function map_ld_url( $url, $link_map ) {
		$hash = strpos( $url, '#' );
		$base = false === $hash ? $url : substr( $url, 0, $hash );
		if ( '' === $base ) {
			return null;
		}
		$new = $link_map( $base );
		return is_string( $new ) ? $new . ( false === $hash ? '' : substr( $url, $hash ) ) : null;
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
	 * @param callable|null $filter   Value filter fn(string, string $kind), run after unmasking.
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
		if ( null !== $out && null !== $filter ) {
			$out = (string) $filter( $out, $seg['kind'] );
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
