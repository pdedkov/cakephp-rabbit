<?php
namespace Rabbit;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use Config\Object;
use Hash;
use PhpAmqpLib\Message\AMQPMessage;

abstract class Base extends Object {
	/**
	 * Соединение
	 * @var AMQPStreamConnection
	 */
	protected $_Connection = null;

	/**
	 * Канал обмена данными с сервером
	 *
	 * @var AMQPChannel
	 */
	protected $_Channel = null;

	/**
	 * имя очереди
	 * @var string
	 */
	protected $_queue = null;

	/**
	 * Исполняема функция
	 * @var string
	 */
	protected $_function = null;

	/**
	 * Настройки по умолчанию
	 * @var array
	 */
	protected static $_defaults = [
		'server' => [
			'host' => 'localhost',
			'port' => 5672,
			'user' => 'guest',
			'password' => 'guest',
			'vhost' => '/'
		],
		'connect'		=> true,
		'qos' => 1,
		'queue' => [
			'options' => [
				'x-max-priority' => 10
			]
		]
	];

	/**
	 * Признак установленного соединения
	 * @var bool
	 */
	protected $_connected = false;

	public function __construct($namespace = null, $config = []) {
		static::$_defaults = Hash::merge(self::$_defaults, static::$_defaults);

		if ($namespace) {
			$config = $this->_config(__NAMESPACE__, $config);
		} else {
			$namespace = __NAMESPACE__;
		}
		parent::__construct($namespace, $config);

		// соединение
		if ($this->_config['connect']) {
			$this->_connect();
		}
	}

	/**
	 *  Подключение к серверу
	 *
	 * @param bool $reconnect пепеподключение, если нужно
	 * @return bool
	 */
	protected function _connect($reconnect = true) {
		// уже подключены
		if (!$reconnect && $this->_connected) {
			return $this->_connected;
		}

		// отключаемся
		$this->_disconnect();
		try {
			// соединение
			$this->_Connection = new AMQPStreamConnection(
				$this->_config['server']['host'],
				$this->_config['server']['port'],
				$this->_config['server']['user'],
				$this->_config['server']['password'],
				$this->_config['server']['vhost']
			);
			// канал
			$this->_Channel = $this->_Connection->channel();

			// можно отправить в очередь отдельно для каждого обработчика
			if (!empty($this->_config['qos'])) {
				$this->_Channel->basic_qos(null, $this->_config['qos'], null);
			}
			$this->_declare();

			$this->_connected = true;
		} catch (\Exception $e) {
			throw new Exception('Could not connect: ' . $e->getMessage(), $e->getCode());
		}

		return $this->_connected;
	}

	/**
	 * Обяъвляем очередь
	 */
	protected function _declare() {
		if (empty($this->_function)) {
			return;
		}

		if (is_array($this->_function)) {
			foreach ($this->_function as $function => $callback) {
				if (is_numeric($function)) {
					$function = $callback;
				}

				// объявляем очередь
				$this->_Channel->queue_declare(
					$this->_queue($function),
					false, false, false, false, false,
					new AMQPTable($this->_config['queue']['options'])
				);
			}
		} else {
			// объявляем очередь
			$this->_Channel->queue_declare(
				$this->_queue(),
				false, false, false, false, false,
				new AMQPTable($this->_config['queue']['options'])
			);
		}
	}

	/**
	 * Отключаемся
	 *
	 * @return bool
	 */
	protected function _disconnect() {
		try {
			if ($this->_connected) {
				$this->_Channel->close();
				$this->_Connection->close();
			}
		} catch (\Exception $e) {

		}
		$this->_Channel = $this->_Connection = null;
		$this->_connected = false;

		return !$this->_connected;
	}

	/**
	 * Определяем название очереди
	 *
	 * @param string $function имя функции
	 * @return имя метода
	 */
	protected function _queue($function = null) {
		if (empty($this->_queue) || !empty($function)) {
			$function = $function ?: $this->_function;
			$class = get_class($this);
			if (strpos($function, '\\') === false) {
				$this->_queue = (new \ReflectionClass($class))->getNamespaceName() . '\\' . $function;
			}  else {
				$this->_queue = $function;
			}
		}

		return $this->_queue;
	}

	/**
	 * Подготовка сообщения
	 *
	 * @param mixed $body тело сообщения
	 * @param array $options дополнительные параметры
	 *
	 * @return AMQPMessage готовое сообщение
	 */
	protected function _message($body, $options = []) {
		if (array_key_exists('priority', $body)) {
			$options['priority'] = $body['priority'];
			unset($body['priority']);
		}

		// добавляем приоритет
		$options['priority'] = array_key_exists('priority', $options) ? $options['priority'] : Priority::NORMAL;

		// обработка приоритета
		if ($options['priority'] < Priority::LOW) {
			$options['priority'] = Priority::MIN;
		} elseif ($options['priority'] > Priority::HIGH) {
			$options['priority'] = Priority::HIGH;
		}

		return new AMQPMessage($this->_encode($body), $options);
	}

	/**
	 * Генерация тела сообщения
	 *
	 * @param mixed $data данные для отправки
	 * @return string
	 */
	protected function _encode($data) {
		return serialize($data);
	}

	/**
	 * Декодируем тело
	 * @param string $data
	 * @return mixed
	 */
	protected function _decode($data) {
		return unserialize($data);
	}

	public function __destruct() {
		return $this->_disconnect();
	}
}