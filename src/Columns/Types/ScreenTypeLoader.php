<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Registry;
use Corinthian\Columns\Types\Comments\CommentDateType;
use Corinthian\Columns\Types\Comments\CommentEditableFieldType;
use Corinthian\Columns\Types\Comments\CommentEditableTextType;
use Corinthian\Columns\Types\Comments\CommentFieldType;
use Corinthian\Columns\Types\Comments\CommentMetaType;
use Corinthian\Columns\Types\Comments\CommentPostTitleType;
use Corinthian\Columns\Types\Comments\CommentTextFilterType;
use Corinthian\Columns\Types\Comments\CommentTextType;
use Corinthian\Columns\Types\Media\MediaDetailFilterType;
use Corinthian\Columns\Types\Media\MediaDetailType;
use Corinthian\Columns\Types\Media\MediaEditableDetailType;
use Corinthian\Columns\Types\Media\MediaEditableFieldType;
use Corinthian\Columns\Types\Media\MediaExifType;
use Corinthian\Columns\Types\Media\MediaFieldType;
use Corinthian\Columns\Types\Media\MediaMeta;
use Corinthian\Columns\Types\Media\MediaMetricType;
use Corinthian\Columns\Types\Terms\TermDescriptionFilterType;
use Corinthian\Columns\Types\Terms\TermEditableFieldType;
use Corinthian\Columns\Types\Terms\TermFieldType;
use Corinthian\Columns\Types\Terms\TermMetaType;
use Corinthian\Columns\Types\Terms\TermTextType;
use Corinthian\Columns\Types\Users\UserAvatarType;
use Corinthian\Columns\Types\Users\UserCapabilitiesType;
use Corinthian\Columns\Types\Users\UserCommentCountType;
use Corinthian\Columns\Types\Users\UserDateType;
use Corinthian\Columns\Types\Users\UserEditableFieldType;
use Corinthian\Columns\Types\Users\UserFieldType;
use Corinthian\Columns\Types\Users\UserLastLoginType;
use Corinthian\Columns\Types\Users\UserMetaType;
use Corinthian\Columns\Types\Users\UserNameMetaType;
use Corinthian\Columns\Types\Users\UserPostCountType;
use Corinthian\Columns\Types\Users\UserProfileType;
use Corinthian\Columns\Types\Users\UserRolesType;

defined( 'ABSPATH' ) || exit;

/**
 * Column types for the users, media, comments and taxonomy term screens.
 */
final class ScreenTypeLoader {

	public static function register( Registry $registry ): void {
		$types = array(
			new UserDateType(),
			new UserRolesType(),
			new UserCapabilitiesType(),
			new UserAvatarType(),
			new UserPostCountType(),
			new UserCommentCountType(),
			new UserMetaType(),
			new UserLastLoginType(),
			new CommentDateType(),
			new CommentPostTitleType(),
			new CommentMetaType(),
			new TermMetaType(),
		);
		foreach ( array( 'id', 'username', 'display_name', 'email', 'website' ) as $field ) {
			$types[] = in_array( $field, array( 'display_name', 'email', 'website' ), true ) ? new UserEditableFieldType( $field ) : new UserFieldType( $field );
		}
		foreach ( array( 'nickname', 'description', 'rich_editing', 'admin_color', 'locale' ) as $field ) {
			$types[] = new UserProfileType( $field );
		}
		foreach ( array( 'first_name', 'last_name' ) as $field ) {
			$types[] = new UserNameMetaType( $field );
		}
		foreach ( array( 'id', 'author', 'author_email', 'author_ip', 'status', 'type' ) as $field ) {
			$types[] = in_array( $field, array( 'author', 'author_email', 'status' ), true ) ? new CommentEditableFieldType( $field ) : new CommentFieldType( $field );
		}
		foreach ( array( 'parent', 'word_count' ) as $field ) {
			$types[] = new CommentTextType( $field );
		}
		foreach ( array( 'author_url', 'excerpt', 'user_agent' ) as $field ) {
			$types[] = 'user_agent' === $field ? new CommentTextFilterType( $field ) : new CommentEditableTextType( $field );
		}
		foreach ( array( 'id', 'name', 'slug', 'post_count', 'parent' ) as $field ) {
			$types[] = in_array( $field, array( 'name', 'slug', 'parent' ), true ) ? new TermEditableFieldType( $field ) : new TermFieldType( $field );
		}
		foreach ( array( 'link', 'depth' ) as $field ) {
			$types[] = new TermTextType( $field );
		}
		$types[] = new TermDescriptionFilterType( 'description' );
		foreach ( array( 'file_name', 'mime_type', 'alt', 'attached_to', 'uploaded_by' ) as $field ) {
			$types[] = in_array( $field, array( 'alt', 'attached_to' ), true ) ? new MediaEditableFieldType( $field ) : new MediaFieldType( $field );
		}
		foreach ( array( 'file_size', 'width', 'height' ) as $field ) {
			$types[] = new MediaMetricType( $field );
		}
		foreach ( array( 'dimensions', 'url', 'sizes' ) as $field ) {
			$types[] = new MediaDetailType( $field );
		}
		foreach ( array( 'caption', 'description', 'featured_count' ) as $field ) {
			$types[] = 'featured_count' === $field ? new MediaDetailFilterType( $field ) : new MediaEditableDetailType( $field );
		}
		foreach ( array( 'camera', 'aperture', 'iso', 'shutter', 'focal_length', 'created' ) as $field ) {
			$types[] = new MediaExifType( $field );
		}

		foreach ( $types as $type ) {
			$registry->add( $type );
		}

		add_filter( 'wp_update_attachment_metadata', array( MediaMeta::class, 'forget' ), 10, 2 );
	}
}
