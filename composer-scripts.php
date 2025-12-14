<?php
// composer-scripts.php

declare(strict_types=1);

//
$commands = [
    'database:updateschema -v',
    'language:update',
    'cache:flush'
];

function isCommandAvailable(string $command): bool {
    $which = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'where' : 'command -v';
    $process = proc_open(
        $which . ' ' . escapeshellarg($command),
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        return false;
    }
    // Read and close to avoid blocking
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    return $status === 0;
}

function run(string $cmd): int {
    // Durchreichen von Ausgabe und Exitcode
    passthru($cmd, $exitCode);
    return (int)$exitCode;
}

// Entscheide, ob DDEV genutzt werden kann
$useDdev = false;

// Schnelle Heuristik: env + executable + lauffähig
if (isCommandAvailable('ddev')) {
    // „ddev describe“ ist schnell und überprüft Projektkontext
    $useDdev = (run('ddev describe > NUL 2>&1') === 0) || (run('ddev describe > /dev/null 2>&1') === 0);
    // Fallback: Wenn describe fehlschlägt, aber Environment gesetzt ist, trotzdem versuchen
    if (!$useDdev && getenv('DDEV_PROJECT')) {
        $useDdev = true;
    }
}

$overallExit = 0;

foreach ($commands as $cmd) {
    if ($useDdev) {
        $full = 'typo3 ' . $cmd;
    } else {
        // plattformneutral: immer über die aktuelle PHP-Binary
        $typo3Cli = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/vendor/bin/typo3');
        $full = $typo3Cli . ' ' . $cmd;
    }

    echo "\n> " . $full . "\n";
    $code = run($full);
    if ($code !== 0) {
        $overallExit = $code;
        break;
    }
}

exit($overallExit);
