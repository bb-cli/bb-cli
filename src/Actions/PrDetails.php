<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Pull Request Details - View PR comments and related information
 *
 * @see https://bb-cli.github.io/docs/commands/pull-request
 */
class PrDetails extends Base
{
    /**
     * Pull request details default command.
     */
    const DEFAULT_METHOD = 'show';

    /**
     * Pull request details commands.
     */
    const AVAILABLE_COMMANDS = [
        'show' => 'show',
    ];

    /**
     * List pull request general and inline comments.
     *
     * Fetches all comments and displays them chronologically.
     *
     * @param int $prId
     * @param bool $unresolved
     * @return void
     */
    public function show($prId = null, $unresolved = false)
    {
        if (is_null($prId)) {
            throw new \Exception('PR ID required. Usage: bb pr show <pr_id> [unresolved]');
        }

        if (is_string($unresolved)) {
            $unresolved = filter_var($unresolved, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if (!is_bool($unresolved)) {
            throw new \Exception('Invalid unresolved value. Usage: bb pr show <pr_id> [unresolved]');
        }

        $this->displayComments($prId, $unresolved);
    }

    /**
     * Display pull request comments.
     *
     * @param int $prId
     * @param bool $unresolved
     * @return void
     */
    private function displayComments($prId, $unresolved)
    {
        $commentsData = $this->fetchAllComments($prId, $unresolved);
        $comments = $commentsData['general'];
        $inlineComments = $commentsData['inline'];

        o("## Pull Request Comments (PR #{$prId})", 'green');
        o('');

        // General comments section
        o("### General Comments", 'yellow');
        if (empty($comments)) {
            o('No general comments found.', 'cyan');
        } else {
            foreach ($comments as $comment) {
                $formatted = $this->formatGeneralComment($comment);
                o("{$formatted['author']} ({$formatted['timestamp']}):", 'green');
                o($formatted['content']);
                o('');
            }
        }

        // Inline comments section
        o("### Inline Code Comments", 'yellow');
        if (empty($inlineComments)) {
            o('No inline comments found.', 'cyan');
        } else {
            foreach ($inlineComments as $comment) {
                $formatted = $this->formatInlineComment($comment);
                o("File: {$formatted['file']}:{$formatted['line']}", 'cyan');
                o("{$formatted['author']} ({$formatted['timestamp']}):", 'green');
                o($formatted['content']);
                o('');
            }
        }
    }

    /**
     * Fetch all comments for a pull request.
     *
     * Fetches all pages of comments and sorts them chronologically.
     * Applies unresolved filter only to inline comments (general comments are never resolvable).
     *
     * @param int $prId
     * @param bool $unresolved
     * @return array
     */
    private function fetchAllComments($prId, $unresolved = false)
    {
        $general = [];
        $inline = [];
        $page = 1;
        $pagelen = 100; // API max for efficiency

        // Fetch all pages until no more
        while ($page <= 100) { // Safety limit: max 100 pages = 10,000 comments
            $response = $this->makeRequest(
                'GET',
                "/pullrequests/{$prId}/comments?pagelen={$pagelen}&page={$page}"
            );

            foreach ($response['values'] ?? [] as $comment) {
                // Partition by inline path presence
                if (empty(array_get($comment, 'inline.path'))) {
                    $general[] = $comment;
                } else {
                    $inline[] = $comment;
                }
            }

            // Stop if no more pages
            if (empty($response['next'])) {
                break;
            }

            $page++;
        }

        // Sort both arrays chronologically by created_on
        usort($general, function ($a, $b) {
            return strcmp($a['created_on'], $b['created_on']);
        });
        usort($inline, function ($a, $b) {
            return strcmp($a['created_on'], $b['created_on']);
        });

        // Apply unresolved filter if requested
        // Per Bitbucket API: only inline comments are resolvable
        // General comments (no inline field) are never resolvable and always shown
        if ($unresolved) {
            $inline = array_values(array_filter($inline, [$this, 'isUnresolvedComment']));
            // General comments remain unchanged - they are never resolvable
        }

        return [
            'general' => $general,
            'inline' => $inline,
        ];
    }

    /**
     * Check if a comment is unresolved.
     *
     * @param array $comment
     * @return bool
     */
    private function isUnresolvedComment($comment)
    {
        // Per Bitbucket API: if 'resolution' key exists (even as empty object {}),
        // the comment is resolved. No resolution key means unresolved.
        return !array_key_exists('resolution', $comment);
    }

    /**
     * Format a general comment for display.
     *
     * @param array $comment
     * @return array
     */
    private function formatGeneralComment($comment)
    {
        if (array_get($comment, 'deleted')) {
            return [
                'author' => array_get($comment, 'user.display_name', 'Unknown'),
                'timestamp' => format_relative_timestamp(array_get($comment, 'created_on')),
                'content' => '[DELETED]',
                'uuid' => array_get($comment, 'user.uuid'),
            ];
        }

        return [
            'author' => array_get($comment, 'user.display_name'),
            'timestamp' => format_relative_timestamp(array_get($comment, 'created_on')),
            'content' => array_get($comment, 'content.raw', ''),
            'uuid' => array_get($comment, 'user.uuid'),
        ];
    }

    /**
     * Format an inline comment for display.
     *
     * @param array $comment
     * @return array
     */
    private function formatInlineComment($comment)
    {
        $line = array_get($comment, 'inline.to');
        if (is_null($line)) {
            $line = array_get($comment, 'inline.from');
        }

        // Handle deleted inline comments
        if (array_get($comment, 'deleted')) {
            return [
                'author' => array_get($comment, 'user.display_name', 'Unknown'),
                'timestamp' => format_relative_timestamp(array_get($comment, 'created_on')),
                'content' => '[DELETED]',
                'file' => array_get($comment, 'inline.path', ''),
                'line' => $line,
            ];
        }

        return [
            'author' => array_get($comment, 'user.display_name'),
            'timestamp' => format_relative_timestamp(array_get($comment, 'created_on')),
            'content' => array_get($comment, 'content.raw', ''),
            'file' => array_get($comment, 'inline.path', ''),
            'line' => $line,
        ];
    }
}
