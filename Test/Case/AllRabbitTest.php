<?php
/**
 * AllRabbitTest class
 *
 */
class AllRabbitTest extends PHPUnit_Framework_TestSuite {

	/**
	 * 	Метод сборки
	 */
	public static function suite() {
		$suite = new CakeTestSuite('All rabbit tests');

		$suite->addTestFile(__DIR__ . DS . 'Dummy' . DS . 'ProducerTest.php');
		$suite->addTestFile(__DIR__ . DS . 'Dummy' . DS . 'ConsumerTest.php');

		return $suite;
	}
}