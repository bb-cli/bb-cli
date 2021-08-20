<?php

$phar = new Phar('bb.phar');
$phar->buildFromDirectory(dirname(__FILE__) . '/src');
$phar->setStub("#!/usr/bin/env php\n".$phar->createDefaultStub('phar-index.php'));
// $phar->compress(PHAR::GZ);
