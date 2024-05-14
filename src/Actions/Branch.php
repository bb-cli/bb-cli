<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Branch
 * All commands for branch.
 *
 * @see https://bb-cli.github.io/docs/commands/branch
 */
class Branch extends Base
{
    /**
     * Branch default command.
     */
    public const DEFAULT_METHOD = 'list';

    /**
     * Branch commands.
     */
    public const AVAILABLE_COMMANDS = [
        'list' => 'list, l',
        'user' => 'user, u',
        'name' => 'name, n',
    ];

    /**
     * List branches that latest commit username contains "xyz".
     *
     * @param  string $user
     * @return void
     */
    public function user($user)
    {
        $this->list($user);
    }

    /**
     * List branches that branch name contains "feature".
     *
     * @param  string $branchName
     * @return void
     */
    public function name($branchName)
    {
        $this->list(null, $branchName);
    }

    /**
     * Lists branchs repo.
     *
     * @param  string $user
     * @param  string $branch
     * @param  int    $page
     * @param  bool   $return
     * @return array|void
     * @throws \Exception
     */
    public function list($user = null, $branch = null, $page = 1, $return = false)
    {
        $result = [];

        $response = $this->makeRequest('GET', "/refs/branches?page={$page}");

        foreach ($response['values'] as $branchInfo) {
            $branchName = $branchInfo['name'];
            $branchOwner = array_get($branchInfo, 'target.author.user.display_name') ?:
                array_get($branchInfo, 'target.author.raw');

            if ($user && strpos(mb_strtolower($branchOwner), mb_strtolower($user)) === false) {
                continue;
            }

            if ($branch && strpos(mb_strtolower($branchName), mb_strtolower($branch)) === false) {
                continue;
            }

            $result[] = [
                'branch' => $branchName,
                'user' => $branchOwner,
                'updated' => date_create(array_get($branchInfo, 'target.date'))->format('Y-m-d H:i'),
            ];
        }

        if (isset($response['next']) && !empty($response['next'])) {
            $result = array_merge($result, $this->list($user, $branch, $page + 1, true) ?: []);
        }

        if ($return) {
            return $result;
        }

        o($result, 'yellow');
    }
}
