<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Pixelgrade\StyleManager\Lab\ContractCatalog;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class ContractCatalogTest extends TestCase {
	public function test_catalog_contains_the_runtime_contract_rows_in_display_order(): void {
		$rows = ContractCatalog::all();

		$this->assertSame(
			[
				'active-palette',
				'contextual-palette',
				'color-signal',
				'dark-mode',
				'safe-semantic-roles',
				'wordpress-bridge',
				'ai-tool-export',
			],
			array_column( $rows, 'id' )
		);
	}

	public function test_each_contract_has_required_builder_fields(): void {
		foreach ( ContractCatalog::all() as $row ) {
			$this->assertContains( $row['maturity'], [ 'available', 'reference', 'proposed' ] );
			$this->assertNotEmpty( $row['label'] );
			$this->assertNotEmpty( $row['visual_behavior'] );
			$this->assertArrayHasKey( 'consume_today', $row );
			$this->assertArrayHasKey( 'proposed_api', $row );
			$this->assertNotEmpty( $row['pixelgrade_proof'] );
			$this->assertNotEmpty( $row['snippet'] );
		}
	}

	public function test_contract_can_be_resolved_by_id_with_default_fallback(): void {
		$this->assertSame( 'contextual-palette', ContractCatalog::by_id( 'contextual-palette' )['id'] );
		$this->assertSame( 'active-palette', ContractCatalog::by_id( 'missing' )['id'] );
	}
}
