<?php
namespace Rabbit\Dummy;

use Rabbit\Consumer as Base;
use PhpAmqpLib\Message\AMQPMessage;

class Consumer extends Base {
	protected $_function = 'dummy';

	public function callback(AMQPMessage $Message) {
		try {
			$result = $Message->delivery_info['delivery_tag'];

			// отправляем ответ, если он вообще нужен
			if ($Message->has('reply_to')) {
				$Message->delivery_info['channel']->basic_publish(
					new AMQPMessage($this->_encode($result),
						['correlation_id' => $Message->get('correlation_id')]
					), '', $Message->get('reply_to')
				);
			}
			// подтверждаем
			$Message->delivery_info['channel']->basic_ack($Message->delivery_info['delivery_tag']);
		} catch (\Exception $e) {
			$Message->delivery_info['channel']->basic_nack($Message->delivery_info['delivery_tag']);
		}
	}
}