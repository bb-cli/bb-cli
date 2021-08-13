<?php

namespace BBCli\BBCli;

class Branch extends Base
{
    public const DEFAULT_METHOD = 'list';

    public const AVAILABLE_COMMANDS = [
        'list' => ', list, l',
    ];

    public function list()
    {
        $result = [];

        foreach ($this->makeRequest('GET', "/refs/branches?page=1")['values'] as $branchInfo) {
            $result[] = [
                'name' => $branchInfo['name'],
                'user' => array_get($branchInfo, 'target.author.user.display_name') ?:
                    array_get($branchInfo, 'target.author.raw'),
            ];
        }

        e($result, 'yellow');
    }
}
