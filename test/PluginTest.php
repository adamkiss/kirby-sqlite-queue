<?php

use Adamkiss\SqliteQueue\Plugin;
use Adamkiss\SqliteQueue\Queue;
use Kirby\Exception\InvalidArgumentException;

it('integrates with Kirby', function () {
	$kirby = createKirby([
		'database' => ':memory:',
		'queues' => [
			'default' => fn () => null,
		],
	]);
	$plugin = Plugin::instance($kirby);
	$site = $kirby->site();

	// Plugin/config
	expect(array_keys($kirby->plugins()))->toContain('adamkiss/kirby-sqlite-queue');

	expect($site->queue())
		->toBeInstanceOf(Plugin::class)
		->toEqual($plugin);

	expect($site->queue('default'))->toBeInstanceOf(Queue::class);

	expect(fn () => $site->queue('non-existent'))->toThrow(InvalidArgumentException::class);

	// CLI
	$cli = new Kirby\CLI\CLI($kirby);
	expect($cli->commands()['plugins'])->toContain('queue:stats');
	expect($cli->commands()['plugins'])->toContain('queue:work');

	// Helper
	expect($kirby->site()->queue())->toEqual(queue());
	expect($kirby->site()->queue('default'))->toEqual(queue('default'));
});

it('supports queues defined in plugins', function () {
	$kirby = createKirby([
		'database' => ':memory:',
	], [
		// Option to "turn on" the plugin defined queues
		// This is for tests only - in real life you'd always extend the kirby options
		'options' => [
			'adamkiss.defines-a-queue' => true
		],
	]);
	$plugin = Plugin::instance($kirby);

	expect($kirby->site()->queue())->toEqual($plugin);

	expect($kirby->site()->queue()->all()->count())->toBe(2);

	// Get queue via plugin
	expect($kirby->site()->queue()->get('queue-from-plugin'))
		->toBeInstanceOf(Queue::class)
		->toHaveMethod('name', 'queue-from-plugin')
		->toHaveMethod('priority', 3)
		->toHaveMethod('retries', 2);

	// Get queue via site()->queue() argument
	expect($kirby->site()->queue('other-queue-from-plugin'))
		->toBeInstanceOf(Queue::class)
		->toHaveMethod('name', 'other-queue-from-plugin')
		->toHaveMethod('priority', 0)
		->toHaveMethod('retries', 3);
});

it('creates a database', function () {
	$kirby = createKirby([
		'database' => ':memory:',
	]);
	$plugin = Plugin::instance($kirby);

	expect($plugin->db())->toBeInstanceOf(\Adamkiss\SqliteQueue\Database::class);
});

it('provides a way to add jobs', function () {
	$kirby = createKirby([
		'database' => ':memory:',
		'queues' => [
			'notdefault' => fn () => null,
		]
	]);
	$plugin = Plugin::instance($kirby);

	$kirby->site()->queue()->add(['foo' => 'bar']);
	$kirby->site()->queue()->add('notdefault', ['foo' => 'bar']);

	expect($plugin->count_jobs())->toBe(2);

	$planned = $kirby->site()->queue()->add('notdefault', ['foo' => 'bar'], '+5 minutes');
	expect($plugin->get('notdefault')->count())->toBe(3);
	expect($planned->available_at)->not->toBeNull();
});

it('returns the next job to be done', function () {
	$kirby = createKirby([
		'database' => ':memory:',
		'queues' => [
			'notdefault' => fn () => null,
		]
	]);
	$plugin = Plugin::instance($kirby);

	$kirby->site()->queue()->add(['do' => 'now']);

	expect(queue()->next_job())->toBeInstanceOf(\Adamkiss\SqliteQueue\Job::class);
});
