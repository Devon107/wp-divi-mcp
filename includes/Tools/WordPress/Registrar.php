<?php
/**
 * Registers the WordPress tools.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Hooked on the wpdm_registry filter. Later tasks add their tools here.
 */
final class Registrar {

	/**
	 * Add every WordPress tool to the registry.
	 *
	 * @param Registry $registry Registry.
	 * @return Registry
	 */
	public static function add( Registry $registry ): Registry {
		$registry->add_tool( new SiteInfo() );
		$registry->add_tool( new ListPostTypes() );
		$registry->add_tool( new ListTaxonomies() );
		$registry->add_tool( new ListPosts() );
		$registry->add_tool( new GetPost() );
		$registry->add_tool( new CreatePost() );
		$registry->add_tool( new UpdatePost() );
		$registry->add_tool( new TrashPost() );
		$registry->add_tool( new RestorePost() );
		$registry->add_tool( new Search() );
		return $registry;
	}
}
