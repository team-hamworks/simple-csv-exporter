<?php


namespace HAMWORKS\WP\Simple_CSV_Exporter;

/**
 * Factory for Data_Builder.
 *
 * @package HAMWORKS\WP\Simple_CSV_Exporter
 */
class Data_Builder_Factory {

	public function create( $type, array $param ): Data_Builder {
		if ( 'WordPress' === $type ) {
			$post_type    = $param['post_type'] ?? 'post';
			$data_builder = new Data_Builder_For_WP_Posts( $post_type );
			/**
			 * Fires after data generator is created, but before export.
			 *
			 * @param Data_Builder $data
			 */
			do_action( 'simple_csv_exporter_created_data_builder', $data_builder );
			return $data_builder;
		}
	}
}