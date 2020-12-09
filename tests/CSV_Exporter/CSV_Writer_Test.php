<?php

namespace HAMWORKS\WP\Tests\Simple_CSV_Exporter;

use HAMWORKS\WP\Simple_CSV_Exporter\CSV_Writer;
use WP_UnitTestCase;

/**
 * Test for CSV_Writer
 */
class CSV_Writer_Test extends WP_UnitTestCase {

	/**
	 * @test
	 */
	public function test_write_stream() {
		$data = array(
			array(
				'a' => 1,
				'b' => 2,
			),
			array(
				'a' => 'A',
				'b' => 'B',
			),
			array(
				'a' => 'あいうえお',
				'b' => 'あ い う え お',
			),
		);

		$expect = <<<CSV
a,b
1,2
A,B
あいうえお,"あ い う え お"

CSV;

		$this->expectOutputString( $expect );
		$csv = new CSV_Writer( $data, 'php://output' );
		$csv->write( $data );
	}

	/**
	 * @test
	 */
	public function test_write_file() {
		$data = array(
			array(
				'a' => 1,
				'b' => 2,
			),
			array(
				'a' => 'A',
				'b' => 'B',
			),
			array(
				'a' => 'あいうえお',
				'b' => 'あ い う え お',
			),
		);

		$expect = <<<CSV
a,b
1,2
A,B
あいうえお,"あ い う え お"

CSV;

		$csv = new CSV_Writer( $data, '/tmp/test.csv' );
		$csv->write( $data );
		$this->assertFileExists( '/tmp/test.csv' );
		$this->assertIsReadable( '/tmp/test.csv' );
		$this->assertStringEqualsFile( '/tmp/test.csv', $expect );
	}
}