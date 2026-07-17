<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class LocalFontStoreTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => parse_url( $url, $component )
		);
	}

	public function tearDown(): void {
		// The health cache is a static, per-request cache. Reset it between tests
		// so unrelated test cases (which may reuse family names) don't leak state.
		$reflection = new \ReflectionProperty( LocalFontStore::class, 'health_cache' );
		$reflection->setAccessible( true );
		$reflection->setValue( null, [] );

		parent::tearDown();
	}

	/*
	 * extract_css_urls()
	 */

	public function test_extract_css_urls_handles_relative_refs_without_quotes(): void {
		$css = "@font-face{src:url(font-400.woff2) format('woff2');}";

		$this->assertSame( [ 'font-400.woff2' ], LocalFontStore::extract_css_urls( $css ) );
	}

	public function test_extract_css_urls_handles_relative_refs_with_single_and_double_quotes(): void {
		$css = "@font-face{src:url('font-400.woff2') format('woff2');}"
			. "@font-face{src:url(\"font-700.woff2\") format('woff2');}";

		$this->assertSame(
			[ 'font-400.woff2', 'font-700.woff2' ],
			LocalFontStore::extract_css_urls( $css )
		);
	}

	public function test_extract_css_urls_handles_subdirectory_ref(): void {
		$css = "@font-face{src:url('31-03-2020/quentin-webfont.woff2') format('woff2');}";

		$this->assertSame(
			[ '31-03-2020/quentin-webfont.woff2' ],
			LocalFontStore::extract_css_urls( $css )
		);
	}

	public function test_extract_css_urls_handles_absolute_ref(): void {
		$css = "@font-face{src:url('https://pxgcdn.com/fonts/quentin/quentin-webfont.woff2') format('woff2');}";

		$this->assertSame(
			[ 'https://pxgcdn.com/fonts/quentin/quentin-webfont.woff2' ],
			LocalFontStore::extract_css_urls( $css )
		);
	}

	public function test_extract_css_urls_skips_data_uris(): void {
		$css = "@font-face{src:url(data:font/woff2;base64,AAAA) format('woff2'), url('font-400.woff2') format('woff2');}";

		$this->assertSame( [ 'font-400.woff2' ], LocalFontStore::extract_css_urls( $css ) );
	}

	public function test_extract_css_urls_dedupes_refs(): void {
		$css = "@font-face{src:url('font-400.woff2') format('woff2');}"
			. "@font-face{src:url('font-400.woff2') format('woff2');}";

		$this->assertSame( [ 'font-400.woff2' ], LocalFontStore::extract_css_urls( $css ) );
	}

	/*
	 * derive_slug()
	 */

	public function test_derive_slug_from_cloud_fonts_v2_url(): void {
		$url = 'https://cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/uncut-sans/stylesheet.css';

		$this->assertSame( 'uncut-sans', LocalFontStore::derive_slug( $url ) );
	}

	public function test_derive_slug_from_pxgcdn_url(): void {
		$url = 'https://pxgcdn.com/fonts/quentin/stylesheet.css';

		$this->assertSame( 'quentin', LocalFontStore::derive_slug( $url ) );
	}

	public function test_derive_slug_from_protocol_relative_url(): void {
		$url = '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css';

		$this->assertSame( 'trueno', LocalFontStore::derive_slug( $url ) );
	}

	public function test_derive_slug_falls_back_to_md5_for_garbage_input(): void {
		$garbage = 'not-a-url';

		$this->assertSame( md5( $garbage ), LocalFontStore::derive_slug( $garbage ) );
	}

	/*
	 * mirror_font()
	 */

	public function test_mirror_font_happy_path_downloads_stylesheet_and_font_files_and_saves_manifest(): void {
		$family      = 'Uncut Sans Happy';
		$stylesheet_url = '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/uncut-sans-happy/stylesheet.css';
		$css         = "@font-face{font-family:'Uncut Sans Happy';src:url('uncut-sans-400.woff2') format('woff2');}"
			. "@font-face{font-family:'Uncut Sans Happy';src:url(\"31-03-2020/uncut-sans-700.woff2\") format('woff2');}";

		$font_config = [
			'family'         => $family,
			'family_display' => 'Uncut Sans Happy',
			'src'            => $stylesheet_url,
			'variants'       => [ '400', '700' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$requested_urls = [];
		$store          = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( &$requested_urls, $css ) {
			$requested_urls[] = $url;

			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}
			if ( str_ends_with( $url, '/uncut-sans-400.woff2' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => 'FONT-400-BYTES' ];
			}
			if ( str_ends_with( $url, '/31-03-2020/uncut-sans-700.woff2' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => 'FONT-700-BYTES' ];
			}

			// LICENSE.txt / SOURCE.txt best-effort requests.
			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );

		$written = [];
		$store->method( 'write_file' )->willReturnCallback( static function( string $path, string $contents ) use ( &$written ) {
			$written[ $path ] = $contents;

			return true;
		} );

		$result = $store->mirror_font( $font_config, '2020-03-31 10:00:00' );

		$this->assertTrue( $result );
		$this->assertCount( 5, $requested_urls, 'Expected the stylesheet, 2 font files, and the 2 best-effort sibling files to be requested.' );
		$this->assertCount( 4, $written, 'Expected 2 font files + stylesheet.css + the guard index.php to be written.' );

		$this->assertNotNull( $updated_manifest );
		$this->assertArrayHasKey( $family, $updated_manifest );
		$entry = $updated_manifest[ $family ];

		$this->assertSame( 'ok', $entry['status'] );
		$this->assertSame( 'uncut-sans-happy', $entry['slug'] );
		$this->assertSame( 'style-manager/fonts/uncut-sans-happy/stylesheet.css', $entry['stylesheet_path'] );
		$this->assertContains( 'style-manager/fonts/uncut-sans-happy/uncut-sans-400.woff2', $entry['files'] );
		$this->assertContains( 'style-manager/fonts/uncut-sans-happy/31-03-2020/uncut-sans-700.woff2', $entry['files'] );
		$this->assertSame( 0, $entry['attempts'] );
		$this->assertSame( '', $entry['last_error'] );
		$this->assertSame( '2020-03-31 10:00:00', $entry['last_modified'] );
		$this->assertSame( 'https://cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/uncut-sans-happy/stylesheet.css', $entry['source_stylesheet'] );

		// A guard index.php must be written into the slug dir on every
		// successful mirror (standard WP uploads-dir convention), but it must
		// not be counted among the manifest's tracked font files.
		$index_php_path = $this->find_written_path( $written, 'style-manager/fonts/uncut-sans-happy/index.php' );
		$this->assertNotNull( $index_php_path, 'Expected a guard index.php to be written into the slug dir.' );
		$this->assertStringContainsString( '<?php', $written[ $index_php_path ] );
		foreach ( $entry['files'] as $tracked_file ) {
			$this->assertStringNotContainsString( 'index.php', $tracked_file );
		}
	}

	public function test_mirror_font_rejects_a_disallowed_file_extension_in_a_relative_ref(): void {
		$family      = 'Evil Ref Font';
		$font_config = [
			'family'         => $family,
			'family_display' => 'Evil Ref Font',
			'src'            => 'https://pxgcdn.com/fonts/evil-ref/stylesheet.css',
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		// A malicious stylesheet trying to smuggle an executable file into the
		// uploads dir via a font `url()` reference.
		$css = "@font-face{src:url('backdoor.php') format('woff2');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}

			// Should never be reached, but return 200 so the only way the test
			// can fail closed is via the extension allowlist check.
			return [ 'response' => [ 'code' => 200 ], 'body' => "<?php echo 'pwned'; " ];
		} );
		// Nothing should ever be written -- the whole mirror must fail.
		$store->expects( $this->never() )->method( 'write_file' );

		$result = $store->mirror_font( $font_config );

		$this->assertFalse( $result );
		$entry = $updated_manifest[ $family ];
		$this->assertSame( 'failed', $entry['status'] );
	}

	public function test_mirror_font_strips_query_and_fragment_from_a_ref_and_dedupes_downloads(): void {
		$family      = 'Iefix Font';
		$font_config = [
			'family'         => $family,
			'family_display' => 'Iefix Font',
			'src'            => 'https://pxgcdn.com/fonts/iefix-font/stylesheet.css',
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		// Two refs to the same underlying file: one with the classic
		// `?#iefix` IE hack, one plain. Both must resolve to the on-disk
		// filename `font.eot` (query/fragment are not part of the filesystem
		// path), and the file must only be downloaded once.
		$css = "@font-face{src:url('font.eot?#iefix') format('embedded-opentype'),url('font.eot') format('opentype');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$requested_urls = [];
		$store          = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( &$requested_urls, $css ) {
			$requested_urls[] = $url;

			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}
			if ( str_contains( $url, 'font.eot' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => 'EOT-BYTES' ];
			}

			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );

		$written = [];
		$store->method( 'write_file' )->willReturnCallback( static function( string $path, string $contents ) use ( &$written ) {
			$written[ $path ] = $contents;

			return true;
		} );

		$result = $store->mirror_font( $font_config );

		$this->assertTrue( $result );

		$font_file_requests = array_filter( $requested_urls, static fn( string $u ): bool => str_contains( $u, 'font.eot' ) );
		$this->assertCount( 1, $font_file_requests, 'The font.eot file must only be downloaded once, despite 2 distinct refs.' );

		$entry = $updated_manifest[ $family ];
		$this->assertSame( [ 'style-manager/fonts/iefix-font/font.eot' ], $entry['files'] );
		$this->assertNotNull(
			$this->find_written_path( $written, 'style-manager/fonts/iefix-font/font.eot' ),
			'Expected font.eot to be written under its clean (query/fragment-stripped) filename.'
		);
		foreach ( array_keys( $written ) as $path ) {
			$this->assertStringNotContainsString( '?', $path, 'No written path should retain the query string.' );
			$this->assertStringNotContainsString( '#', $path, 'No written path should retain the fragment.' );
		}
	}

	public function test_mirror_font_failure_marks_manifest_failed_when_no_prior_ok_entry(): void {
		$family      = 'Broken Font';
		$font_config = [
			'family'         => $family,
			'family_display' => 'Broken Font',
			'src'            => 'https://pxgcdn.com/fonts/broken-font/stylesheet.css',
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		$css = "@font-face{src:url('broken-400.woff2') format('woff2');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}

			// The font file 404s.
			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );
		$store->expects( $this->never() )->method( 'write_file' );

		$result = $store->mirror_font( $font_config );

		$this->assertFalse( $result );
		$this->assertNotNull( $updated_manifest );
		$entry = $updated_manifest[ $family ];
		$this->assertSame( 'failed', $entry['status'] );
		$this->assertSame( 1, $entry['attempts'] );
		$this->assertNotSame( '', $entry['last_error'] );
	}

	public function test_mirror_font_failure_preserves_prior_ok_entry_and_bumps_attempts(): void {
		$family      = 'Previously Ok Font';
		$font_config = [
			'family'         => $family,
			'family_display' => 'Previously Ok Font',
			'src'            => 'https://pxgcdn.com/fonts/previously-ok/stylesheet.css',
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		$css = "@font-face{src:url('previously-ok-400.woff2') format('woff2');}";

		$prior_entry = [
			'slug'              => 'previously-ok',
			'source_stylesheet' => 'https://pxgcdn.com/fonts/previously-ok/stylesheet.css',
			'stylesheet_path'   => 'style-manager/fonts/previously-ok/stylesheet.css',
			'files'             => [ 'style-manager/fonts/previously-ok/previously-ok-400.woff2' ],
			'variants'          => [ '400' ],
			'category'          => 'sans-serif',
			'fallback_stack'    => 'sans-serif',
			'family_display'    => 'Previously Ok Font',
			'last_modified'     => '2020-01-01 00:00:00',
			'mirrored_at'       => 1_600_000_000,
			'status'            => 'ok',
			'attempts'          => 0,
			'last_error'        => '',
		];

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [ $family => $prior_entry ] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}

			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );
		$store->expects( $this->never() )->method( 'write_file' );

		$result = $store->mirror_font( $font_config );

		$this->assertFalse( $result );
		$entry = $updated_manifest[ $family ];
		$this->assertSame( 'ok', $entry['status'], 'A failed refresh must not break a previously working mirror.' );
		$this->assertSame( 1, $entry['attempts'] );
		$this->assertNotSame( '', $entry['last_error'] );
		$this->assertSame( $prior_entry['stylesheet_path'], $entry['stylesheet_path'] );
		$this->assertSame( $prior_entry['files'], $entry['files'] );
		$this->assertSame( $prior_entry['mirrored_at'], $entry['mirrored_at'] );
	}

	public function test_mirror_font_disambiguates_slug_when_another_family_derives_the_same_slug_from_a_different_url(): void {
		$family_a = 'Foo Family A';
		$url_a    = 'https://pxgcdn.com/fonts/foo/stylesheet.css';
		$prior_entry_a = [
			'slug'              => 'foo',
			'source_stylesheet' => $url_a,
			'stylesheet_path'   => 'style-manager/fonts/foo/stylesheet.css',
			'files'             => [ 'style-manager/fonts/foo/foo-400.woff2' ],
			'variants'          => [ '400' ],
			'category'          => 'sans-serif',
			'fallback_stack'    => 'sans-serif',
			'family_display'    => $family_a,
			'last_modified'     => '2020-01-01 00:00:00',
			'mirrored_at'       => 1_600_000_000,
			'status'            => 'ok',
			'attempts'          => 0,
			'last_error'        => '',
		];

		$family_b = 'Foo Family B';
		$url_b    = '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/foo/stylesheet.css';
		$normalized_url_b = 'https://cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/foo/stylesheet.css';
		$expected_slug_b  = 'foo-' . substr( md5( $normalized_url_b ), 0, 8 );

		$font_config_b = [
			'family'         => $family_b,
			'family_display' => $family_b,
			'src'            => $url_b,
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		$css_b = "@font-face{font-family:'Foo Family B';src:url('foo-b-400.woff2') format('woff2');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [ $family_a => $prior_entry_a ] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css_b ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css_b ];
			}
			if ( str_ends_with( $url, '/foo-b-400.woff2' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => 'FONT-B-400-BYTES' ];
			}

			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );

		$written = [];
		$store->method( 'write_file' )->willReturnCallback( static function( string $path, string $contents ) use ( &$written ) {
			$written[ $path ] = $contents;

			return true;
		} );

		$result = $store->mirror_font( $font_config_b );

		$this->assertTrue( $result );
		$this->assertNotNull( $updated_manifest );

		// Family A's entry must be left completely untouched.
		$this->assertSame( $prior_entry_a, $updated_manifest[ $family_a ] );

		// Family B must be disambiguated onto its own slug/directory.
		$entry_b = $updated_manifest[ $family_b ];
		$this->assertSame( $expected_slug_b, $entry_b['slug'] );
		$this->assertSame( "style-manager/fonts/{$expected_slug_b}/stylesheet.css", $entry_b['stylesheet_path'] );
		$this->assertContains( "style-manager/fonts/{$expected_slug_b}/foo-b-400.woff2", $entry_b['files'] );

		foreach ( array_keys( $written ) as $path ) {
			$this->assertStringContainsString( "/{$expected_slug_b}/", $path, 'No file should be written into family A\'s slug directory.' );
		}
	}

	public function test_mirror_font_keeps_plain_slug_when_remirroring_the_same_family(): void {
		$family = 'Foo Family Same';
		$url    = 'https://pxgcdn.com/fonts/foo/stylesheet.css';

		$prior_entry = [
			'slug'              => 'foo',
			'source_stylesheet' => $url,
			'stylesheet_path'   => 'style-manager/fonts/foo/stylesheet.css',
			'files'             => [ 'style-manager/fonts/foo/foo-400.woff2' ],
			'variants'          => [ '400' ],
			'category'          => 'sans-serif',
			'fallback_stack'    => 'sans-serif',
			'family_display'    => $family,
			'last_modified'     => '2020-01-01 00:00:00',
			'mirrored_at'       => 1_600_000_000,
			'status'            => 'ok',
			'attempts'          => 0,
			'last_error'        => '',
		];

		$font_config = [
			'family'         => $family,
			'family_display' => $family,
			'src'            => $url,
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		$css = "@font-face{font-family:'Foo Family Same';src:url('foo-400.woff2') format('woff2');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [ $family => $prior_entry ] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}
			if ( str_ends_with( $url, '/foo-400.woff2' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => 'FONT-400-BYTES' ];
			}

			return [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		} );
		$store->method( 'write_file' )->willReturn( true );

		$result = $store->mirror_font( $font_config );

		$this->assertTrue( $result );
		$entry = $updated_manifest[ $family ];
		$this->assertSame( 'foo', $entry['slug'] );
		$this->assertSame( 'style-manager/fonts/foo/stylesheet.css', $entry['stylesheet_path'] );
	}

	public function test_mirror_font_rejects_path_traversal_in_relative_ref(): void {
		$family      = 'Traversal Font';
		$font_config = [
			'family'         => $family,
			'family_display' => 'Traversal Font',
			'src'            => 'https://pxgcdn.com/fonts/traversal-font/stylesheet.css',
			'variants'       => [ '400' ],
			'category'       => 'sans-serif',
			'fallback_stack' => 'sans-serif',
		];
		$css = "@font-face{src:url('../evil.woff') format('woff');}";

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$updated_manifest = null;
		Functions\when( 'update_option' )->alias( static function( string $option, $value ) use ( &$updated_manifest ) {
			$updated_manifest = $value;

			return true;
		} );

		$store = $this->partial_mock( [ 'remote_get', 'write_file' ] );
		$store->method( 'remote_get' )->willReturnCallback( static function( string $url ) use ( $css ) {
			if ( str_ends_with( $url, '/stylesheet.css' ) ) {
				return [ 'response' => [ 'code' => 200 ], 'body' => $css ];
			}

			// Should never be reached for the traversal ref, but return 200 so the
			// only way the test can fail closed is via the sanitization check.
			return [ 'response' => [ 'code' => 200 ], 'body' => 'SHOULD-NOT-BE-DOWNLOADED' ];
		} );
		// No file should ever be written, inside or outside the slug dir.
		$store->expects( $this->never() )->method( 'write_file' );

		$result = $store->mirror_font( $font_config );

		$this->assertFalse( $result );
		$entry = $updated_manifest[ $family ];
		$this->assertSame( 'failed', $entry['status'] );
	}

	/*
	 * needs_refresh()
	 */

	public function test_needs_refresh_true_when_healthy_and_last_modified_differs(): void {
		$family = 'Refresh Needed Font';
		$this->mock_healthy_entry( $family, '2020-01-01 00:00:00' );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertTrue( $store->needs_refresh( $family, '2020-06-01 00:00:00' ) );
	}

	public function test_needs_refresh_false_when_last_modified_matches(): void {
		$family = 'Refresh Not Needed Font';
		$this->mock_healthy_entry( $family, '2020-01-01 00:00:00' );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertFalse( $store->needs_refresh( $family, '2020-01-01 00:00:00' ) );
	}

	public function test_needs_refresh_false_when_entry_not_healthy(): void {
		$family = 'Unhealthy Refresh Font';

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [
			$family => [
				'stylesheet_path' => 'style-manager/fonts/unhealthy-refresh-font/stylesheet.css',
				'status'          => 'failed',
				'last_modified'   => '2020-01-01 00:00:00',
			],
		] );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertFalse( $store->needs_refresh( $family, '2020-06-01 00:00:00' ) );
	}

	/*
	 * get_local_src() / is_healthy()
	 */

	public function test_get_local_src_returns_null_when_entry_missing(): void {
		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [] );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertNull( $store->get_local_src( 'Nonexistent Font' ) );
	}

	public function test_get_local_src_returns_url_when_entry_ok_and_file_exists(): void {
		$family = 'On Disk Font';
		$this->mock_healthy_entry( $family, '2020-01-01 00:00:00' );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertSame(
			'https://example.com/wp-content/uploads/style-manager/fonts/on-disk-font/stylesheet.css',
			$store->get_local_src( $family )
		);
	}

	public function test_is_healthy_returns_false_when_file_missing_on_disk(): void {
		$family = 'Missing File Font';

		$this->mock_uploads_dir();
		Functions\when( 'get_option' )->justReturn( [
			$family => [
				'stylesheet_path' => 'style-manager/fonts/missing-file-font/stylesheet.css',
				'status'          => 'ok',
			],
		] );

		$store = new LocalFontStore( $this->createMock( LoggerInterface::class ) );

		$this->assertFalse( $store->is_healthy( $family ) );
	}

	/*
	 * Test helpers
	 */

	private function mock_uploads_dir(): void {
		$basedir = sys_get_temp_dir() . '/sm-local-font-store-' . str_replace( '.', '-', uniqid( '', true ) );
		mkdir( $basedir, 0777, true );

		Functions\when( 'wp_get_upload_dir' )->justReturn( [
			'basedir' => $basedir,
			'baseurl' => 'https://example.com/wp-content/uploads',
		] );
		Functions\when( 'trailingslashit' )->alias( static fn( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_mkdir_p' )->alias( static function( string $dir ): bool {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}

			return true;
		} );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static function( $response ) {
			return $response['response']['code'] ?? 0;
		} );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static function( $response ) {
			return $response['body'] ?? '';
		} );
	}

	/**
	 * Mock a healthy manifest entry with a real stylesheet.css file on disk.
	 */
	private function mock_healthy_entry( string $family, string $last_modified ): void {
		$this->mock_uploads_dir();

		$slug = LocalFontStore::derive_slug( strtolower( str_replace( ' ', '-', $family ) ) . '/stylesheet.css' );
		// Force a predictable slug matching the family's kebab-case for readability in assertions.
		$slug = strtolower( str_replace( ' ', '-', $family ) );

		Functions\when( 'get_option' )->justReturn( [
			$family => [
				'stylesheet_path' => "style-manager/fonts/{$slug}/stylesheet.css",
				'status'          => 'ok',
				'last_modified'   => $last_modified,
			],
		] );

		$upload_dir = wp_get_upload_dir();
		$dir        = $upload_dir['basedir'] . "/style-manager/fonts/{$slug}";
		mkdir( $dir, 0777, true );
		file_put_contents( $dir . '/stylesheet.css', 'body{}' );
	}

	/**
	 * @param string[] $methods
	 *
	 * @return LocalFontStore&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function partial_mock( array $methods ) {
		return $this->getMockBuilder( LocalFontStore::class )
			->setConstructorArgs( [ $this->createMock( LoggerInterface::class ) ] )
			->onlyMethods( $methods )
			->getMock();
	}

	/**
	 * Find the key in a `$written` map (absolute-path => contents, as recorded
	 * by write_file() mocks) whose path ends with the given relative suffix.
	 *
	 * @param array<string, string> $written           The recorded written files.
	 * @param string                $relative_path_suffix The relative (uploads-relative) path to look for.
	 *
	 * @return string|null The matching absolute path key, or null if none found.
	 */
	private function find_written_path( array $written, string $relative_path_suffix ): ?string {
		foreach ( array_keys( $written ) as $path ) {
			if ( str_ends_with( $path, $relative_path_suffix ) ) {
				return $path;
			}
		}

		return null;
	}
}
