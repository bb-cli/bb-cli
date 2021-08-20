<?php

$pharIndexFile = 'phar-index.php';

$binFileContents = file_get_contents('bin/bb');
file_put_contents(
    $pharIndexFile,
    str_replace(
        ['/../src/', "#!/usr/bin/env php\n"],
        ['/src/', ''],
        $binFileContents
    )
);

$phar = new Phar('bb.phar');
$phar->buildFromDirectory(dirname(__FILE__), '[src|config|phar\-index\.php]');
$phar->setStub("#!/usr/bin/env php\n".$phar->createDefaultStub('phar-index.php'));

unlink($pharIndexFile);
