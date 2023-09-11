<?php

/**
 * This is a command used after "composer install" is called for this project (when developing it)
 * It goes and removes the "dump" and "go" helpers from kirby, because I haven't found a reliable
 * way of turning them off on each vendor/bin execution, and they interfere with `global-ray`
 */

define('HELPERS_FILE', __DIR__ . '/test/kirby/config/helpers.php');
$helpers = @file_get_contents(HELPERS_FILE);
if (!$helpers) {
    exit;
}

$modified = preg_replace('/^if.*?\'dump\'.*?$([\w\W]*?)^}$/m', '// DUMP HELPER REMOVED', $helpers);
$modified = preg_replace('/^if.*?\'go\'.*?$([\w\W]*?)^}$/m', '// GO HELPER REMOVED', $modified);

if ($modified !== $helpers && !empty($modified)) {
	file_put_contents(HELPERS_FILE, $modified);
	echo "kirby/config/helpers.php modified.";
}
