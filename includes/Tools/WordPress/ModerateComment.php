<?php
/**
 * Tool: moderate_comment.
 *
 * @package WPDM
 */

namespace WPDM\Tools\WordPress;

use WPDM\Mcp\Context;
use WPDM\Mcp\Tool;
use WPDM\Mcp\ToolError;

defined( 'ABSPATH' ) || exit;

/**
 * Approves, holds, spams or trashes a comment, or replies to it.
 */
final class ModerateComment extends Tool {

	const ACTIONS = array( 'approve', 'unapprove', 'spam', 'trash', 'reply' );

	public function name(): string {
		return 'moderate_comment';
	}

	public function description(): string {
		return 'Moderate one comment: approve, unapprove (back to pending), spam, trash (never permanent: it can be restored by approving) or reply (posts an approved reply as the current user; needs content). Only comments on content the current user may edit.';
	}

	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Comment ID.',
				),
				'action'  => array(
					'type'        => 'string',
					'enum'        => self::ACTIONS,
					'description' => 'approve, unapprove, spam, trash or reply.',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Reply text (only with action reply).',
				),
			),
			'required'             => array( 'id', 'action' ),
			'additionalProperties' => false,
		);
	}

	public function destructive(): bool {
		return true;
	}

	public function capability(): string {
		return 'moderate_comments';
	}

	public function execute( array $args, Context $ctx ): array {
		PostSupport::bind_user( $ctx );
		$args    = PostSupport::validate( $this->input_schema(), $args );
		$comment = get_comment( $args['id'] );
		if ( ! $comment instanceof \WP_Comment ) {
			throw new ToolError( 'Comment ' . $args['id'] . ' not found.', 'not_found' );
		}
		if ( ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			throw new ToolError( 'You are not allowed to moderate comment ' . $comment->comment_ID . '.', 'forbidden' );
		}

		$action  = $args['action'];
		$content = $args['content'] ?? null;
		if ( 'reply' === $action ) {
			if ( null === $content || '' === trim( $content ) ) {
				throw new ToolError( 'A reply needs non-empty "content".', 'invalid_argument' );
			}
			return $this->reply( $comment, $content );
		}
		if ( null !== $content ) {
			throw new ToolError( '"content" is only used with action reply.', 'invalid_argument' );
		}

		$wanted = array(
			'approve'   => 'approve',
			'unapprove' => 'hold',
			'spam'      => 'spam',
			'trash'     => 'trash',
		)[ $action ];

		if ( SiteSupport::comment_status( $comment ) !== $wanted ) {
			if ( 'trash' === $wanted ) {
				// With the trash disabled, wp_trash_comment() deletes for good.
				if ( ! EMPTY_TRASH_DAYS ) {
					throw new ToolError( 'The WordPress trash is disabled on this site (EMPTY_TRASH_DAYS is 0), so trashing would delete permanently. Refusing.', 'trash_disabled' );
				}
				$done = wp_trash_comment( $comment );
			} else {
				$done = wp_set_comment_status( $comment, $wanted );
			}
			if ( ! $done ) {
				throw new ToolError( 'WordPress could not change the comment status.', 'update_failed' );
			}
		}

		$fresh = get_comment( $comment->comment_ID );
		return array(
			'id'     => (int) $comment->comment_ID,
			'action' => $action,
			'status' => $fresh instanceof \WP_Comment ? SiteSupport::comment_status( $fresh ) : $wanted,
		);
	}

	/**
	 * @param \WP_Comment $parent  Comment replied to.
	 * @param string      $content Reply text.
	 * @return array
	 * @throws ToolError invalid_argument or create_failed.
	 */
	private function reply( \WP_Comment $parent, string $content ): array {
		$status = SiteSupport::comment_status( $parent );
		if ( in_array( $status, array( 'spam', 'trash' ), true ) ) {
			throw new ToolError( 'Cannot reply to a ' . $status . ' comment.', 'invalid_argument' );
		}
		if ( ! get_post( (int) $parent->comment_post_ID ) ) {
			throw new ToolError( 'The post of this comment no longer exists.', 'invalid_argument' );
		}

		$user = wp_get_current_user();
		$id   = wp_insert_comment(
			wp_slash(
				array(
					'comment_post_ID'      => (int) $parent->comment_post_ID,
					'comment_parent'       => (int) $parent->comment_ID,
					'user_id'              => (int) $user->ID,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_author_url'   => $user->user_url,
					'comment_content'      => wp_kses_post( $content ),
					'comment_type'         => 'comment',
					'comment_approved'     => 1,
				)
			)
		);
		if ( ! $id ) {
			throw new ToolError( 'Could not post the reply.', 'create_failed' );
		}
		return array(
			'id'       => (int) $parent->comment_ID,
			'action'   => 'reply',
			'status'   => SiteSupport::comment_status( $parent ),
			'reply_id' => (int) $id,
		);
	}
}
