<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Container;
use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Container\ContainerInterface;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

use function Pixelgrade\StyleManager\plugin;

class ColorPalettesDarkModeAttributeTest extends TestCase {
	/**
	 * @dataProvider valid_dark_mode_values
	 */
	public function test_frontend_html_language_attributes_include_constrained_dark_mode_value( string $value ): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'esc_attr' )->alias( static fn( $text ): string => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ) );

		$output = $this->invoke_with_dark_mode_option( $value, 'lang="en-US"', 'html' );

		$this->assertSame(
			'lang="en-US" data-dark-mode-advanced="' . $value . '"',
			$output
		);
	}

	public static function valid_dark_mode_values(): array {
		return [
			'off'  => [ 'off' ],
			'on'   => [ 'on' ],
			'auto' => [ 'auto' ],
		];
	}

	public function test_frontend_html_language_attributes_fall_back_to_off_for_unexpected_values(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'esc_attr' )->alias( static fn( $text ): string => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ) );

		$output = $this->invoke_with_dark_mode_option(
			'auto" onclick="alert(1)',
			'lang="en-US"',
			'html'
		);

		$this->assertSame( 'lang="en-US" data-dark-mode-advanced="off"', $output );
	}

	public function test_dark_mode_attribute_is_not_added_in_admin_or_non_html_doctypes(): void {
		Functions\when( 'esc_attr' )->alias( static fn( $text ): string => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ) );

		Functions\when( 'is_admin' )->justReturn( true );
		$this->assertSame(
			'lang="en-US"',
			$this->invoke_with_dark_mode_option( 'on', 'lang="en-US"', 'html' )
		);

		Functions\when( 'is_admin' )->justReturn( false );
		$this->assertSame(
			'lang="en-US"',
			$this->invoke_with_dark_mode_option( 'on', 'lang="en-US"', 'xhtml' )
		);
	}

	private function invoke_with_dark_mode_option( string $value, string $output, string $doctype ): string {
		$plugin            = plugin();
		$previous_container = $plugin->get_container();
		$container         = new Container();
		$container['options'] = new class( $value ) {
			public function __construct( private readonly string $value ) {}

			public function get( string $option_id, $default = null, $option_details = null ) {
				if ( 'sm_dark_mode_advanced' !== $option_id ) {
					return $default;
				}

				return $this->value;
			}
		};

		$plugin->set_container( $container );

		try {
			$color_palettes = new ColorPalettes(
				$this->createMock( DesignAssets::class ),
				$this->createMock( LoggerInterface::class )
			);

			return PHPUnitUtil::getProtectedMethod( $color_palettes, 'add_dark_mode_data_attribute' )
				->invoke( $color_palettes, $output, $doctype );
		} finally {
			if ( $previous_container instanceof ContainerInterface ) {
				$plugin->set_container( $previous_container );
			} else {
				$property = new \ReflectionProperty( $plugin, 'container' );
				$property->setAccessible( true );
				$property->setValue( $plugin, null );
			}
		}
	}
}
