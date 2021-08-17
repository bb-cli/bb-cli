<?php

namespace BBCli\BBCli;

class Auth extends Base
{
    public const DEFAULT_METHOD = 'saveLoginInfo';
    public const CHECK_GIT_FOLDER = false;

    public const AVAILABLE_COMMANDS = [
        'saveLoginInfo' => 'save',
        'show' => 'show',
    ];

    public function saveLoginInfo()
    {
        e('This action requires app password:', 'yellow');
        e('If you don\'t have a app password you may create by following this link:', 'yellow');
        e('https://support.atlassian.com/bitbucket-cloud/docs/app-passwords/', 'green');

        $username = getUserInput('Username: ');
        $appPassword = getUserInput('App password: ');

        $saveToFile = userConfig([
            'auth' => [
                'username' => $username,
                'appPassword' => $appPassword,
            ],
        ]);

        if ($saveToFile !== false) {
            e('Auth info saved.', 'green');
        } else {
            e('Cannot save file to: '.config('userConfigFilePath'), 'red');
        }
    }

    public function show()
    {
        $authInfo = userConfig('auth');

        if (!$authInfo) {
            e('You have to configure auth info to use this command.', 'red');
            e('Run "bb auth" first.', 'yellow');
            exit(1);
        }

        e($authInfo);
    }
}
