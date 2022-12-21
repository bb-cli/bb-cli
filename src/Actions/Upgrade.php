<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Self Update for BB-CLI
 *
 * @see https://bb-cli.github.io/docs/upgrade
 */
class Upgrade extends Base
{
    /**
     * Upgrade default command.
     */
    public const DEFAULT_METHOD = 'index';

    /**
     * Upgrade.
     *
     * @return void
     */
    public function index()
    {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: BB-Cli Curl Agent\r\n",
                'follow_location' => true,
            ],
        ];

        $context = stream_context_create($opts);

        $repo = json_decode(
            file_get_contents(
                'https://api.github.com/repos/bb-cli/bb-cli/releases/latest',
                false,
                $context
            )
        );

        if (APP_VERSION < $repo->tag_name) {
            $runningFile = \Phar::running(false);

            o('Fething new version ('.$repo->tag_name.') ...', 'green');

            file_put_contents(
                $runningFile,
                file_get_contents(
                    sprintf('https://github.com/bb-cli/bb-cli/releases/download/%s/bb', $repo->tag_name),
                    false,
                    $context
                )
            );

            chmod($runningFile, 0755);

            o('BB-CLI Updated', 'green');
        } else {
            o('You are already on the latest version of bb-cli', 'green');
        }
    }
}
