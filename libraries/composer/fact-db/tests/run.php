<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$count = (new \FactDb\Tests\ContractTest())->run();
echo "FactDB: {$count} contract cases passed.\n";
