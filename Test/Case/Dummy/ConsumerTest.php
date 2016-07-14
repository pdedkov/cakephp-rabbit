<?php
namespace Rabbit\Dummy;

class ConsumerTest extends \CakeTestCase {
	public function testShould() {
		$Consumer = new Consumer();
		$Consumer->work();
	}
}