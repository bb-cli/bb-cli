<?php

namespace BBCli\BBCli\Tests\Unit\Actions;

use BBCli\BBCli\Actions\PrDetails;
use BBCli\BBCli\Tests\Support\ActionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PrDetailsTest extends ActionTestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function comment(string $author, string $body, string $createdOn, array $overrides = []): array
    {
        return array_replace_recursive([
            'user' => ['display_name' => $author, 'uuid' => '{'.strtolower($author).'}'],
            'content' => ['raw' => $body],
            'created_on' => $createdOn,
        ], $overrides);
    }

    private function inlineComment(string $author, string $body, string $createdOn, array $inline, array $overrides = []): array
    {
        return array_replace_recursive(
            $this->comment($author, $body, $createdOn),
            ['inline' => $inline],
            $overrides
        );
    }

    public function testShowRequiresAPullRequestId(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PR ID required. Usage: bb pr show <pr_id> [unresolved]');

        (new PrDetails())->show(null);
    }

    #[DataProvider('unresolvedFlagProvider')]
    public function testAcceptsBooleanishUnresolvedArguments($flag, bool $expectFiltered): void
    {
        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => [
                'values' => [
                    $this->inlineComment('Ada', 'open', '2024-01-01T00:00:00Z', ['path' => 'a.php', 'to' => 1]),
                    $this->inlineComment('Grace', 'done', '2024-01-02T00:00:00Z', ['path' => 'b.php', 'to' => 2], [
                        'resolution' => [],
                    ]),
                ],
            ],
        ]);

        $output = $this->captureOutput(function () use ($action, $flag) {
            $action->show(7, $flag);
        });

        $this->assertStringContainsString('open', $output);
        $this->assertSame(!$expectFiltered, strpos($output, 'done') !== false);
    }

    public static function unresolvedFlagProvider(): array
    {
        return [
            'bool true' => [true, true],
            'bool false' => [false, false],
            'string true' => ['true', true],
            'string 1' => ['1', true],
            'string yes' => ['yes', true],
            'string false' => ['false', false],
            'string 0' => ['0', false],
        ];
    }

    public function testRejectsAnUnparseableUnresolvedArgument(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid unresolved value. Usage: bb pr show <pr_id> [unresolved]');

        (new PrDetails())->show(7, 'maybe');
    }

    public function testSeparatesGeneralAndInlineComments(): void
    {
        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => [
                'values' => [
                    $this->comment('Ada', 'Looks good', '2024-01-02T00:00:00Z'),
                    $this->inlineComment('Grace', 'Rename this', '2024-01-01T00:00:00Z', [
                        'path' => 'src/Base.php',
                        'to' => 42,
                    ]),
                ],
            ],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $lines = $this->lines($output);

        $this->assertSame('## Pull Request Comments (PR #7)', $lines[0]);
        $this->assertContains('### General Comments', $lines);
        $this->assertContains('### Inline Code Comments', $lines);
        $this->assertContains('Looks good', $lines);
        $this->assertContains('File: src/Base.php:42', $lines);
        $this->assertContains('Rename this', $lines);
    }

    public function testReportsWhenThereAreNoComments(): void
    {
        $action = $this->actionRouting(PrDetails::class, ['/comments' => ['values' => []]]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertContains('No general comments found.', $this->lines($output));
        $this->assertContains('No inline comments found.', $this->lines($output));
    }

    public function testSortsCommentsChronologically(): void
    {
        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => [
                'values' => [
                    $this->comment('Ada', 'third', '2024-01-03T00:00:00Z'),
                    $this->comment('Grace', 'first', '2024-01-01T00:00:00Z'),
                    $this->comment('Alan', 'second', '2024-01-02T00:00:00Z'),
                ],
            ],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertLessThan(strpos($output, 'second'), strpos($output, 'first'));
        $this->assertLessThan(strpos($output, 'third'), strpos($output, 'second'));
    }

    public function testFollowsCommentPaginationAndRequestsTheMaximumPageLength(): void
    {
        $recorded = [];
        $action = $this->actionRouting(PrDetails::class, [
            'page=1' => [
                'values' => [$this->comment('Ada', 'page one', '2024-01-01T00:00:00Z')],
                'next' => 'https://api.bitbucket.org/next',
            ],
            'page=2' => [
                'values' => [$this->comment('Ada', 'page two', '2024-01-02T00:00:00Z')],
            ],
        ], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertCount(2, $recorded);
        $this->assertSame('/pullrequests/7/comments?pagelen=100&page=1', $recorded[0]['url']);
        $this->assertSame('fetching pull request comments', $recorded[0]['label']);
        $this->assertStringContainsString('page one', $output);
        $this->assertStringContainsString('page two', $output);
    }

    public function testUnresolvedFilterNeverHidesGeneralComments(): void
    {
        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => [
                'values' => [
                    // General comments carry no resolution key and are never resolvable.
                    $this->comment('Ada', 'general note', '2024-01-01T00:00:00Z'),
                    $this->inlineComment('Grace', 'resolved inline', '2024-01-02T00:00:00Z', ['path' => 'a.php', 'to' => 1], [
                        'resolution' => ['type' => 'pullrequest_comment_resolution'],
                    ]),
                ],
            ],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7, true);
        });

        $this->assertStringContainsString('general note', $output);
        $this->assertStringNotContainsString('resolved inline', $output);
        $this->assertContains('No inline comments found.', $this->lines($output));
    }

    public function testFormatsDeletedGeneralComments(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatGeneralComment', [
            $this->comment('Ada', 'gone', '2024-01-01T00:00:00Z', ['deleted' => true]),
        ]);

        $this->assertSame('[DELETED]', $formatted['content']);
        $this->assertSame('Ada', $formatted['author']);
    }

    public function testFormatsDeletedCommentsWithNoAuthorAsUnknown(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatGeneralComment', [
            ['created_on' => '2024-01-01T00:00:00Z', 'deleted' => true],
        ]);

        $this->assertSame('Unknown', $formatted['author']);
        $this->assertSame('[DELETED]', $formatted['content']);
    }

    public function testFormatsGeneralCommentsWithoutContent(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatGeneralComment', [
            ['user' => ['display_name' => 'Ada'], 'created_on' => '2024-01-01T00:00:00Z'],
        ]);

        $this->assertSame('', $formatted['content']);
    }

    public function testInlineCommentsFallBackToTheFromLineWhenThereIsNoToLine(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatInlineComment', [
            $this->inlineComment('Ada', 'removed line', '2024-01-01T00:00:00Z', [
                'path' => 'src/Old.php',
                'from' => 17,
            ]),
        ]);

        $this->assertSame(17, $formatted['line']);
        $this->assertSame('src/Old.php', $formatted['file']);
    }

    public function testInlineCommentsPreferTheToLine(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatInlineComment', [
            $this->inlineComment('Ada', 'changed line', '2024-01-01T00:00:00Z', [
                'path' => 'src/New.php',
                'from' => 17,
                'to' => 23,
            ]),
        ]);

        $this->assertSame(23, $formatted['line']);
    }

    public function testFormatsDeletedInlineComments(): void
    {
        $formatted = $this->callPrivate(new PrDetails(), 'formatInlineComment', [
            $this->inlineComment('Ada', 'gone', '2024-01-01T00:00:00Z', ['path' => 'a.php', 'to' => 3], [
                'deleted' => true,
            ]),
        ]);

        $this->assertSame('[DELETED]', $formatted['content']);
        $this->assertSame('a.php', $formatted['file']);
        $this->assertSame(3, $formatted['line']);
    }

    public function testCommentsWithAnEmptyInlinePathCountAsGeneral(): void
    {
        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => [
                'values' => [
                    ['user' => ['display_name' => 'Ada'], 'content' => ['raw' => 'no path'],
                     'created_on' => '2024-01-01T00:00:00Z', 'inline' => ['path' => '']],
                ],
            ],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertStringContainsString('no path', $output);
        $this->assertContains('No inline comments found.', $this->lines($output));
    }

    public function testHandlesAResponseWithNoValuesKey(): void
    {
        $action = $this->actionRouting(PrDetails::class, ['/comments' => []]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertContains('No general comments found.', $this->lines($output));
    }

    public function testIsUnresolvedCommentTreatsAnyResolutionKeyAsResolved(): void
    {
        $action = new PrDetails();

        $this->assertTrue($this->callPrivate($action, 'isUnresolvedComment', [['id' => 1]]));
        $this->assertFalse($this->callPrivate($action, 'isUnresolvedComment', [['resolution' => []]]));
        $this->assertFalse($this->callPrivate($action, 'isUnresolvedComment', [['resolution' => null]]));
    }

    public function testCommentTimestampsAreShownAsRelativeTime(): void
    {
        $twoHoursAgo = (new \DateTime())->modify('-2 hours')->format(\DateTime::ATOM);

        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => ['values' => [$this->comment('Ada', 'recent', $twoHoursAgo)]],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertStringContainsString('Ada (2 hours ago):', $output);
    }

    public function testCommentHeadingsShowIdsAndWhatARepliesTo(): void
    {
        $twoHoursAgo = (new \DateTime())->modify('-2 hours')->format(\DateTime::ATOM);

        $action = $this->actionRouting(PrDetails::class, [
            '/comments' => ['values' => [
                $this->comment('Ada', 'question', $twoHoursAgo, ['id' => 101]),
                $this->comment('Grace', 'answer', $twoHoursAgo, ['id' => 102, 'parent' => ['id' => 101]]),
                $this->inlineComment('Alan', 'nit', $twoHoursAgo, ['path' => 'a.php', 'to' => 3], ['id' => 103]),
            ]],
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->show(7);
        });

        $this->assertStringContainsString('Ada (2 hours ago) [#101]:', $output);
        $this->assertStringContainsString('Grace (2 hours ago) [#102, reply to #101]:', $output);
        $this->assertStringContainsString('Alan (2 hours ago) [#103]:', $output);
    }
}
