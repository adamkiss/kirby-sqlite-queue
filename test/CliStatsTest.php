<?php

use Kirby\Filesystem\F;
use Adamkiss\SqliteQueue\Plugin;
use League\CLImate\Util\Writer\Buffer;

test('Complex Stats & CLI test', function() {

	function process_job_and_sometime_fail(array $data) {
		if ($data['i'] % 7 === 0) {
			throw new Exception('Failed');
		}
		return null;
	}

	F::remove(kirby()->root('site') . '/db/stats-test.sqlite');
	$kirby = createKirby([
		'database' => kirby()->root('site') . '/db/stats-test.sqlite',
		'queues' => [
			'first' => [
				'handler' => 'process_job_and_sometime_fail',
				'retries' => 0
			],
			'second' => [
				'handler' => 'process_job_and_sometime_fail',
				'retries' => 0
			],
			'third' => [
				'handler' => 'process_job_and_sometime_fail',
				'retries' => 0
			],
		]
	]);
	$plugin = Plugin::instance($kirby);

	// add 200 jobs to queues
	for($i = 0; $i < 200; $i++) {
		$kirby->site()->queue(['first', 'second', 'third'][$i % 3])->add(['i' => $i]);
	}
	// process 150 jobs
	for($i = 0; $i < 150; $i++) {
		$kirby->site()->queue()->next_job()->execute();
	}

	expect($plugin->stats())->toBe([
		"first" => [
			"Queue name" => "first",
			"Waiting" => 17,
			"In progress" => 0,
			"Completed" => 42,
			"Failed" => 8,
		],
		"second" => [
			"Queue name" => "second",
			"Waiting" => 17,
			"In progress" => 0,
			"Completed" => 43,
			"Failed" => 7,
		],
		"third" => [
			"Queue name" => "third",
			"Waiting" => 16,
			"In progress" => 0,
			"Completed" => 43,
			"Failed" => 7,
		],
	]);

	expect($plugin->stats('first'))->toBe([
		"Queue name" => "first",
		"Waiting" => 17,
		"In progress" => 0,
		"Completed" => 42,
		"Failed" => 8,
	]);

	expect($kirby->site()->queue('third')->stats())->toBe([
		"Queue name" => "third",
		"Waiting" => 16,
		"In progress" => 0,
		"Completed" => 43,
		"Failed" => 7,
	]);

	$cli = new Kirby\CLI\CLI($kirby);
	$buffer = new Buffer();
	$cli->climate()->output->add('test-output', $buffer);
	$cli->climate()->output->defaultTo('test-output');

	$cmd = $cli->load('queue:stats');
	$cmd['command']($cli);

	expect($buffer->get())
		->toContain('| Queue name | Waiting | In progress | Completed | Failed')
		->toContain('| first      | 17      | 0           | 42        | 8')
		->toContain('| second     | 17      | 0           | 43        | 7')
		->toContain('| third      | 16      | 0           | 43        | 7');

	$cmd = $cli->load('queue:work');
	$cmd['command']($cli);

	expect($plugin->next_job())->toBeNull();
	expect($plugin->count_jobs())->toBe(0);

});
