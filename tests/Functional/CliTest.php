<?php

namespace BBCli\BBCli\Tests\Functional;

use BBCli\BBCli\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Drives bin/bb as a real command so dispatch, help, flag parsing and the
 * exit codes for every failure path are covered end to end.
 *
 * No test here reaches the network: commands either stop before the first
 * request or run against actions that make none.
 */
class CliTest extends TestCase
{
    public function testPrintsTheVersion(): void
    {
        $result = $this->runCli(['--version']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Version: ', $result['stdout']);
    }

    #[DataProvider('versionAliasProvider')]
    public function testVersionAliases(string $alias): void
    {
        $result = $this->runCli([$alias]);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Version: ', $result['stdout']);
    }

    public static function versionAliasProvider(): array
    {
        return [['version'], ['--version'], ['-v']];
    }

    #[DataProvider('helpAliasProvider')]
    public function testHelpAliasesListEveryAction(string $alias): void
    {
        $result = $this->runCli($alias === '' ? [] : [$alias]);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Available actions:', $result['stdout']);

        foreach (['pr', 'pr-details', 'pipeline', 'branch', 'auth', 'browse', 'upgrade', 'env'] as $action) {
            $this->assertMatchesRegularExpression(
                '/^\s+'.preg_quote($action, '/').'\s+\S/m',
                $result['stdout'],
                "The help output should list the '{$action}' action."
            );
        }
    }

    public static function helpAliasProvider(): array
    {
        return [[''], ['help'], ['--help'], ['-h']];
    }

    public function testHelpDocumentsTheGlobalOptions(): void
    {
        $result = $this->runCli(['--help']);

        foreach (['--project', '--interactive', '--title', '--description', '--destination', '--reviewers', '--draft', '--file', '--line', '--reply-to'] as $flag) {
            $this->assertStringContainsString($flag, $result['stdout'], "{$flag} should be documented.");
        }
    }

    public function testAutocompleteListsTheActionNames(): void
    {
        $result = $this->runCli(['--autocomplete']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame(
            'pr pr-details pipeline branch auth browse upgrade env',
            trim($result['stdout'])
        );
    }

    public function testAutocompleteForAnActionListsItsSubcommands(): void
    {
        $result = $this->runCli(['branch', '--autocomplete']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('list name user', trim($result['stdout']));
    }

    public function testActionHelpListsSubcommandsWithArgumentsAndDescriptions(): void
    {
        $result = $this->runCli(['pr', '--help']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('bb pr <subcommand> [args]', $result['stdout']);
        $this->assertStringContainsString('create', $result['stdout']);
        $this->assertStringContainsString('<from> [<to>]', $result['stdout']);
        $this->assertStringContainsString('ready', $result['stdout']);
    }

    public function testActionHelpForAnActionWithoutSubcommands(): void
    {
        $result = $this->runCli(['upgrade', '--help']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('bb upgrade', $result['stdout']);
        $this->assertStringNotContainsString('<subcommand>', $result['stdout']);
    }

    public function testRejectsAnUnknownAction(): void
    {
        $result = $this->runCli(['nonsense']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Given action is invalid: (nonsense)', $result['stdout']);
    }

    public function testRejectsAnUnknownSubcommand(): void
    {
        $result = $this->runCli(['pr', 'nonsense', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Unknown method', $result['stdout']);
        $this->assertStringContainsString('nonsense', $result['stdout']);
        $this->assertStringContainsString('pr', $result['stdout']);
    }

    public function testRefusesToRunOutsideAGitRepository(): void
    {
        $result = $this->runCli(['pr', 'list']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('ERROR: No git repository found in current directory.', $result['stdout']);
        $this->assertStringContainsString('Use --project "owner/repo"', $result['stdout']);
    }

    public function testActionsThatDoNotNeedARepositorySkipTheGitCheck(): void
    {
        $result = $this->runCli(['auth', 'show']);

        $this->assertStringNotContainsString('No git repository found', $result['stdout']);
    }

    public function testProjectFlagReplacesTheGitRepositoryRequirement(): void
    {
        $result = $this->runCli(['browse', 'show', '--project', 'acme/widgets']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('https://bitbucket.org/acme/widgets', trim($result['stdout']));
    }

    public function testProjectFlagAcceptsAFullBitbucketUrl(): void
    {
        $result = $this->runCli(['browse', 'show', '--project', 'https://bitbucket.org/acme/widgets']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('https://bitbucket.org/acme/widgets', trim($result['stdout']));
    }

    public function testAnInvalidProjectFlagIsReportedAsAnError(): void
    {
        $result = $this->runCli(['browse', 'show', '--project', 'https://github.com/acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Invalid repository format.', $result['stdout']);
    }

    public function testGlobalFlagsAreStrippedBeforeSubcommandDispatch(): void
    {
        $result = $this->runCli([
            'browse', 'show',
            '--project', 'acme/widgets',
            '--draft',
            '-i',
            '--title', 'ignored here',
            '--description', 'ignored here',
            '--destination', 'ignored here',
            '--reviewers', 'ignored here',
            '--file', 'ignored here',
            '--line', 'ignored here',
            '--reply-to', 'ignored here',
        ]);

        $this->assertSame(0, $result['exitCode'], 'Global flags must not be passed on as positional arguments.');
        $this->assertSame('https://bitbucket.org/acme/widgets', trim($result['stdout']));
    }

    public function testMissingArgumentsAreReportedClearly(): void
    {
        $result = $this->runCli(['pr', 'show', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('PR ID required.', $result['stdout']);
    }

    public function testTooFewArgumentsForASubcommandIsReportedClearly(): void
    {
        $result = $this->runCli(['env', 'variables', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Too few arguments.', $result['stdout']);
    }

    public function testAuthShowWithoutAnyConfigFile(): void
    {
        $result = $this->runCli(['auth', 'show']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('You have to configure auth info to use this command.', $result['stdout']);
        $this->assertStringContainsString('Run "bb auth" first.', $result['stdout']);
    }

    public function testCommandsRequiringAuthStopWhenNoCredentialsAreConfigured(): void
    {
        $this->writeUserConfig(['something' => 'else']);

        $result = $this->runCli(['branch', 'list', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('You have to configure auth info to use this command.', $result['stdout']);
    }

    public function testLegacyAppPasswordConfigIsRejected(): void
    {
        $this->writeUserConfig([
            'auth' => ['username' => 'legacy', 'appPassword' => 'secret'],
        ]);

        $result = $this->runCli(['branch', 'list', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString(
            'Bitbucket App Passwords are no longer supported (Bitbucket retired them on July 28, 2026).',
            $result['stdout']
        );
        $this->assertStringContainsString('Run "bb auth" to configure an API token.', $result['stdout']);
    }

    #[DataProvider('incompleteAuthProvider')]
    public function testIncompleteApiTokenConfigIsRejected(array $auth, string $expected): void
    {
        $this->writeUserConfig(['auth' => $auth]);

        $result = $this->runCli(['branch', 'list', '--project', 'acme/widgets']);

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString($expected, $result['stdout']);
    }

    public static function incompleteAuthProvider(): array
    {
        return [
            'no token' => [
                ['email' => 'dev@example.com'],
                'Your auth config is missing an API token.',
            ],
            'no email' => [
                ['apiToken' => 'tok'],
                'Your auth config is missing an email address.',
            ],
            'neither' => [
                ['somethingElse' => true],
                'Your auth config is missing an email address and API token.',
            ],
        ];
    }

    public function testAuthShowDisplaysTheConfiguredCredentials(): void
    {
        $this->writeApiTokenConfig('dev@example.com', 'tok-123');

        $result = $this->runCli(['auth', 'show']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Email: dev@example.com', $result['stdout']);
        $this->assertStringContainsString('ApiToken: tok-123', $result['stdout']);
    }

    public function testAuthShowFlagsALegacyConfig(): void
    {
        $this->writeUserConfig([
            'auth' => ['username' => 'legacy', 'appPassword' => 'secret'],
        ]);

        $result = $this->runCli(['auth', 'show']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('legacy fields', $result['stdout']);
    }

    public function testAuthSavesCredentialsTypedAtThePrompt(): void
    {
        $this->writeUserConfig([]);

        $result = $this->runCli(['auth'], null, "dev@example.com\ntok-123\n");

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Auth info saved.', $result['stdout']);

        $stored = json_decode(file_get_contents($this->home.'/.bitbucket-rest-cli-config.json'), true);

        $this->assertSame(['email' => 'dev@example.com', 'apiToken' => 'tok-123'], $stored['auth']);
    }

    public function testAuthRejectsEmptyPromptAnswers(): void
    {
        $this->writeUserConfig([]);

        $result = $this->runCli(['auth'], null, "\n\n");

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('No input received - auth not changed.', $result['stdout']);
    }

    public function testBrowseInsideAGitRepositoryUsesTheOriginRemote(): void
    {
        $repo = $this->makeGitRepo('git@bitbucket.org:acme/widgets.git');

        $result = $this->runCli(['browse', 'show'], $repo);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('https://bitbucket.org/acme/widgets', trim($result['stdout']));
    }

    public function testDefaultSubcommandRunsWhenNoneIsGiven(): void
    {
        // Browse's DEFAULT_METHOD is browse(), which prints the url before opening it.
        $result = $this->runCli(['browse', '--project', 'acme/widgets']);

        $this->assertStringContainsString('https://bitbucket.org/acme/widgets', $result['stdout']);
    }

    public function testTheEntrypointIsSyntacticallyValid(): void
    {
        $result = $this->runProcessForLint(dirname(__DIR__, 2).'/bin/bb');

        $this->assertSame(0, $result['exitCode'], $result['stdout']);
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runProcessForLint(string $file): array
    {
        return $this->runPhp(sprintf(
            'passthru(escapeshellarg(PHP_BINARY)." -l ".escapeshellarg(%s), $code); exit($code);',
            var_export($file, true)
        ));
    }
}
