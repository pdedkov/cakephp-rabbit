<?php
class ConsumerShell extends AppShell {
	/**
	 * Запуск подписчиков
	 *
	 * @return int
	 */
	public function run() {
		try {
			return !$this->_class($this->args[0])->work();
		} catch (\Exception $e) {
			$this->out($e->getMessage());
		}

		return 1;
	}

	/**
	 * Генерация класса подписчика
	 * @param string $consumer имя подписчика
	 * @return object
	 * @throws AppException
	 */
	protected function _class($consumer) {
		if (strpos($consumer, '\\') !== 0) {
			$class = '\Rabbit\\' . ucfirst($consumer) . '\Consumer';
		} else {
			$class = $consumer;
		}

		if (!class_exists($class)) {
			throw new \AppException('Недоступный подписчик');
		}


		return new $class();
	}
}