<?php
namespace Rabbit;

use PhpAmqpLib\Message\AMQPMessage;

class Consumer extends Base {
	/**
	 * @var mixed тело сообщения
	 */
	protected $_body = null;

	/**
	 * Запуск в работу
	 */
	public function work() {
		if (is_array($this->_function)) {
			foreach ($this->_function as $function => $callback) {
				if (is_numeric($function)) {
					$function = $callback;
				}

				$this->_Channel->basic_consume(
					$this->_queue($function), '', false, false, false, false,
					[$this, lcfirst($callback)]
				);
			}
		} else {
			$this->_Channel->basic_consume(
				$this->_queue(), '', false, false, false, false,
				[$this, 'callback']
			);
		}


		// слушаем чё там как
		while(count($this->_Channel->callbacks)) {
			$this->_Channel->wait();
		}
	}

	/**
	 * callback на функцию по оработке задачи
	 *
	 * @param AMQPMessage $Message
	 * @return mixed|string
	 */
	public function callback(AMQPMessage $Message) {
		$this->_body = $this->_decode($Message->body);

		return $this->_body;
	}
}