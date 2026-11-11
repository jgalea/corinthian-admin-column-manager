<?php

namespace Corinthian\Columns\Types;

use Corinthian\Columns\Registry;

defined( 'ABSPATH' ) || exit;

final class TypeLoader {

	public static function register( Registry $registry ): void {
		$registry->add( new NativeType() );
		$registry->add( new PostMetaType() );

		foreach ( CoreFieldTypes::all() as $type ) {
			$registry->add( $type );
		}
		$registry->add( new PublishedDateType() );
		$registry->add( new DateType( 'modified' ) );
		$registry->add( new AuthorType() );
		$registry->add( new TaxonomyType() );
		$registry->add( new PostFormatType() );
		$registry->add( new StickyType() );
		$registry->add( new ParentType() );
		$registry->add( new WordCountType() );
		$registry->add( new ReadingTimeType() );
		$registry->add( new ExcerptType() );
		$registry->add( new PermalinkType() );
		$registry->add( new LinksCountType() );
		$registry->add( new FeaturedImageType() );
		$registry->add( new HasFeaturedImageType() );

		WordCountMeta::register();
	}
}
