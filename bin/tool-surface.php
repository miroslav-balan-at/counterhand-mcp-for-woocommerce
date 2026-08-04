<?php
/**
 * Print the shipped tool surface as JSON: one row per group, with the label and
 * description the plugin itself uses.
 *
 * The marketing site restated these counts by hand and had no way to notice when
 * a group was added. This makes the plugin the source and the site a reader.
 *
 * Counts come from ShippedSurfaceTest::SHIPPED rather than from booting the
 * registry, because that constant is the deliberately-maintained contract: it
 * only changes when someone means to change the published surface, and it needs
 * no WordPress to read.
 *
 *   php bin/tool-surface.php > tool-surface.json
 */

declare( strict_types=1 );

const SURFACE_TEST = __DIR__ . '/../tests/Unit/Features/WooCommerceTools/Descriptors/ShippedSurfaceTest.php';
const GROUP_ENUM   = __DIR__ . '/../src/Shared/Tool/ToolGroup.php';

/**
 * @return array<string, int>
 */
function counterhand_group_counts(): array {
	$source = (string) file_get_contents( SURFACE_TEST );

	if ( 1 !== preg_match( '/private const SHIPPED = \[(.*?)\n\t\];/s', $source, $block ) ) {
		fwrite( STDERR, "tool-surface: could not find the SHIPPED constant\n" );
		exit( 1 );
	}

	preg_match_all( '/=>\s*\[\s*\'([a-z_]+)\',/', $block[1], $matches );

	$counts = [];

	foreach ( $matches[1] as $group ) {
		$counts[ $group ] = ( $counts[ $group ] ?? 0 ) + 1;
	}

	return $counts;
}

/**
 * Reads the match arms rather than loading the enum: label() and description()
 * call __(), which needs WordPress.
 *
 * @return array<string, array{label: string, description: string}>
 */
function counterhand_group_text(): array {
	$source = (string) file_get_contents( GROUP_ENUM );
	$text   = [];

	foreach ( [ 'label', 'description' ] as $method ) {
		if ( 1 !== preg_match( '/public function ' . $method . '\(\): string \{(.*?)\n\t\}/s', $source, $block ) ) {
			fwrite( STDERR, "tool-surface: could not read $method()\n" );
			exit( 1 );
		}

		preg_match_all( '/self::(\w+)\s*=>\s*__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $block[1], $arms, PREG_SET_ORDER );

		foreach ( $arms as $arm ) {
			$text[ $arm[1] ][ $method ] = str_replace( "\\'", "'", $arm[2] );
		}
	}

	return $text;
}

/**
 * The enum case name for a slug, so counts and text can be joined.
 *
 * @return array<string, string>
 */
function counterhand_case_slugs(): array {
	$source = (string) file_get_contents( GROUP_ENUM );

	preg_match_all( '/case\s+(\w+)\s*=\s*\'([a-z_]+)\'/', $source, $cases, PREG_SET_ORDER );

	$slugs = [];

	foreach ( $cases as $case ) {
		$slugs[ $case[2] ] = $case[1];
	}

	return $slugs;
}

$counts = counterhand_group_counts();
$text   = counterhand_group_text();
$slugs  = counterhand_case_slugs();

arsort( $counts );

$groups = [];

foreach ( $counts as $slug => $count ) {
	$case = $slugs[ $slug ] ?? null;

	if ( null === $case || ! isset( $text[ $case ]['label'] ) ) {
		fwrite( STDERR, "tool-surface: no label for group '$slug'\n" );
		exit( 1 );
	}

	$groups[] = [
		'slug'        => $slug,
		'label'       => $text[ $case ]['label'],
		'description' => $text[ $case ]['description'] ?? '',
		'tools'       => $count,
	];
}

echo (string) json_encode(
	[
		'groups' => $groups,
		'totals' => [
			'groups' => count( $groups ),
			'tools'  => array_sum( $counts ),
		],
	],
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
), "\n";
