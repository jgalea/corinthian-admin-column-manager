<?php

namespace Corinthian\Sets;

defined( 'ABSPATH' ) || exit;

/**
 * Column sets defined in PHP with corinthian_register_set(). They're read-only
 * in the admin and live in version control instead of the database.
 */
final class CodeSets {

	/** Ids for code sets start here so they never collide with database rows. */
	public const ID_BASE = 1000000000;

	/** @var array<string,array<string,mixed>> */
	private static array $sets = array();

	/**
	 * @param array<string,mixed> $data screen, title, columns, settings; plus a stable "key".
	 */
	public static function add( array $data ): void {
		$key = sanitize_key( (string) ( $data['key'] ?? '' ) );
		if ( '' === $key || empty( $data['screen'] ) ) {
			_doing_it_wrong( 'corinthian_register_set', 'A code set needs a "key" and a "screen".', '0.1.0' );
			return;
		}
		self::$sets[ $key ] = $data;
	}

	public static function id_for( string $key ): int {
		return self::ID_BASE + ( abs( crc32( 'corinthian:' . $key ) ) % 1000000000 );
	}

	public static function is_code_id( int $id ): bool {
		return $id >= self::ID_BASE;
	}

	/**
	 * @param list<string> $types
	 */
	public static function uses_type( array $types ): bool {
		foreach ( self::all() as $set ) {
			foreach ( $set->columns as $config ) {
				if ( in_array( $config->type, $types, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return list<ColumnSet>
	 */
	public static function all(): array {
		$out = array();
		foreach ( self::$sets as $key => $data ) {
			$columns = array();
			foreach ( (array) ( $data['columns'] ?? array() ) as $column ) {
				$config = \Corinthian\Columns\Config::from_array( (array) $column );
				if ( '' !== $config->id && '' !== $config->type ) {
					$columns[] = $config;
				}
			}
			$out[] = new ColumnSet(
				id: self::id_for( $key ),
				screen: (string) $data['screen'],
				title: (string) ( $data['title'] ?? $key ),
				columns: $columns,
				settings: array(
					'readonly' => true,
					'code_key' => $key,
				) + (array) ( $data['settings'] ?? array() ),
				position: (int) ( $data['position'] ?? -100 ),
			);
		}
		return $out;
	}
}
