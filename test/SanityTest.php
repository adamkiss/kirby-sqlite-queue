<?php

test('Sanity Check for forgotten ray() calls')
	->expect('Adamkiss\SqliteQueue')
	->toBeClasses()
	->not->toUse('ray');
