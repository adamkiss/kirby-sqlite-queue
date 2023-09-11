<?php

use Adamkiss\SqliteQueue\Job;
use Kirby\Cache\FileCache;
use Kirby\Cache\Value;
use Kirby\Cms\App;
use Kirby\Toolkit\Str;
use Kirby\Database\Database;
use Kirby\Toolkit\Obj;

require_once __DIR__ . '/vendor/autoload.php';

$db = new Database([
	'database' => __DIR__ . '/db.sqlite3',
	'type' => 'sqlite'
]);

foreach (Str::split(<<<SQL
CREATE TABLE IF NOT EXISTSjobs (
	id TEXT PRIMARY KEY NOT NULL,
	status INTEGER,
	created DATE NOT NULL,
	due DATE,
	data TEXT
);
SQL, ';') as $sql) {
	$db->execute($sql);
}

ray()->clearScreen();

// $job = new Job(['oh' => page('home')]);
// $result = $db->table('jobs')->insert([
// 	'id' => $job->id(),
// 	'status' => $job->status()->value,
// 	'created' => (new DateTime('now', new DateTimeZone('Europe/Bratislava')))->format('c'),
// 	'due' => $job->due(),
// 	'data' => serialize($job->data()),
// ]);

// $job = $db->table('jobs')->select('*')->fetch('Adamkiss\\SqliteQueue\\Job')->first();
ray()->measure();
$job = new Job($db->table('jobs')->select('*')->first());
ray()->measure();
ray($job);
