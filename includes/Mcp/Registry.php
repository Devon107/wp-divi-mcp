<?php
/**
 * Registry of tools, resources and prompts.
 *
 * @package WPDM
 */

namespace WPDM\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Holds everything the server can expose.
 */
class Registry {

	/**
	 * @var Tool[] Keyed by name.
	 */
	private $tools = array();

	/**
	 * @var Resource[] Keyed by URI.
	 */
	private $resources = array();

	/**
	 * @var array[] Each: uri_template, name, mime, reader, regex.
	 */
	private $templates = array();

	/**
	 * @var Prompt[] Keyed by name.
	 */
	private $prompts = array();

	/**
	 * Register a tool (replaces one with the same name).
	 *
	 * @param Tool $tool Tool.
	 * @return void
	 */
	public function add_tool( Tool $tool ): void {
		$this->tools[ $tool->name() ] = $tool;
	}

	/**
	 * Register a static resource.
	 *
	 * @param Resource $resource Resource.
	 * @return void
	 */
	public function add_resource( Resource $resource ): void {
		$this->resources[ $resource->uri ] = $resource;
	}

	/**
	 * Register a resource template with {placeholder} segments.
	 * The reader receives the extracted variables as an array.
	 *
	 * @param string   $uri_template URI template, e.g. wpdm://page/{id}.
	 * @param string   $name         Display name.
	 * @param string   $mime         MIME type.
	 * @param callable $reader       function( array $vars ): string.
	 * @return void
	 */
	public function add_resource_template( string $uri_template, string $name, string $mime, callable $reader ): void {
		$quoted = preg_quote( $uri_template, '#' );
		$regex  = preg_replace_callback(
			'/\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\\}/',
			static function ( $m ) {
				return '(?P<' . $m[1] . '>[^/]+)';
			},
			$quoted
		);

		$this->templates[ $uri_template ] = array(
			'uri_template' => $uri_template,
			'name'         => $name,
			'mime'         => $mime,
			'reader'       => $reader,
			'regex'        => '#^' . $regex . '$#',
		);
	}

	/**
	 * Register a prompt.
	 *
	 * @param Prompt $prompt Prompt.
	 * @return void
	 */
	public function add_prompt( Prompt $prompt ): void {
		$this->prompts[ $prompt->name ] = $prompt;
	}

	/**
	 * Tools visible to the context: read-only filter and capability filter applied.
	 *
	 * @param Context $ctx Context.
	 * @return Tool[]
	 */
	public function tools( Context $ctx ): array {
		$out = array();
		foreach ( $this->tools as $tool ) {
			if ( $ctx->read_only && ! $tool->read_only() ) {
				continue;
			}
			if ( ! user_can( $ctx->user_id, $tool->capability() ) ) {
				continue;
			}
			$out[] = $tool;
		}
		return $out;
	}

	/**
	 * Look a tool up by name, regardless of visibility.
	 *
	 * @param string $name Tool name.
	 * @return Tool|null
	 */
	public function tool( string $name ): ?Tool {
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * @return Resource[]
	 */
	public function resources(): array {
		return array_values( $this->resources );
	}

	/**
	 * @return array[] Template definitions (uri_template, name, mime).
	 */
	public function resource_templates(): array {
		$out = array();
		foreach ( $this->templates as $t ) {
			$out[] = array(
				'uri_template' => $t['uri_template'],
				'name'         => $t['name'],
				'mime'         => $t['mime'],
			);
		}
		return $out;
	}

	/**
	 * Read a resource by URI (static first, then templates).
	 *
	 * @param string $uri URI.
	 * @return array|null {uri, mimeType, text} or null when nothing matches.
	 */
	public function read_resource( string $uri ): ?array {
		if ( isset( $this->resources[ $uri ] ) ) {
			$res = $this->resources[ $uri ];
			return array(
				'uri'      => $uri,
				'mimeType' => $res->mime,
				'text'     => (string) call_user_func( $res->reader, array() ),
			);
		}

		foreach ( $this->templates as $t ) {
			if ( preg_match( $t['regex'], $uri, $m ) ) {
				$vars = array();
				foreach ( $m as $key => $value ) {
					if ( is_string( $key ) ) {
						$vars[ $key ] = $value;
					}
				}
				return array(
					'uri'      => $uri,
					'mimeType' => $t['mime'],
					'text'     => (string) call_user_func( $t['reader'], $vars ),
				);
			}
		}

		return null;
	}

	/**
	 * @return Prompt[]
	 */
	public function prompts(): array {
		return array_values( $this->prompts );
	}

	/**
	 * @param string $name Prompt name.
	 * @return Prompt|null
	 */
	public function prompt( string $name ): ?Prompt {
		return $this->prompts[ $name ] ?? null;
	}
}
