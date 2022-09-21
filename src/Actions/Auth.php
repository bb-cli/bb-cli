<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

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
        o('This action requires app password:', 'yellow');
        o('If you don\'t have a app password you may create by following this link:', 'yellow');
        o('https://support.atlassian.com/bitbucket-cloud/docs/app-passwords/', 'green');

        $username = getUserInput('Username: ');
        $appPassword = getUserInput('App password: ');

        $saveToFile = userConfig([
            'auth' => [
                'username' => $username,
                'appPassword' => $appPassword,
            ],
        ]);

        if ($saveToFile !== false) {
            o('Auth info saved.', 'green');
        } else {
            o('Cannot save file to: '.config('userConfigFilePath'), 'red');
        }
    }

    public function show()
    {
        $authInfo = userConfig('auth');

        if (!$authInfo) {
            o('You have to configure auth info to use this command.', 'red');
            o('Run "bb auth" first.', 'yellow');
            exit(1);
        }

        o($authInfo);
    }
}
