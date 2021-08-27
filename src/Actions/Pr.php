<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

class Pr extends Base
{
    public const DEFAULT_METHOD = 'list';

    public const AVAILABLE_COMMANDS = [
        'list' => 'list, l',
        'diff' => 'diff, d',
        'commits' => 'commits, c',
        'approve' => 'approve, a',
        'unApprove' => 'no-approve, na',
        'requestChanges' => 'request-changes, rc',
        'unRequestChanges' => 'no-request-changes, nrc',
        'decline' => 'decline',
        'merge' => 'merge, m',
        'create' => 'create',
    ];

    public function list($destination = '')
    {
        $result = [];

        foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN')['values'] as $prInfo) {
            if (!empty($destination) &&
                array_get($prInfo, 'destination.branch.name') !== $destination
            ) {
                continue;
            }

            $result[] = [
                'id' => $prInfo['id'],
                'author' => array_get($prInfo, 'author.nickname'),
                'source' => array_get($prInfo, 'source.branch.name'),
                'destination' => array_get($prInfo, 'destination.branch.name'),
                'link' => array_get($prInfo, 'links.html.href'),
            ];
        }

        e($result, 'yellow');
    }

    public function diff($prNumber)
    {
        e($this->makeRequest('GET', "/pullrequests/{$prNumber}/diff"), 'yellow');
    }

    public function commits($prNumber)
    {
        $result = [];

        foreach ($this->makeRequest('GET', "/pullrequests/{$prNumber}/commits")['values'] as $prInfo) {
            $result[] = trim(str_replace('\n', PHP_EOL, array_get($prInfo, 'summary.raw')));
        }

        e($result, 'yellow');
    }

    public function approve($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/approve");
        e('Approved.', 'green');
    }

    public function unApprove($prNumber)
    {
        e($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/approve"));
    }

    public function requestChanges($prNumber)
    {
        e($this->makeRequest('POST', "/pullrequests/{$prNumber}/request-changes"));
    }

    public function unRequestChanges($prNumber)
    {
        e($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/request-changes"));
    }

    public function decline($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/decline");
        e('OK.', 'green');
    }

    public function merge($prNumber)
    {
        e($this->makeRequest('POST', "/pullrequests/{$prNumber}/merge")['state'], 'green');
    }

    public function create($fromBranch, $toBranch = '')
    {
        if (empty($toBranch)) {
            $toBranch = $fromBranch;
            $fromBranch = trim(exec('git symbolic-ref --short HEAD'));
        }

        $response = $this->makeRequest('POST', "/pullrequests", [
            'title' => "Merge {$fromBranch} into {$toBranch}",
            'source' => [
                'branch' => [
                    'name' => $fromBranch,
                ],
            ],
            'destination' => [
                'branch' => [
                    'name' => $toBranch,
                ],
            ],
        ]);

        e([
            'id' => array_get($response, 'id'),
            'link' => array_get($response, 'links.html.href'),
        ]);
    }
}
