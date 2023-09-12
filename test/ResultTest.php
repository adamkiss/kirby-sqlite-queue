<?php

use Adamkiss\SqliteQueue\Result;

// @covers result::from_db
it('can read create itself from db', function () {
	$kirby = createKirby([
		'database' => ':memory:',
		'queues' => [
			'default' => fn($data) => $data['site']->queue()->next_job()->data,
		],
	]);
	$site = $kirby->site();

	$job1 = $site->queue()->add(['name' => 'job1', 'site' => $site]);

	$result1 = $site->queue()->next_job()->execute();
	$result2 = $site->queue()->db()->table('logs')->fetch(fn($r) => Result::from_db($site->queue(), $r))->limit(1)->all()->first();

	// dates are not equal due to sqlite precision
	// expect($result2)->toEqual($result1);

	expect($result2->id())->toEqual($result1->id());
	expect($result2->data())->toEqual($result1->data());
	expect($result2->result())->toEqual($result1->result());
	expect($result2->status())->toEqual($result1->status());
	expect($result2->attempt())->toEqual($result1->attempt());

	expect($result2->created_at()->timestamp())->toEqual($result1->created_at()->timestamp());
	expect($result2->executed_at()->timestamp())->toEqual($result1->executed_at()->timestamp());
	expect($result2->completed_at()->timestamp())->toEqual($result1->completed_at()->timestamp());
});
