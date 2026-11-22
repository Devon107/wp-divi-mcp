<?php
/**
 * Every tool's inputSchema must be valid JSON Schema on the wire.
 *
 * PHP encodes an empty array as [], but an empty subschema or an empty "properties"
 * map must be {}; strict MCP clients (the Inspector, SDKs) reject or drop the tool
 * otherwise. This walks the encoded tools/list of every tool, Divi tools included
 * (whether or not the Divi theme is active).
 *
 * @package WPDM
 */

use WPDM\Mcp\Context;
use WPDM\Mcp\Registry;
use WPDM\Mcp\Server;
use WPDM\Tools\Divi\Registrar as DiviRegistrar;
use WPDM\Tools\WordPress\Registrar as WordPressRegistrar;

class ToolSchemaWireTest extends WP_UnitTestCase {

	/** @var string[] Offending paths found by the walker. */
	private $bad = array();

	private function listed_tools(): array {
		$registry = WordPressRegistrar::add( new Registry() );
		foreach ( DiviRegistrar::tools() as $tool ) {
			$registry->add_tool( $tool );
		}
		$server = new Server( $registry, '' );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$res    = $server->handle(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/list',
				'params'  => array(),
			),
			new Context( $admin )
		);
		// Encode and decode as objects so {} and [] stay distinguishable.
		return json_decode( wp_json_encode( $res['result']['tools'] ) );
	}

	/**
	 * @param mixed  $node Schema node.
	 * @param string $path Path for messages.
	 */
	private function walk_schema( $node, string $path ): void {
		if ( is_bool( $node ) ) {
			return;
		}
		if ( ! is_object( $node ) ) {
			$this->bad[] = $path . ' is not a schema object';
			return;
		}
		foreach ( array( 'items', 'additionalProperties', 'not', 'contains', 'propertyNames' ) as $key ) {
			if ( isset( $node->$key ) ) {
				$this->walk_schema( $node->$key, "$path.$key" );
			}
		}
		foreach ( array( 'properties', 'patternProperties', '$defs', 'definitions' ) as $key ) {
			if ( ! isset( $node->$key ) ) {
				continue;
			}
			if ( ! is_object( $node->$key ) ) {
				$this->bad[] = "$path.$key is not an object";
				continue;
			}
			foreach ( $node->$key as $name => $sub ) {
				$this->walk_schema( $sub, "$path.$key.$name" );
			}
		}
		foreach ( array( 'anyOf', 'oneOf', 'allOf' ) as $key ) {
			if ( isset( $node->$key ) ) {
				if ( ! is_array( $node->$key ) ) {
					$this->bad[] = "$path.$key is not a list";
					continue;
				}
				foreach ( $node->$key as $i => $sub ) {
					$this->walk_schema( $sub, $path . "." . $key . "[" . $i . "]" );
				}
			}
		}
	}

	public function test_every_tool_input_schema_has_object_subschemas() {
		$tools = $this->listed_tools();
		$this->assertGreaterThanOrEqual( 40, count( $tools ) );
		foreach ( $tools as $tool ) {
			$this->assertIsObject( $tool->inputSchema, $tool->name );
			$this->assertSame( 'object', $tool->inputSchema->type, $tool->name );
			$this->assertTrue( isset( $tool->inputSchema->properties ), $tool->name . ' has no properties' );
			$this->walk_schema( $tool->inputSchema, $tool->name );
		}
		$this->assertSame( array(), $this->bad, implode( "\n", $this->bad ) );
	}

	public function test_wire_schema_keeps_lists_and_values() {
		$out = Server::wire_schema(
			array(
				'type'       => 'object',
				'required'   => array(),
				'properties' => array(
					'a' => array( 'type' => 'array', 'items' => array(), 'enum' => array(), 'default' => array() ),
					'b' => array(),
					'c' => array( 'properties' => array(), 'anyOf' => array( array(), array( 'type' => 'string' ) ) ),
				),
			)
		);
		$json = wp_json_encode( $out );
		$this->assertStringContainsString( '"required":[]', $json );
		$this->assertStringContainsString( '"enum":[]', $json );
		$this->assertStringContainsString( '"default":[]', $json );
		$this->assertStringContainsString( '"items":{}', $json );
		$this->assertStringContainsString( '"b":{}', $json );
		$this->assertStringContainsString( '"properties":{}', $json );
		$this->assertStringContainsString( '"anyOf":[{},{"type":"string"}]', $json );
	}
}
