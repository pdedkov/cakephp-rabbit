<?php
namespace Rabbit;

class Priority extends \Enum\Base {
	// низкий приоритет задачи
	const LOW = 4;
	// минимальный приоритет
	const MIN = 0;
	// максимальный приоритет
	const MAX = 10;
	// нормальный приоритет
	const NORMAL = 5;
	// высокий приоритет
	const HIGH = 6;
	// пользовательский приоритет
	const USER = 7;
}