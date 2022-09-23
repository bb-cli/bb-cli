<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Pull Request
 * All commands for pull request.
 *
 * @see https://bb-cli.github.io/docs/commands/pull-request
 */
class Pr extends Base
{
    /**
     * Pull request default command.
     */
    public const DEFAULT_METHOD = 'list';

    /**
     * Pull request commands.
     */
    public const AVAILABLE_COMMANDS = [
        'list' => 'list, l',
        'diff' => 'diff, d',
        'files' => 'files',
        'commits' => 'commits, c',
        'approve' => 'approve, a',
        'unApprove' => 'no-approve, na',
        'requestChanges' => 'request-changes, rc',
        'unRequestChanges' => 'no-request-changes, nrc',
        'decline' => 'decline',
        'merge' => 'merge, m',
        'create' => 'create',
    ];

    /**
     * List pull request for repository.
     *
     * @param  string $destination
     * @return void
     */
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

        o($result, 'yellow');
    }

    /**
     * Get pull request diff.
     *
     * @param  int $prNumber
     * @return void
     */
    public function diff($prNumber)
    {
        o($this->makeRequest('GET', "/pullrequests/{$prNumber}/diff"), 'yellow');
    }

    /**
     * Diff stats file.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function files($prNumber)
    {
        $response = array_get($this->makeRequest('GET', "/pullrequests/{$prNumber}/diffstat"), 'values');

        foreach ($response as $row) {
            o(array_get($row, 'new.path'), 'yellow');
        }
    }

    /**
     * Get pull request commits.
     *
     * @param $prNumber
     * @return void
     * @throws \Exception
     */
    public function commits($prNumber)
    {
        $result = [];

        foreach ($this->makeRequest('GET', "/pullrequests/{$prNumber}/commits")['values'] as $prInfo) {
            $result[] = trim(str_replace('\n', PHP_EOL, array_get($prInfo, 'summary.raw')));
        }

        o($result, 'yellow');
    }

    /**
     * Approve pull request.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function approve($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/approve");
        o('Approved.', 'green');
    }

    /**
     * Revert pull request to not approved status.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function unApprove($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/approve"));
    }

    /**
     *  Request changes for pull reques
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function requestChanges($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Revert pull request to not request changes status.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function unRequestChanges($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Decline pull request.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function decline($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/decline");
        o('OK.', 'green');
    }

    /**
     * Merge pull request.
     *
     * @param  int $prNumber
     * @return void
     * @throws \Exception
     */
    public function merge($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/merge")['state'], 'green');
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param  string $fromBranch
     * @param  string $toBranch
     * @return void
     * @throws \Exception
     */
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

        o([
            'id' => array_get($response, 'id'),
            'link' => array_get($response, 'links.html.href'),
        ]);
    }
}
