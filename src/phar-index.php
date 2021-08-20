<?php

require_once __DIR__.'/utils/helpers.php';
require_once __DIR__.'/Base.php';

foreach (glob(__DIR__.'/Actions/*.php') as $file) {
    require_once $file;
}

$actions = [
    'pr' => \BBCli\BBCli\Actions\Pr::class,
    'pipeline' => \BBCli\BBCli\Actions\Pipeline::class,
    'branch' => \BBCli\BBCli\Actions\Branch::class,
    'auth' => \BBCli\BBCli\Actions\Auth::class,
];

$helpCommands = [
    'help',
    '--help',
    '-h',
];

$userBaseAction = $argv[1] ?? null;

if (is_null($userBaseAction) || in_array($userBaseAction, $helpCommands)) {
    e('Available actions:', 'green');
    e(array_keys($actions), 'green');
    exit(0);
}

if (!isset($actions[$userBaseAction])) {
    e("Given action is invalid: ({$userBaseAction})", 'red');
    exit(1);
}

$actionClass = new $actions[$userBaseAction]();
$userAction = $argv[2] ?? null;

if (in_array($userAction, $helpCommands)) {
    e('Available methods:', 'green');
    e(array_values($actionClass::AVAILABLE_COMMANDS));
    exit(0);
}

$classMethodToCall = $actionClass::DEFAULT_METHOD;

if (!is_null($userAction)) {
    $classMethodToCall = $actionClass->getMethodNameFromAlias($userAction);
}

if ($classMethodToCall === false) {
    e("Method not exists. ({$argv[2]})", 'red');
    exit(1);
}

try {
    call_user_func_array(
        [$actionClass, $classMethodToCall],
        array_slice($argv, 3)
    );
} catch (\ArgumentCountError $e) {
    e('Too few arguments.', 'red');
    exit(1);
} catch (\Throwable $th) {
    e($th->getMessage(), 'red');
    exit(1);
}
