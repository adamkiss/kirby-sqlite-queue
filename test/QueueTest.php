<?php

use Adamkiss\SqliteQueue\Job;
use Adamkiss\SqliteQueue\Plugin;
use Adamkiss\SqliteQueue\Result;
use Kirby\Filesystem\F;
use Kirby\Toolkit\Date;

class TestCallable
{
	public static function handler($data)
	{
		return $data['param1'];
	}
}

if (!function_exists('test_callable_in_string')) {
	function test_callable_in_string($data)
	{
		return $data['param1'];
	}
}

beforeEach(function () {
	// Reset database
	F::remove(__DIR__ . '/site/db/queue.sqlite');

	// Create Kirby & Plugin instances
	$this->kirby = createKirby([
		'queues' => [
			'default' => [TestCallable::class, 'handler'],
			'important' => [
				'handler' => fn ($data) => $data['param2'],
				'priority' => 1,
				'retries' => 5,
			],
			'unimportant' => [
				'handler' => [TestCallable::class, 'handler'],
				'priority' => -1,
				'retries' => 0,
			],
			'fails' => [
				'handler' => fn ($data) => throw new Exception('Failed'),
				'priority' => 1,
				'retries' => 1,
				'backoff' => 0,
			],
			'fails-with-backoff' => fn ($data) => throw new Exception('Failed'),
			'handler-in-string' => 'test_callable_in_string',
			'handler-in-string-in-array' => [
				'handler' => 'test_callable_in_string',
			],
			'sync-queue' => [
				'handler' => fn ($data) => $data['hello'],
				'sync' => true,
			],
		]
	]);
	$this->plugin = Plugin::instance($this->kirby);
});

it('accepts jobs', function () {
	$job1 = $this->kirby->site()->queue('default')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);

	$job2 = $this->kirby->site()->queue('important')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);

	expect($job1)->toBeInstanceOf(Job::class);
	expect($job2)->toBeInstanceOf(Job::class);

	expect($this->plugin->get('default')->count())->toBe(1);
	expect($this->plugin->get('important')->count())->toBe(1);
});

it('can work jobs', function () {
	$this->kirby->site()->queue()->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$this->kirby->site()->queue('important')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$this->kirby->site()->queue('unimportant')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);

	$job2 = $this->kirby->site()->queue()->next_job();
	expect($job2)->toBeInstanceOf(Job::class);
	expect($job2->queue()->name())->toBe('important');
	expect($job2->data['param2'])->toBe('value2');

	$result = $job2->execute();
	expect($result)->toBeInstanceOf(Result::class);
	expect($result->result())->toBe('value2');

	$job1 = $this->kirby->site()->queue()->next_job();
	expect($job1)->toBeInstanceOf(Job::class);
	expect($job1->queue()->name())->toBe('default');
	expect($job2->data['param1'])->toBe('value1');

	$result = $job1->execute();
	expect($result)->toBeInstanceOf(Result::class);
	expect($result->result())->toBe('value1');

	$job3 = $this->kirby->site()->queue()->next_job();
	expect($job3)->toBeInstanceOf(Job::class);
	expect($job3->queue()->name())->toBe('unimportant');

	$result = $job3->execute();
	expect($result)->toBeInstanceOf(Result::class);
	expect($result->result())->toBe('value1');
});

it('can clear itself', function () {
	$this->kirby->site()->queue()->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$this->kirby->site()->queue()->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$this->kirby->site()->queue()->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);

	expect($this->plugin->get('default')->count())->toBe(3);
	expect($this->plugin->get('default')->clear())->toBe(true);
	expect($this->plugin->get('default')->count())->toBe(0);
});

it('can retry a failed job', function () {
	$job = $this->kirby->site()->queue('fails')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$result = $this->kirby->site()->queue()->next_job()->execute();

	expect($result->status())->toBe(1);
	expect($result->data())->toBe([
		'param1' => 'value1',
		'param2' => 'value2',
	]);

	expect($this->plugin->get('fails')->count())->toBe(1);
	$retry = $this->plugin->next_job();

	expect($retry->attempt)->toBe(2);
	expect($retry->data)->toEqual($job->data);
	expect($retry->queue()->name())->toBe('fails');
	expect($retry->available_at)->toBeNull();

	$retry->execute(); // Should fail again, now with no retries left
	expect($this->plugin->get('fails')->count())->toBe(0);
});

it('can back off a retry of a failed job', function () {
	$job = $this->kirby->site()->queue('fails-with-backoff')->add([
		'param1' => 'value1',
		'param2' => 'value2',
	]);
	$result = $this->kirby->site()->queue()->next_job()->execute();

	expect($this->plugin->get('fails-with-backoff')->count())->toBe(1);
	expect($this->plugin->next_job())->toBeNull();

	// Manually pull the job from the DB (normally this isn't available)
	$next_job = Job::from_db(
		$this->plugin,
		$this->plugin->db()->table('jobs')->fetch('array')->first()
	);

	expect($next_job->attempt)->toBe(2);
	expect($next_job->data)->toEqual($job->data);
	expect($next_job->available_at)
		->toBeInstanceOf(Date::class)
		->toBeGreaterThan(new Date('+14 minutes +59 seconds'))
		->toBeLessThan(new Date('+15 minutes +1 seconds'));
	// Default backoff time is 15 minutes
});

describe('handler formats', function () {
	test('closures', function () {
		expect($this->plugin->get('important')->handler())->toBeInstanceOf(Closure::class);
		expect($this->plugin->get('fails')->handler())->toBeInstanceOf(Closure::class);
	});

	test('class method arrays', function () {
		expect($this->plugin->get('unimportant')->handler())->toBeInstanceOf(Closure::class);
		expect($this->plugin->get('default')->handler())->toBeInstanceOf(Closure::class);
	});

	test('strings', function () {
		expect($this->plugin->get('handler-in-string')->handler())->toBeInstanceOf(Closure::class);
		expect($this->plugin->get('handler-in-string-in-array')->handler())->toBeInstanceOf(Closure::class);
	});
});

it('can work queue in a sync manner', function () {
	$queue = $this->kirby->site()->queue('sync-queue');

	expect($queue->sync())->toBe(true);
	expect($queue->count())->toBe(0);

	$result = $queue->add([
		'hello' => 'world',
		'unused' => 'param',
	]);
	expect($result)->toBeString();
	expect($queue->count())->toBe(0);

	expect($this->kirby->site()->queue()->next_job())->toBeNull();

	expect(fn () => $queue->add(['wrong' => 'params']))->toThrow('Undefined array key');
	expect($queue->count())->toBe(0);
});
