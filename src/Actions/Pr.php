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
    const DEFAULT_METHOD = 'list';

    /**
     * Pull request commands.
     */
    const AVAILABLE_COMMANDS = [
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
     * @param  string  $destination
     * @return void
     */
    public function list($destination = '')
    {
        $result = [];

        foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN')['values'] as $prInfo) {
            if (! empty($destination) &&
                array_get($prInfo, 'destination.branch.name') !== $destination
            ) {
                continue;
            }

            $prDetail = $this->makeRequest('GET', "/pullrequests/{$prInfo['id']}");

            $result[] = [
                'id' => $prInfo['id'],
                'author' => array_get($prInfo, 'author.nickname'),
                'source' => array_get($prInfo, 'source.branch.name'),
                'destination' => array_get($prInfo, 'destination.branch.name'),
                'link' => array_get($prInfo, 'links.html.href'),
                'reviewers' => implode(
                    ', ',
                    array_map(function ($reviewer) {
                        return $reviewer['display_name'];
                    }, $prDetail['reviewers'])
                ),
                'participants' => implode(
                    ' | ',
                    array_filter(
                        array_map(function ($participant) {
                            return $participant['state'] ? sprintf(
                                '%s -> %s',
                                $participant['user']['display_name'],
                                $participant['state']
                            ) : null;
                        }, $prDetail['participants'])
                    )
                ),
            ];
        }

        o($result, 'yellow');
    }

    /**
     * Get pull request diff.
     *
     * @param  int  $prNumber
     * @return void
     */
    public function diff($prNumber)
    {
        o($this->makeRequest('GET', "/pullrequests/{$prNumber}/diff"), 'yellow');
    }

    /**
     * Diff stats file.
     *
     * @param  int  $prNumber
     * @return void
     *
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
     * @return void
     *
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
     * @param  array  $prNumbers
     * @return void
     *
     * @throws \Exception
     */
    public function approve(...$prNumbers)
    {
        if (empty($prNumbers)) {
            throw new \Exception('Pr number required.', 1);
        }

        // if first param is zero than approve all
        if ($prNumbers[0] == 0) {
            $prNumbers = [];

            foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN')['values'] as $prInfo) {
                $prNumbers[] = $prInfo['id'];
            }

            if (empty($prNumbers)) {
                throw new \Exception('Pr not found.', 1);
            }
        }

        foreach ($prNumbers as $prNumber) {
            $this->makeRequest('POST', "/pullrequests/{$prNumber}/approve");
            o("{$prNumber} Approved.", 'green');
        }
    }

    /**
     * Revert pull request to not approved status.
     *
     * @param  int  $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unApprove($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/approve"));
    }

    /**
     *  Request changes for pull request
     *
     * @param  int  $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function requestChanges($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Revert pull request to not request changes status.
     *
     * @param  int  $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unRequestChanges($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Decline pull request.
     *
     * @param  int  $prNumber
     * @return void
     *
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
     * @param  int  $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function merge($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/merge")['state'], 'green');
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param  string  $fromBranch
     * @param  string  $toBranch
     * @param  int  $addDefaultReviewers
     * @return void
     *
     * @throws \Exception
     */
    public function create($fromBranch, $toBranch = '', $addDefaultReviewers = 1)
    {
        if (empty($toBranch)) {
            $toBranch = $fromBranch;
            $fromBranch = trim(exec('git symbolic-ref --short HEAD'));
        }

        $toBranches = (strpos($toBranch, ',') !== false) ? explode(',', $toBranch) : [$toBranch];
        $this->bulkCreate($toBranches, $fromBranch, $addDefaultReviewers);
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param  array  $toBranch
     * @param  int  $addDefaultReviewers
     * @return void
     *
     * @throws \Exception
     */
    protected function bulkCreate(array $toBranches, string $fromBranch, $addDefaultReviewers = 1)
    {
        $responses = [];

        foreach ($toBranches as $toBranch) {
            $response = $this->makeRequest('POST', '/pullrequests', [
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
                'reviewers' => $addDefaultReviewers ? $this->defaultReviewers() : [],
            ]);
            $responses[] = [
                'id' => array_get($response, 'id'),
                'link' => array_get($response, 'links.html.href'),
            ];
        }

        o([
            'pullRequests' => $responses,
        ]);
    }

    /**
     * Get default reviewers for repository.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function defaultReviewers()
    {
        $currentUserUuid = $this->currentUserUuid();
        $response = $this->makeRequest('GET', '/default-reviewers');

        // remove current user from reviewers
        return array_values(array_filter($response['values'] ?? [], function ($reviewer) use ($currentUserUuid) {
            return $reviewer['uuid'] !== $currentUserUuid;
        }));
    }

    /**
     * Get current user uuid.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function currentUserUuid()
    {
        $response = $this->makeRequest(
            'GET',
            '/user',
            [],
            false
        );

        return array_get($response, 'uuid');
    }
}
