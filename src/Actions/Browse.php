<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Browse Bitbucket Page
 *
 * @see https://bb-cli.github.io/docs/commands/browse
 */
class Browse extends Base
{
    /**
     * Browse default command.
     */
    const DEFAULT_METHOD = 'browse';

    /**
     * Browse commands.
     */
    const AVAILABLE_COMMANDS = [
        'browse' => 'browse, b',
        'show' => 'show, url',
    ];

    /**
     * Open browser for current repository
     *
     * @return void
     * @throws \Exception
     */
    public function browse()
    {
        $bitbucketUrl = 'https://bitbucket.org/'.getRepoPath();

        $openBrowserCommand = '';
        if (stripos(PHP_OS, 'win') === 0) {
            $openBrowserCommand = 'start';
        } elseif (stripos(PHP_OS, 'darwin') === 0) {
            $openBrowserCommand = 'open';
        } elseif (stripos(PHP_OS, 'linux') === 0) {
            $openBrowserCommand = 'xdg-open';
        }

        o($bitbucketUrl);
        if (!$openBrowserCommand) {
            o('Cannot get operation system info', 'red');
            exit(1);
        }

        exec(sprintf('%s %s', $openBrowserCommand, $bitbucketUrl));
    }

    /**
     * Prints repository url
     *
     * @return void
     * @throws \Exception
     */
    public function show()
    {
        o('https://bitbucket.org/'.getRepoPath());
    }
}
