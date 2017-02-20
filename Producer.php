<?php
namespace Rabbit;

use PhpAmqpLib\Message\AMQPMessage;
use SplQueue;

class Producer extends Base {
	protected static $_defaults = [
		'background'	=> true,
		'priority'		=> null
	];

	/**
	 * Сюда собираем все задачи
	 * @var \SplQueue
	 */
	protected $_Messages = null;

	/**
	 *
	 * Очередь для callback-ов от consumer
	 * @var string
	 */
	protected $_cbQueue = null;

	/**
	 * Массив находящихся сообщений в текущей сессии
	 *
	 */
	protected $_collerations = [];

	/**
	 *
	 * Массив сообщений, которые обрабатываются на  данный момент
	 * @var array
	 */
	protected $_runtime = [];

	public function __construct($namespace = null, array $config = []) {
		parent::__construct($namespace, $config);

		// регистрируем callback-функции
		if (
			method_exists($this, 'onResponse')
			|| (array_key_exists('background', $this->_config) && !$this->_config['background'])
		) {
			// объявляем очередь
			list($this->_cbQueue, ,) = $this->_Channel->queue_declare('', false, false, true, false);
		}

		$this->_Messages = new SplQueue();
	}

	/**
	 * Добавляем задачу в очередь
	 *
	 * @param mixed $data данные запроса
	 * @param string $correlation id сообщения
	 *
	 * @return Producer
	 */
	public function add($data = null, $correlation = null) {
		$Message = $this->_message($data, ['reply_to' => $this->_cbQueue, 'correlation_id' => $correlation ?: uniqid("", true)]);
		$this->_collerations[$Message->get('correlation_id')] = time();
		$this->_Messages->push($Message);

		return $this;
	}

	/**
	 * Запуск в работу
	 *
	 * @param mixed $data данные для задачи
	 * @param bool $wait ожидание результата
	 *@return mixed результатs
	 */
	public function run($data = null, $wait = true) {
		// добавляем задачу
		if ($data) {
			$this->add($data);
		}

		// обработка
		while ($this->_Messages->count()) {
			$message = $this->_Messages->pop();

			$this->_Channel->basic_publish($message, '', $this->_queue());

			// массив запущенных задач
			$this->_runtime[$message->get('correlation_id')] = time();

		}


		// если есть обработчик ответов, то слушаем его
		if ($this->_cbQueue && $wait) {
			if (method_exists($this, 'onResponse')) {
				$this->_Channel->basic_consume(
					$this->_cbQueue, '', false, true, false, false,
					[$this, 'onResponse']
				);
			} else {
				// если же метода нет, но подождать надо, то тут всё просто:
				$this->_Channel->basic_consume(
					$this->_cbQueue, '', false, true, false, false,
					function(AMQPMessage $Message) {
						$response = $this->_decode($Message->body);

						unset(
							$this->_collerations[$Message->get('correlation_id')],
							$this->_runtime[$Message->get('correlation_id')]
						);

						if ($response instanceof \Exception) {
							throw $response;
						}

						return true;
					}
				);
			}
			// ждём
			while (count($this->_collerations)) {
				$this->_Channel->wait();
			}

		}

		return true;
	}
}