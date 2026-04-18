<?php

// MySQL cannot create FULLTEXT indexes on temporary InnoDB tables (Error 1796).
// Force MediaWiki's test framework to use normal (prefixed) tables instead.
putenv( 'PHPUNIT_USE_NORMAL_TABLES=1' );
$_ENV['PHPUNIT_USE_NORMAL_TABLES'] = '1';

// Load MediaWiki's test bootstrap. Assumes the extension lives in MediaWiki's
// extensions/ directory (standard installation layout).
$IP = getenv( 'MW_INSTALL_PATH' ) ?: __DIR__ . '/../../..';
require_once "$IP/tests/phpunit/bootstrap.php";
