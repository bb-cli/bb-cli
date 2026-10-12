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
        'edit' => 'edit, e',
        'ready' => 'ready',
        'show' => 'show',
        'comment' => 'comment',
    ];

    const ACTION_DESCRIPTION = 'Manage pull requests';

    const COMMAND_DETAILS = [
        'list'             => ['args' => '[<destination>]',       'description' => 'List open pull requests'],
        'diff'             => ['args' => '<pr>',                  'description' => 'Show diff for a pull request'],
        'files'            => ['args' => '<pr>',                  'description' => 'List files changed in a pull request'],
        'commits'          => ['args' => '<pr>',                  'description' => 'List commits in a pull request'],
        'approve'          => ['args' => '<pr> [<pr>...]',        'description' => 'Approve one or more pull requests'],
        'unApprove'        => ['args' => '<pr>',                  'description' => 'Remove your approval from a pull request'],
        'requestChanges'   => ['args' => '<pr>',                  'description' => 'Request changes on a pull request'],
        'unRequestChanges' => ['args' => '<pr>',                  'description' => 'Remove your request-changes from a pull request'],
        'decline'          => ['args' => '<pr>',                  'description' => 'Decline a pull request'],
        'merge'            => ['args' => '<pr>',                  'description' => 'Merge a pull request'],
        'create'           => ['args' => '<from> [<to>]',         'description' => 'Create a pull request (default reviewers unless --reviewers given, --draft for a draft)'],
        'edit'             => ['args' => '<pr>',                  'description' => 'Edit title, description, destination, or reviewers of a pull request'],
        'ready'            => ['args' => '<pr>',                  'description' => 'Mark a draft pull request as ready for review'],
        'show'             => ['args' => '[<pr>]',                'description' => 'Show pull request details and comments'],
        'comment'          => ['args' => '<pr> <message>',        'description' => 'Comment on a pull request (--file/--line for inline, --reply-to <id> to reply, "-" reads stdin)'],
    ];

    /**
     * List pull request for repository.
     *
     * @param string $destination
     * @return void
     */
    public function list($destination = '')
    {
        $result = [];

        foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN', [], true, 'listing pull requests')['values'] as $prInfo) {
            if (!empty($destination) &&
                array_get($prInfo, 'destination.branch.name') !== $destination
            ) {
                continue;
            }

            $prDetail = $this->makeRequest('GET', "/pullrequests/{$prInfo['id']}", [], true, 'fetching pull request details');

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
     * @param int $prNumber
     * @return void
     */
    public function diff($prNumber)
    {
        o($this->makeRequest('GET', "/pullrequests/{$prNumber}/diff", [], true, 'fetching pull request diff'), 'yellow');
    }

    /**
     * Diff stats file.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function files($prNumber)
    {
        $response = array_get($this->makeRequest('GET', "/pullrequests/{$prNumber}/diffstat", [], true, 'fetching pull request files'), 'values');

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

        foreach ($this->makeRequest('GET', "/pullrequests/{$prNumber}/commits", [], true, 'fetching pull request commits')['values'] as $prInfo) {
            $result[] = trim(str_replace('\n', PHP_EOL, array_get($prInfo, 'summary.raw')));
        }

        o($result, 'yellow');
    }

    /**
     * Approve pull request.
     *
     * @param array $prNumbers
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

            foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN', [], true, 'listing pull requests')['values'] as $prInfo) {
                $prNumbers[] = $prInfo['id'];
            }

            if (empty($prNumbers)) {
                throw new \Exception('Pr not found.', 1);
            }
        }

        foreach ($prNumbers as $prNumber) {
            $this->makeRequest('POST', "/pullrequests/{$prNumber}/approve", [], true, 'approving pull request');
            o("{$prNumber} Approved.", 'green');
        }
    }

    /**
     * Revert pull request to not approved status.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unApprove($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/approve", [], true, 'removing pull request approval'));
    }

    /**
     *  Request changes for pull request
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function requestChanges($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/request-changes", [], true, 'requesting changes on pull request'));
    }

    /**
     * Revert pull request to not request changes status.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unRequestChanges($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/request-changes", [], true, 'removing pull request change request'));
    }

    /**
     * Decline pull request.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function decline($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/decline", [], true, 'declining pull request');
        o('OK.', 'green');
    }

    /**
     * Merge pull request.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function merge($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/merge", [], true, 'merging pull request')['state'], 'green');
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * Reviewers come from --reviewers when supplied, otherwise from the
     * repository's effective default reviewers. Pass --draft to open the
     * pull request as a draft.
     *
     * @param string $fromBranch
     * @param string $toBranch
     * @param int $addDefaultReviewers
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

        $interactive = !empty($GLOBALS['bb_cli_interactive']);
        $title = $GLOBALS['bb_cli_pr_title'] ?? null;
        $description = $GLOBALS['bb_cli_pr_description'] ?? null;
        $reviewers = $GLOBALS['bb_cli_pr_reviewers'] ?? null;
        $draft = !empty($GLOBALS['bb_cli_pr_draft']);

        if ($interactive) {
            if (!$title) {
                $title = getUserInput('PR title (leave empty for default):') ?: null;
            }
            if (!$description) {
                $description = getUserInput('PR description (leave empty to skip):') ?: null;
            }
            if (!$reviewers) {
                $reviewers = getUserInput('PR reviewers, comma separated (leave empty for default reviewers):') ?: null;
            }
        }

        $this->bulkCreate(
            explode(',', $toBranch),
            $fromBranch,
            $addDefaultReviewers == 1,
            $title,
            $description,
            $reviewers,
            $draft
        );
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param array $toBranches
     * @param string $fromBranch
     * @param bool $addDefaultReviewers
     * @param string|null $title
     * @param string|null $description
     * @param string|null $reviewers Comma-separated nicknames and/or UUIDs.
     * @param bool $draft Open the pull request as a draft.
     * @return void
     *
     * @throws \Exception
     */
    private function bulkCreate($toBranches, $fromBranch, $addDefaultReviewers = true, $title = null, $description = null, $reviewers = null, $draft = false)
    {
        $responses = [];

        if (!is_null($reviewers)) {
            $prReviewers = $this->resolveReviewers($reviewers);
        } else {
            $prReviewers = $addDefaultReviewers ? $this->defaultReviewers() : [];
        }

        foreach ($toBranches as $toBranch) {
            $payload = [
                'title' => $title ?? "Merge {$fromBranch} into {$toBranch}",
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
                'reviewers' => $prReviewers,
            ];

            if ($description) {
                $payload['description'] = $description;
            }

            if ($draft) {
                $payload['draft'] = true;
            }

            $response = $this->makeRequest('POST', '/pullrequests', $payload, true, 'creating pull request');

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
     * Get the effective default reviewers for the repository.
     *
     * Uses /effective-default-reviewers so that reviewers inherited from the
     * repository's project are included alongside repository-level ones. That
     * endpoint wraps each account in a `user` key, unlike /default-reviewers,
     * which returns bare account objects. If it is unavailable, fall back to
     * the repository-only endpoint.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function defaultReviewers()
    {
        $currentUserUuid = $this->currentUserUuid();

        try {
            $response = $this->makeRequest('GET', '/effective-default-reviewers', [], true, 'fetching default reviewers');
            $reviewers = array_map(function ($reviewer) {
                return array_get($reviewer, 'user', []);
            }, $response['values'] ?? []);
        } catch (\Exception $e) {
            $response = $this->makeRequest('GET', '/default-reviewers', [], true, 'fetching default reviewers');
            $reviewers = $response['values'] ?? [];
        }

        // remove current user from reviewers
        return array_values(array_filter($reviewers, function ($reviewer) use ($currentUserUuid) {
            return !empty($reviewer['uuid']) && $reviewer['uuid'] !== $currentUserUuid;
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
            false,
            'fetching current user'
        );

        return array_get($response, 'uuid');
    }

    /**
     * Resolve reviewer identifiers (nicknames or UUIDs) to Bitbucket account UUIDs.
     *
     * Entries that already look like a UUID (braced or unbraced) are used
     * as-is with no API call. Everything else is treated as a nickname and
     * resolved via an exact-match lookup against the repository's
     * workspace members.
     *
     * @param string $namesCsv
     * @return array
     *
     * @throws \Exception
     */
    private function resolveReviewers($namesCsv)
    {
        $uuidPattern = '/^\{?[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\}?$/i';
        $reviewers = [];

        foreach (explode(',', $namesCsv) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (preg_match($uuidPattern, $entry)) {
                $reviewers[] = ['uuid' => '{'.trim($entry, '{}').'}'];
                continue;
            }

            // Bitbucket deprecated nickname lookups via GET /users/{username} in April 2019
            // (GDPR). Resolve nicknames via the workspace members endpoint instead, which
            // supports an exact-match filter on user.nickname.
            $workspace = explode('/', getRepoPath())[0];
            $query = rawurlencode("user.nickname=\"{$entry}\"");

            try {
                $response = $this->makeRequest('GET', "/workspaces/{$workspace}/members?q={$query}", [], false, "resolving reviewer '{$entry}'");
            } catch (\Exception $e) {
                throw new \Exception("Could not resolve reviewer '{$entry}': {$e->getMessage()}", 1);
            }

            $uuid = array_get($response, 'values.0.user.uuid');

            if (empty($uuid)) {
                throw new \Exception("Could not resolve reviewer '{$entry}': no matching workspace member found.", 1);
            }

            $reviewers[] = ['uuid' => $uuid];
        }

        return $reviewers;
    }

    /**
     * Edit an existing pull request's title, description, destination, or reviewers.
     *
     * Only fields supplied via --title/--description/--destination/--reviewers
     * are changed; Bitbucket's PUT /pullrequests/{id} leaves omitted fields
     * untouched.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function edit($prNumber)
    {
        $title = $GLOBALS['bb_cli_pr_title'] ?? null;
        $description = $GLOBALS['bb_cli_pr_description'] ?? null;
        $destination = $GLOBALS['bb_cli_pr_destination'] ?? null;
        $reviewers = $GLOBALS['bb_cli_pr_reviewers'] ?? null;

        if (!empty($GLOBALS['bb_cli_interactive'])) {
            list($title, $description, $destination, $reviewers) = $this->promptForEditFields(
                $prNumber,
                $title,
                $description,
                $destination,
                $reviewers
            );
        }

        $payload = $this->buildEditPayload($title, $description, $destination, $reviewers);

        if (empty($payload)) {
            throw new \Exception('No changes provided. Use --title, --description, --destination, --reviewers, or -i.', 1);
        }

        $response = $this->makeRequest('PUT', "/pullrequests/{$prNumber}", $payload, true, 'updating pull request');

        o([
            'id' => array_get($response, 'id'),
            'title' => array_get($response, 'title'),
            'destination' => array_get($response, 'destination.branch.name'),
            'link' => array_get($response, 'links.html.href'),
        ], 'green');
    }

    /**
     * Mark a draft pull request as ready for review.
     *
     * Clearing the `draft` flag is an ordinary PUT /pullrequests/{id} update,
     * which leaves every omitted field untouched. Running it against a pull
     * request that is already ready is a harmless no-op.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function ready($prNumber)
    {
        $response = $this->makeRequest(
            'PUT',
            "/pullrequests/{$prNumber}",
            ['draft' => false],
            true,
            'marking pull request ready for review'
        );

        o([
            'id' => array_get($response, 'id'),
            'title' => array_get($response, 'title'),
            'draft' => array_get($response, 'draft') ? 'yes' : 'no',
            'link' => array_get($response, 'links.html.href'),
        ], 'green');
    }

    /**
     * Build the PUT payload for pr edit from provided (non-null) fields only.
     *
     * @param string|null $title
     * @param string|null $description
     * @param string|null $destination
     * @param string|null $reviewers Comma-separated nicknames and/or UUIDs.
     * @return array
     *
     * @throws \Exception
     */
    private function buildEditPayload($title, $description, $destination, $reviewers)
    {
        $payload = [];

        if (!is_null($title)) {
            $payload['title'] = $title;
        }
        if (!is_null($description)) {
            $payload['description'] = $description;
        }
        if (!is_null($destination)) {
            $payload['destination'] = ['branch' => ['name' => $destination]];
        }
        if (!is_null($reviewers)) {
            $payload['reviewers'] = $this->resolveReviewers($reviewers);
        }

        return $payload;
    }

    /**
     * Prompt for any pr edit field not already supplied via flags.
     *
     * Fetches the current pull request so prompts can show existing values.
     * A blank answer leaves that field unset (unchanged).
     *
     * @param int $prNumber
     * @param string|null $title
     * @param string|null $description
     * @param string|null $destination
     * @param string|null $reviewers
     * @return array [$title, $description, $destination, $reviewers]
     *
     * @throws \Exception
     */
    private function promptForEditFields($prNumber, $title, $description, $destination, $reviewers)
    {
        if (!is_null($title) && !is_null($description) && !is_null($destination) && !is_null($reviewers)) {
            return [$title, $description, $destination, $reviewers];
        }

        $current = $this->makeRequest('GET', "/pullrequests/{$prNumber}", [], true, 'fetching pull request details');

        if (is_null($title)) {
            $currentTitle = array_get($current, 'title', '');
            $title = getUserInput("New title (current: \"{$currentTitle}\"), leave empty to keep:") ?: null;
        }

        if (is_null($description)) {
            $description = getUserInput('New description, leave empty to keep:') ?: null;
        }

        if (is_null($destination)) {
            $currentDestination = array_get($current, 'destination.branch.name', '');
            $destination = getUserInput("New destination branch (current: \"{$currentDestination}\"), leave empty to keep:") ?: null;
        }

        if (is_null($reviewers)) {
            $currentReviewers = implode(', ', array_map(function ($reviewer) {
                return array_get($reviewer, 'nickname') ?: array_get($reviewer, 'display_name', '');
            }, array_get($current, 'reviewers', [])));
            $reviewers = getUserInput("New reviewers (current: {$currentReviewers}), comma separated, leave empty to keep:") ?: null;
        }

        return [$title, $description, $destination, $reviewers];
    }

    /**
     * List pull request general and inline comments.
     *
     * Delegates to PrDetails action class to keep Pr focused on lifecycle operations.
     *
     * @param int $prId
     * @param bool $unresolved
     * @return void
     */
    public function show($prId = null, $unresolved = false)
    {
        (new PrDetails())->show($prId, $unresolved);
    }

    /**
     * Comment on a pull request.
     *
     * The message is every argument after the PR number joined with spaces,
     * so it needn't be quoted; a lone "-" reads it from stdin instead. The
     * comment is general unless --file and --line place it inline on a line
     * of the new version of a file, or --reply-to puts it in the thread of an
     * existing comment (replies inherit their parent's placement).
     *
     * @param int $prNumber
     * @param string ...$messageParts
     * @return void
     *
     * @throws \Exception
     */
    public function comment($prNumber, ...$messageParts)
    {
        $message = implode(' ', $messageParts);
        if ($message === '-') {
            $message = $this->readStdin();
        }

        if (trim($message) === '') {
            throw new \Exception('Comment message required. Usage: bb pr comment <pr> <message>', 1);
        }

        $payload = array_merge(
            ['content' => ['raw' => $message]],
            $this->commentPlacement(
                $GLOBALS['bb_cli_pr_comment_file'] ?? null,
                $GLOBALS['bb_cli_pr_comment_line'] ?? null,
                $GLOBALS['bb_cli_pr_comment_reply_to'] ?? null
            )
        );

        $response = $this->makeRequest(
            'POST',
            "/pullrequests/{$prNumber}/comments",
            $payload,
            true,
            'adding comment to pull request'
        );

        o([
            'id' => array_get($response, 'id'),
            'link' => array_get($response, 'links.html.href'),
        ], 'green');
    }

    /**
     * Build the inline/parent part of a comment payload from --file, --line
     * and --reply-to.
     *
     * @param string|null $file
     * @param string|null $line
     * @param string|null $replyTo
     * @return array
     *
     * @throws \Exception
     */
    private function commentPlacement($file, $line, $replyTo)
    {
        if (!is_null($replyTo)) {
            if (!is_null($file) || !is_null($line)) {
                throw new \Exception('--reply-to can\'t be combined with --file or --line: replies go in their parent\'s thread.', 1);
            }

            return ['parent' => ['id' => $this->positiveInteger($replyTo, '--reply-to')]];
        }

        if (is_null($file) && is_null($line)) {
            return [];
        }

        if (is_null($file) || is_null($line)) {
            throw new \Exception('Inline comments need both --file and --line.', 1);
        }

        return ['inline' => ['path' => $file, 'to' => $this->positiveInteger($line, '--line')]];
    }

    /**
     * @param string $value
     * @param string $flag
     * @return int
     *
     * @throws \Exception
     */
    private function positiveInteger($value, $flag)
    {
        if (!ctype_digit((string) $value) || (int) $value < 1) {
            throw new \Exception("{$flag} must be a positive whole number, got \"{$value}\".", 1);
        }

        return (int) $value;
    }

    /**
     * Read a comment message piped to stdin.
     *
     * @return string
     */
    protected function readStdin()
    {
        return (string) stream_get_contents(STDIN);
    }
}
