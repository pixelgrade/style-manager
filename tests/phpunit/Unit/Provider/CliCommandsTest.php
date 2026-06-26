<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_CLI', false ) ) {
		class WP_CLI {
			public static array $commands = [];
			public static array $success_messages = [];

			public static function add_command( string $name, $callback ): void {
				self::$commands[ $name ] = $callback;
			}

			public static function success( string $message ): void {
				self::$success_messages[] = $message;
			}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Pixelgrade\StyleManager\Provider\CliCommands;
	use Pixelgrade\StyleManager\Provider\Options;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	class CliCommandsTest extends TestCase {
		public function setUp(): void {
			parent::setUp();

			\WP_CLI::$commands         = [];
			\WP_CLI::$success_messages = [];
		}

		public function test_register_hooks_adds_flush_cache_command_when_wp_cli_is_available(): void {
			$commands = new CliCommands( $this->createMock( Options::class ) );

			$commands->register_hooks();

			$this->assertArrayHasKey( 'style-manager flush-cache', \WP_CLI::$commands );
			$this->assertSame( [ $commands, 'flush_cache' ], \WP_CLI::$commands['style-manager flush-cache'] );
		}

		public function test_flush_cache_invalidates_all_caches_and_reports_success(): void {
			$options = $this->createMock( Options::class );
			$options
				->expects( $this->once() )
				->method( 'invalidate_all_caches' );

			$commands = new CliCommands( $options );

			$commands->flush_cache( [], [] );

			$this->assertSame(
				[ 'Style Manager caches flushed (Customizer config, option details, opt-name).' ],
				\WP_CLI::$success_messages
			);
		}
	}
}
