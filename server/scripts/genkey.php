<?php
/** Generates a random key for APP_KEY / SESSION_SECRET. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
echo bin2hex(random_bytes(32)) . "\n";
