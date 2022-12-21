<?php

$pharIndexFile = 'phar-index.php';

$replacements = [
    '/../src/' => '/src/',
    "#!/usr/bin/env php\n" => '',
    '#APP_VERSION#' => trim(exec('git describe --tags --abbrev=0'))
];

file_put_contents(
    $pharIndexFile,
    str_replace(
        array_keys($replacements),
        array_values($replacements),
        file_get_contents('bin/bb')
    )
);

$phar = new Phar('bb.phar');
$phar->buildFromDirectory(dirname(__FILE__), '[src|config|phar\-index\.php]');
$phar->setStub("#!/usr/bin/env php\n".$phar->createDefaultStub($pharIndexFile));

unlink($pharIndexFile);
