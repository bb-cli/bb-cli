<?php

namespace SemihErdogan\BitbucketRestCli;

class Auth extends Base
{
    public const DEFAULT_METHOD = 'saveLoginInfo';
    public const CHECK_GIT_FOLDER = false;

    public const AVAILABLE_COMMANDS = [
        'saveLoginInfo' => 'save',
    ];

    public function saveLoginInfo()
    {
        e('This action requires app password:', 'yellow');
        e('If you don\'t have a app password you may create by following this link:', 'yellow');
        e('https://support.atlassian.com/bitbucket-cloud/docs/app-passwords/', 'green');

        $username = getUserInput('Username: ');
        $appPassword = getUserInput('App password: ');

        if ($this->saveToFile($username, $appPassword) !== false) {
            e('Auth info saved.', 'green');
        } else {
            e('Cannot save file to: '.$this->configFilePath, 'red');
        }
    }

    private function saveToFile($username, $appPassword)
    {
        $json = json_encode([
            'auth' => [
                'username' => $username,
                'appPassword' => $appPassword,
            ],
        ], JSON_PRETTY_PRINT);

        return file_put_contents($this->configFilePath, $json);
    }
}
