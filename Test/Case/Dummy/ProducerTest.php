<?php
namespace Rabbit\Dummy;

class ProducerTest extends \CakeTestCase {
	public function testShould() {
		(new Producer())->add()->add()->add()->run();
	}
}