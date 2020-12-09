<?php

namespace HAMWORKS\WP\Simple_CSV_Exporter\Tests;

use HAMWORKS\WP\Simple_CSV_Exporter\Data_Builder;
use HAMWORKS\WP\Simple_CSV_Exporter\Data_Builder_Factory;
use HAMWORKS\WP\Simple_CSV_Exporter\Data_Builder_For_WP_Posts;
use WP_UnitTestCase;

/**
 * Unit test for Data_Builder_Factory
 *
 * @package HAMWORKS\WP\Simple_CSV_Exporter\Tests
 */
class Data_Builder_Factory_Test extends WP_UnitTestCase {

	public function test_create() {
		$factory = new Data_Builder_Factory();

		$post_data_builder = $factory->create( 'WordPress', array( 'post_type' => 'post' ) );
		$this->assertInstanceOf( Data_Builder_For_WP_Posts::class, $post_data_builder );
		$this->assertEquals( 'post', $post_data_builder->get_post_type() );

		$page_data_builder = $factory->create( 'WordPress', array( 'post_type' => 'page' ) );
		$this->assertInstanceOf( Data_Builder_For_WP_Posts::class, $page_data_builder );
		$this->assertEquals( 'page', $page_data_builder->get_post_type() );
	}

	public function test_action_simple_csv_exporter_created_data_builder() {
		add_action(
			'simple_csv_exporter_created_data_builder',
			function ( Data_Builder $data ) {
				// Remove column.
				$data->append_drop_column( 'page_template' );
				// Add custom field column.
				$data->append_meta_key( 'my_meta_key' );
			}
		);
		$this->factory()->post->create_many( 2 );
		$factory      = new Data_Builder_Factory();
		$data_builder = $factory->create( 'WordPress', array( 'post_type' => 'post' ) );
		foreach ( $data_builder->get_rows() as $row ) {
			$this->assertArrayNotHasKey( 'page_template', $row );
			$this->assertArrayHasKey( 'my_meta_key', $row );
		}
	}
}