<?php
/**
 * Tool: update_menu_items.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Adds, edits, reorders and removes the items of one menu, and can assign the
 * menu to a theme location. Everything is validated before the first write.
 */
final class UpdateMenuItems extends Tool {

	const MAX_ITEMS = 200;

	/**
	 * URL schemes a custom menu link may use.
	 */
	const URL_SCHEMES = array( 'http', 'https', 'mailto', 'tel' );

	public function name(): string {
		return 'update_menu_items';
	}

	public function description(): string {
		return 'Change one navigation menu in a single call: create items (no id), edit or reorder items (with id; only the fields you pass change) and remove items (remove_ids, menu items only, they are not content; removal is permanent; the result lists the removed items\' definitions). An item links either to a custom url, or to a post/page/term via object_type (a post type or taxonomy name) plus object_id. parent must be the id of another item already in this menu (to nest under a new item, create the parent first, then make a second call). position orders items (ascending). set_location assigns the menu to a registered theme location. Everything is validated first: if anything is invalid, nothing is changed.';
	}

	/**
	 * @return array Schema of one entry of "items".
	 */
	private function item_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Existing item ID of this menu to update. Omit to create an item.',
				),
				'title'       => array(
					'type'        => 'string',
					'description' => 'Link label. Required when creating.',
				),
				'url'         => array(
					'type'        => 'string',
					'description' => 'Custom link (http, https, mailto, tel or a relative path). Only for custom items.',
				),
				'object_type' => array(
					'type'        => 'string',
					'description' => 'Post type (page, post, ...) or taxonomy (category, ...) of the linked object. Creating only.',
				),
				'object_id'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the linked post or term. Creating only.',
				),
				'parent'      => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Parent item ID in this menu, 0 for top level.',
				),
				'position'    => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Order among the menu\'s items (ascending). New items go last when omitted.',
				),
			),
			'required'             => array(),
			'additionalProperties' => false,
		);
	}

	private function location_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'location' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => 'Registered theme location (see list_menus).',
				),
				'menu_id'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Menu to assign. Defaults to menu_id of this call.',
				),
			),
			'required'             => array( 'location' ),
			'additionalProperties' => false,
		);
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'menu_id'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Menu ID (see list_menus).',
				),
				'items'        => array(
					'type'        => 'array',
					'description' => 'Items to create or update, see the tool description. At most ' . self::MAX_ITEMS . '.',
					'items'       => $this->item_schema(),
				),
				'remove_ids'   => array(
					'type'        => 'array',
					'description' => 'IDs of items of this menu to remove.',
					'items'       => array( 'type' => 'integer' ),
				),
				'set_location' => array_merge(
					$this->location_schema(),
					array( 'description' => 'Assign a menu to a theme location: {location, menu_id?}.' )
				),
			),
			'required'             => array( 'menu_id' ),
			'additionalProperties' => false,
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'edit_theme_options';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args = PostSupport::validate( $this->input_schema(), $args );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			throw new ToolError( 'You are not allowed to edit menus.', 'forbidden' );
		}

		$menu    = SiteSupport::menu( $args['menu_id'] );
		$menu_id = (int) $menu->term_id;
		$items   = $args['items'] ?? array();
		$remove  = $args['remove_ids'] ?? array();
		if ( ! $items && ! $remove && ! isset( $args['set_location'] ) ) {
			throw new ToolError( 'Nothing to do: pass items, remove_ids or set_location.', 'invalid_argument' );
		}
		if ( count( $items ) > self::MAX_ITEMS || count( $remove ) > self::MAX_ITEMS ) {
			throw new ToolError( 'At most ' . self::MAX_ITEMS . ' items per call.', 'invalid_argument' );
		}

		// ---- validate everything; no write happens in this block ----
		$members = array_flip( SiteSupport::menu_item_ids( $menu_id ) );
		$remove  = $this->remove_ids( $remove, $members );
		$plan    = $this->plan( $items, $menu_id, $members, $remove );
		$this->check_parents( $plan, $members, $remove );
		$location = isset( $args['set_location'] ) ? $this->location( $args['set_location'], $menu_id ) : null;

		// ---- write ----
		$created = array();
		$updated = array();
		$undo    = array();
		try {
			foreach ( $plan as $entry ) {
				$id = wp_update_nav_menu_item( $menu_id, $entry['id'], wp_slash( $entry['args'] ) );
				if ( is_wp_error( $id ) || ! $id ) {
					throw new ToolError( 'Could not save menu item "' . $entry['label'] . '"' . ( is_wp_error( $id ) ? ': ' . $id->get_error_message() : '.' ), 'update_failed' );
				}
				if ( $entry['id'] ) {
					$updated[]             = (int) $id;
					$undo[ (int) $id ] = $entry['old'];
				} else {
					$created[] = (int) $id;
				}
			}
		} catch ( ToolError $e ) {
			// Put back what was touched so a failure does not leave a half-edited menu.
			foreach ( $created as $id ) {
				wp_delete_post( $id, true );
			}
			foreach ( $undo as $id => $old ) {
				wp_update_nav_menu_item( $menu_id, $id, wp_slash( $old ) );
			}
			throw $e;
		}

		// Nav menu items have no trash: removal is permanent. Capture each
		// item's definition first (before any reparenting) so it can be recreated.
		$definitions = array();
		foreach ( $remove as $id ) {
			$definitions[ $id ] = self::definition( $id );
		}
		$removed = array();
		foreach ( $remove as $id ) {
			$this->reparent_children( $id, array_keys( $members ) );
			if ( ! wp_delete_post( $id, true ) ) {
				throw new ToolError(
					'Could not remove menu item ' . $id . '. Items removed so far: ' . ( $removed ? implode( ', ', array_column( $removed, 'id' ) ) : 'none' ) . '.',
					'remove_failed',
					array( 'removed' => $removed )
				);
			}
			$removed[] = $definitions[ $id ];
		}

		if ( null !== $location ) {
			set_theme_mod( 'nav_menu_locations', array_merge( (array) get_nav_menu_locations(), array( $location['location'] => $location['menu_id'] ) ) );
		}

		return array(
			'menu_id'  => $menu_id,
			'created'  => $created,
			'updated'  => $updated,
			'removed'  => $removed,
			'location' => null === $location ? null : $location['location'],
			'items'    => SiteSupport::menu_items( $menu_id ),
		);
	}

	/**
	 * What a menu item is, in the shape of get_menu's items.
	 *
	 * @param int $id Menu item id.
	 * @return array{id:int,title:string,url:string,type:string,object:string,object_id:int,parent:int,position:int}
	 */
	private static function definition( int $id ): array {
		$item = wp_setup_nav_menu_item( get_post( $id ) );
		return array(
			'id'        => $id,
			'title'     => (string) $item->title,
			'url'       => (string) $item->url,
			'type'      => (string) $item->type,
			'object'    => (string) $item->object,
			'object_id' => (int) $item->object_id,
			'parent'    => (int) $item->menu_item_parent,
			'position'  => (int) $item->menu_order,
		);
	}

	/**
	 * Deleting an item does not move its children (they would point at a missing
	 * parent and vanish from the menu), so hand them to the grandparent first.
	 *
	 * @param int   $id         Item about to be removed.
	 * @param int[] $member_ids Items of the menu.
	 * @return void
	 */
	private function reparent_children( int $id, array $member_ids ): void {
		$grandparent = (int) get_post_meta( $id, '_menu_item_menu_item_parent', true );
		foreach ( $member_ids as $child ) {
			if ( $child !== $id && (int) get_post_meta( $child, '_menu_item_menu_item_parent', true ) === $id ) {
				update_post_meta( $child, '_menu_item_menu_item_parent', $grandparent );
			}
		}
	}

	/**
	 * @param array $remove  Raw remove_ids.
	 * @param array $members Item ids of the menu (keys).
	 * @return int[]
	 * @throws ToolError invalid_argument.
	 */
	private function remove_ids( array $remove, array $members ): array {
		$out = array();
		foreach ( $remove as $id ) {
			if ( ! is_int( $id ) || ! isset( $members[ $id ] ) ) {
				throw new ToolError( 'remove_ids may only contain ids of items of this menu (got ' . ( is_scalar( $id ) ? $id : gettype( $id ) ) . ').', 'invalid_argument' );
			}
			$out[ $id ] = $id;
		}
		return array_values( $out );
	}

	/**
	 * Validate the items and compute the arguments to write.
	 *
	 * @param array $items   Raw items.
	 * @param int   $menu_id Menu id.
	 * @param array $members Item ids of the menu (keys).
	 * @param int[] $remove  Ids to remove.
	 * @return array[] Entries {id, label, args, old, parent (int|null)}.
	 * @throws ToolError invalid_argument.
	 */
	private function plan( array $items, int $menu_id, array $members, array $remove ): array {
		$max_position = 0;
		foreach ( array_keys( $members ) as $id ) {
			$max_position = max( $max_position, (int) get_post( $id )->menu_order );
		}
		foreach ( $items as $raw ) {
			if ( is_array( $raw ) && isset( $raw['position'] ) && is_int( $raw['position'] ) ) {
				$max_position = max( $max_position, $raw['position'] );
			}
		}

		$plan = array();
		$seen = array();
		foreach ( $items as $index => $raw ) {
			$label = 'items[' . $index . ']';
			if ( ! is_array( $raw ) ) {
				throw new ToolError( $label . ' must be an object.', 'invalid_argument' );
			}
			try {
				$item = PostSupport::validate( $this->item_schema(), $raw );
			} catch ( ToolError $e ) {
				throw new ToolError( $label . ': ' . $e->getMessage(), 'invalid_argument' );
			}

			if ( isset( $item['id'] ) ) {
				$id = $item['id'];
				if ( ! isset( $members[ $id ] ) ) {
					throw new ToolError( $label . ': item ' . $id . ' is not an item of menu ' . $menu_id . '.', 'invalid_argument' );
				}
				if ( isset( $seen[ $id ] ) ) {
					throw new ToolError( $label . ': item ' . $id . ' is listed more than once.', 'invalid_argument' );
				}
				if ( in_array( $id, $remove, true ) ) {
					throw new ToolError( $label . ': item ' . $id . ' is also in remove_ids.', 'invalid_argument' );
				}
				$seen[ $id ] = true;
				$plan[]      = $this->plan_update( $label, $item );
			} else {
				if ( ! isset( $item['position'] ) ) {
					$item['position'] = ++$max_position;
				}
				$plan[] = $this->plan_create( $label, $item );
			}
		}
		return $plan;
	}

	/**
	 * @param string $label Entry label for messages.
	 * @param array  $item  Validated item with an id.
	 * @return array
	 * @throws ToolError invalid_argument.
	 */
	private function plan_update( string $label, array $item ): array {
		if ( isset( $item['object_type'] ) || isset( $item['object_id'] ) ) {
			throw new ToolError( $label . ': the linked object of an existing item cannot be changed; remove the item and create a new one.', 'invalid_argument' );
		}
		$id   = $item['id'];
		$old  = $this->args_from_meta( $id );
		$args = $old;

		if ( isset( $item['title'] ) ) {
			$args['menu-item-title'] = trim( $item['title'] );
		}
		if ( isset( $item['url'] ) ) {
			if ( 'custom' !== $old['menu-item-type'] ) {
				throw new ToolError( $label . ': only custom links have a url.', 'invalid_argument' );
			}
			$args['menu-item-url'] = $this->clean_url( $item['url'], $label );
		}
		if ( 'custom' === $old['menu-item-type'] && '' === $args['menu-item-title'] ) {
			throw new ToolError( $label . ': a custom link needs a title.', 'invalid_argument' );
		}
		if ( isset( $item['parent'] ) ) {
			$args['menu-item-parent-id'] = $item['parent'];
		}
		if ( isset( $item['position'] ) ) {
			$args['menu-item-position'] = $item['position'];
		}
		return array(
			'id'     => $id,
			'label'  => $label,
			'args'   => $args,
			'old'    => $old,
			'parent' => isset( $item['parent'] ) ? $item['parent'] : null,
		);
	}

	/**
	 * @param string $label Entry label.
	 * @param array  $item  Validated item without an id.
	 * @return array
	 * @throws ToolError invalid_argument.
	 */
	private function plan_create( string $label, array $item ): array {
		$title = isset( $item['title'] ) ? trim( $item['title'] ) : '';
		if ( '' === $title ) {
			throw new ToolError( $label . ': "title" is required for a new item.', 'invalid_argument' );
		}
		$has_url    = isset( $item['url'] );
		$has_object = isset( $item['object_type'] ) || isset( $item['object_id'] );
		if ( $has_url === $has_object ) {
			throw new ToolError( $label . ': provide either "url" or "object_type" with "object_id".', 'invalid_argument' );
		}

		$args = array(
			'menu-item-title'     => $title,
			'menu-item-parent-id' => $item['parent'] ?? 0,
			'menu-item-position'  => $item['position'],
			'menu-item-status'    => 'publish',
		);
		if ( $has_url ) {
			$args['menu-item-type']   = 'custom';
			$args['menu-item-object'] = 'custom';
			$args['menu-item-url']    = $this->clean_url( $item['url'], $label );
		} else {
			if ( ! isset( $item['object_type'], $item['object_id'] ) ) {
				throw new ToolError( $label . ': "object_type" and "object_id" go together.', 'invalid_argument' );
			}
			$args = array_merge( $args, $this->object_args( $item['object_type'], $item['object_id'], $label ) );
		}
		return array(
			'id'     => 0,
			'label'  => $label,
			'args'   => $args,
			'old'    => array(),
			'parent' => $item['parent'] ?? null,
		);
	}

	/**
	 * Menu item arguments for a linked post or term.
	 *
	 * @param string $object_type Post type or taxonomy.
	 * @param int    $object_id   Post or term id.
	 * @param string $label       Entry label.
	 * @return array
	 * @throws ToolError invalid_argument.
	 */
	private function object_args( string $object_type, int $object_id, string $label ): array {
		if ( post_type_exists( $object_type ) ) {
			$type = get_post_type_object( $object_type );
			$post = get_post( $object_id );
			if ( ! $type || ! $type->show_in_nav_menus || ! $post instanceof \WP_Post || $post->post_type !== $object_type || 'trash' === $post->post_status ) {
				throw new ToolError( $label . ': there is no ' . $object_type . ' with id ' . $object_id . ' that can be linked.', 'invalid_argument' );
			}
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				throw new ToolError( $label . ': you are not allowed to link ' . $object_type . ' ' . $object_id . '.', 'forbidden' );
			}
			return array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => $object_type,
				'menu-item-object-id' => $object_id,
			);
		}
		if ( taxonomy_exists( $object_type ) ) {
			$taxonomy = get_taxonomy( $object_type );
			$term     = get_term( $object_id, $object_type );
			if ( ! $taxonomy || ! $taxonomy->show_in_nav_menus || ! $term instanceof \WP_Term ) {
				throw new ToolError( $label . ': there is no ' . $object_type . ' term with id ' . $object_id . ' that can be linked.', 'invalid_argument' );
			}
			return array(
				'menu-item-type'      => 'taxonomy',
				'menu-item-object'    => $object_type,
				'menu-item-object-id' => $object_id,
			);
		}
		throw new ToolError( $label . ': "' . $object_type . '" is neither a post type nor a taxonomy.', 'invalid_argument' );
	}

	/**
	 * @param string $url   Raw url.
	 * @param string $label Entry label.
	 * @return string
	 * @throws ToolError invalid_argument.
	 */
	private function clean_url( string $url, string $label ): string {
		$url = trim( preg_replace( '/[\x00-\x1f\x7f]/', '', $url ) );
		// esc_url_raw() strips a disallowed scheme and keeps the rest, so look at the scheme first.
		if ( preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $url, $match ) && ! in_array( strtolower( $match[1] ), self::URL_SCHEMES, true ) ) {
			throw new ToolError( $label . ': the url scheme "' . $match[1] . '" is not allowed (use ' . implode( ', ', self::URL_SCHEMES ) . ' or a relative path).', 'invalid_argument' );
		}
		$clean = esc_url_raw( $url, self::URL_SCHEMES );
		if ( '' === $clean ) {
			throw new ToolError( $label . ': the url is not valid.', 'invalid_argument' );
		}
		return $clean;
	}

	/**
	 * Parents must be existing items of this menu, not removed, and must not
	 * form a loop.
	 *
	 * @param array[] $plan    Plan.
	 * @param array   $members Item ids of the menu (keys).
	 * @param int[]   $remove  Ids to remove.
	 * @return void
	 * @throws ToolError invalid_argument.
	 */
	private function check_parents( array $plan, array $members, array $remove ): void {
		$parents = array();
		foreach ( array_keys( $members ) as $id ) {
			$parents[ $id ] = (int) get_post_meta( $id, '_menu_item_menu_item_parent', true );
		}

		foreach ( $plan as $entry ) {
			if ( null === $entry['parent'] || 0 === $entry['parent'] ) {
				continue;
			}
			$parent = $entry['parent'];
			if ( ! isset( $members[ $parent ] ) || in_array( $parent, $remove, true ) ) {
				throw new ToolError( $entry['label'] . ': parent ' . $parent . ' is not an item of this menu (or is being removed).', 'invalid_argument' );
			}
		}
		foreach ( $plan as $entry ) {
			if ( $entry['id'] && null !== $entry['parent'] ) {
				$parents[ $entry['id'] ] = $entry['parent'];
			}
		}
		foreach ( $plan as $entry ) {
			if ( ! $entry['id'] || ! $entry['parent'] ) {
				continue;
			}
			$cursor = $entry['parent'];
			$steps  = 0;
			while ( $cursor > 0 && $steps++ <= count( $parents ) ) {
				if ( $cursor === $entry['id'] ) {
					throw new ToolError( $entry['label'] . ': an item cannot be its own ancestor.', 'invalid_argument' );
				}
				$cursor = $parents[ $cursor ] ?? 0;
			}
		}
	}

	/**
	 * @param array $location Validated-by-schema? no: raw set_location.
	 * @param int   $menu_id  Default menu.
	 * @return array {location, menu_id}
	 * @throws ToolError invalid_argument.
	 */
	private function location( array $location, int $menu_id ): array {
		try {
			$location = PostSupport::validate( $this->location_schema(), $location );
		} catch ( ToolError $e ) {
			throw new ToolError( 'set_location: ' . $e->getMessage(), 'invalid_argument' );
		}
		$registered = get_registered_nav_menus();
		if ( ! isset( $registered[ $location['location'] ] ) ) {
			throw new ToolError( 'set_location: "' . $location['location'] . '" is not a registered location. Registered: ' . implode( ', ', array_keys( $registered ) ) . '.', 'invalid_argument' );
		}
		$target = $location['menu_id'] ?? $menu_id;
		if ( ! wp_get_nav_menu_object( $target ) ) {
			throw new ToolError( 'set_location: menu ' . $target . ' does not exist.', 'invalid_argument' );
		}
		return array(
			'location' => $location['location'],
			'menu_id'  => (int) $target,
		);
	}

	/**
	 * Arguments reproducing an existing item exactly, read from the raw meta
	 * (wp_update_nav_menu_item() does not merge with what is stored).
	 *
	 * @param int $id Item id.
	 * @return array
	 */
	private function args_from_meta( int $id ): array {
		$post    = get_post( $id );
		$classes = get_post_meta( $id, '_menu_item_classes', true );
		$type    = (string) get_post_meta( $id, '_menu_item_type', true );
		return array(
			'menu-item-object-id'   => (int) get_post_meta( $id, '_menu_item_object_id', true ),
			'menu-item-object'      => (string) get_post_meta( $id, '_menu_item_object', true ),
			'menu-item-parent-id'   => (int) get_post_meta( $id, '_menu_item_menu_item_parent', true ),
			'menu-item-position'    => (int) $post->menu_order,
			'menu-item-type'        => $type,
			'menu-item-title'       => $post->post_title,
			'menu-item-url'         => 'custom' === $type ? (string) get_post_meta( $id, '_menu_item_url', true ) : '',
			'menu-item-description' => $post->post_content,
			'menu-item-attr-title'  => $post->post_excerpt,
			'menu-item-target'      => (string) get_post_meta( $id, '_menu_item_target', true ),
			'menu-item-classes'     => implode( ' ', is_array( $classes ) ? $classes : array() ),
			'menu-item-xfn'         => (string) get_post_meta( $id, '_menu_item_xfn', true ),
			'menu-item-status'      => 'draft' === $post->post_status ? 'draft' : 'publish',
		);
	}
}
