<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

class Branch extends Base
{
    public const DEFAULT_METHOD = 'list';

    public const AVAILABLE_COMMANDS = [
        'list' => 'list, l',
        'user' => 'user, u',
        'name' => 'name, n',
    ];

    public function user($user)
    {
        $this->list($user);
    }

    public function name($branchName)
    {
        $this->list(null, $branchName);
    }

    public function list($user = null, $branch = null, $page = 1, $return = false)
    {
        $result = [];

        $response = $this->makeRequest('GET', "/refs/branches?page={$page}");

        foreach ($response['values'] as $branchInfo) {
            $branchName = $branchInfo['name'];
            $branchOwner = array_get($branchInfo, 'target.author.user.display_name') ?:
                array_get($branchInfo, 'target.author.raw');

            // if user is specified, only show branches that belong to that user
            if ($user && strpos(mb_strtolower($branchOwner), mb_strtolower($user)) === false) {
                continue;
            }

            // if branch is specified, only show that branch
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

        e($result, 'yellow');
    }
}
