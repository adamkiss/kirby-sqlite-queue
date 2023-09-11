<?php

use Adamkiss\SqliteQueue\Job;
use Adamkiss\SqliteQueue\Plugin;

$job = new Job(
	plugin: Plugin::instance(),
	data: [
		'param1' => 'value1',
		'param2' => 'value2',
	],
);
ray($job);

return true;
