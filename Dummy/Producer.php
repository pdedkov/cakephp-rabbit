<?php
namespace Rabbit\Dummy;

use Rabbit\Producer as Base;
use PhpAmqpLib\Message\AMQPMessage;

class Producer extends Base {
	protected $_function = 'dummy';

	public function onResponse(AMQPMessage $Message) {
		unset($this->_collerations[$Message->get('correlation_id')]);
	}
}