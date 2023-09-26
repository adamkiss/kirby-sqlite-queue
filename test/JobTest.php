<?php


// @covers Job::lock
it('Locks itself correctly', function () {
	$kirby = createKirby([
		'database' => ':memory:',
		'queues' => [
			'default' => fn ($data) => $data['site']->queue()->next_job()->data,
		],
	]);
	$site = $kirby->site();

	$job1 = $site->queue()->add(['name' => 'job1', 'site' => $site]);
	$job2 = $site->queue()->add(['name' => 'job2', 'site' => $site]);

	$result = $site->queue()->next_job()->execute();

	expect($result->result())->toEqual(['name' => 'job2', 'site' => $site, ]);
});
