<?php

date_default_timezone_set('Europe/Bratislava');

return [
	'debug' => true,
	'db' => [
		'type' => 'sqlite',
		'database' => __DIR__ . '/../database.sqlite',
	],
	'adamkiss.kirby-sqlite-queue' => [
		'queues' => []
	]
];
