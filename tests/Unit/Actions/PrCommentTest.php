<?php

namespace BBCli\BBCli\Tests\Unit\Actions;

use BBCli\BBCli\Actions\Pr;
use BBCli\BBCli\Tests\Support\ActionTestCase;

/**
 * Covers "bb pr comment": general, inline and reply comments, and where the
 * message comes from.
 */
class PrCommentTest extends ActionTestCase
{
    /** @var array<int, array{method: string, url: string, payload: array}> */
    private $recorded = [];

    /**
     * An action whose makeRequest() records calls and answers like the
     * comments endpoint, with readStdin() returning $stdin.
     */
    private function commentAction(?string $stdin = null)
    {
        $action = $this->getMockBuilder(Pr::class)
            ->onlyMethods(['makeRequest', 'readStdin'])
            ->getMock();

        $this->recorded = [];
        $action->method('makeRequest')->willReturnCallback(function ($method, $url, $payload = []) {
            $this->recorded[] = ['method' => $method, 'url' => $url, 'payload' => $payload];

            return [
                'id' => 555,
                'links' => ['html' => ['href' => 'https://bitbucket.org/acme/widgets/pull-requests/7#comment-555']],
            ];
        });
        $action->method('readStdin')->willReturn((string) $stdin);

        return $action;
    }

    public function testPostsAGeneralComment(): void
    {
        $action = $this->commentAction();

        $output = $this->captureOutput(function () use ($action) {
            $action->comment(7, 'Looks good');
        });

        $this->assertSame([[
            'method' => 'POST',
            'url' => '/pullrequests/7/comments',
            'payload' => ['content' => ['raw' => 'Looks good']],
        ]], $this->recorded);

        $this->assertSame([
            'Id: 555',
            'Link: https://bitbucket.org/acme/widgets/pull-requests/7#comment-555',
        ], $this->lines($output));
    }

    public function testJoinsUnquotedWordsIntoOneMessage(): void
    {
        $action = $this->commentAction();

        $this->captureOutput(function () use ($action) {
            $action->comment(7, 'Looks', 'good', 'to', 'me');
        });

        $this->assertSame('Looks good to me', $this->recorded[0]['payload']['content']['raw']);
    }

    public function testADashReadsTheMessageFromStdin(): void
    {
        $action = $this->commentAction("First line\n\nSecond paragraph\n");

        $this->captureOutput(function () use ($action) {
            $action->comment(7, '-');
        });

        $this->assertSame("First line\n\nSecond paragraph\n", $this->recorded[0]['payload']['content']['raw']);
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $action = $this->commentAction("  \n");

        try {
            $action->comment(7, '-');
            $this->fail('Expected an empty message to be rejected.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Comment message required.', $e->getMessage());
        }

        $this->assertSame([], $this->recorded);
    }

    public function testAMissingMessageIsRejected(): void
    {
        $this->expectExceptionMessage('Comment message required.');

        $this->commentAction()->comment(7);
    }

    public function testFileAndLinePlaceTheCommentInline(): void
    {
        $GLOBALS['bb_cli_pr_comment_file'] = 'src/Base.php';
        $GLOBALS['bb_cli_pr_comment_line'] = '42';

        $action = $this->commentAction();

        $this->captureOutput(function () use ($action) {
            $action->comment(7, 'Nit');
        });

        $this->assertSame(
            ['content' => ['raw' => 'Nit'], 'inline' => ['path' => 'src/Base.php', 'to' => 42]],
            $this->recorded[0]['payload']
        );
    }

    public function testReplyToSetsTheParentComment(): void
    {
        $GLOBALS['bb_cli_pr_comment_reply_to'] = '101';

        $action = $this->commentAction();

        $this->captureOutput(function () use ($action) {
            $action->comment(7, 'Done');
        });

        $this->assertSame(
            ['content' => ['raw' => 'Done'], 'parent' => ['id' => 101]],
            $this->recorded[0]['payload']
        );
    }

    /**
     * @param array<string, string> $globals
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPlacementProvider')]
    public function testInvalidPlacementIsRejectedBeforeAnyRequest(array $globals, string $message): void
    {
        foreach ($globals as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        $action = $this->commentAction();

        try {
            $action->comment(7, 'Hello');
            $this->fail('Expected the placement to be rejected.');
        } catch (\Exception $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame([], $this->recorded);
    }

    public static function invalidPlacementProvider(): array
    {
        return [
            'file without line' => [['bb_cli_pr_comment_file' => 'a.php'], 'need both --file and --line'],
            'line without file' => [['bb_cli_pr_comment_line' => '3'], 'need both --file and --line'],
            'line not a number' => [
                ['bb_cli_pr_comment_file' => 'a.php', 'bb_cli_pr_comment_line' => 'ten'],
                '--line must be a positive whole number',
            ],
            'line zero' => [
                ['bb_cli_pr_comment_file' => 'a.php', 'bb_cli_pr_comment_line' => '0'],
                '--line must be a positive whole number',
            ],
            'reply-to not a number' => [['bb_cli_pr_comment_reply_to' => 'abc'], '--reply-to must be a positive whole number'],
            'reply-to with file' => [
                ['bb_cli_pr_comment_reply_to' => '101', 'bb_cli_pr_comment_file' => 'a.php', 'bb_cli_pr_comment_line' => '3'],
                "--reply-to can't be combined",
            ],
        ];
    }
}
