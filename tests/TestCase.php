<?php

namespace BBCli\BBCli\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Shared fixture handling for the bb-cli suite.
 *
 * Every test runs against a throwaway HOME directory so that config('userConfigFilePath')
 * — which is re-evaluated from getenv('HOME') on each call — points at a fixture file
 * instead of the developer's real bb-cli config.
 */
abstract class TestCase extends BaseTestCase
{
    /** @var string */
    protected $home;

    /** @var string|false */
    private $originalHome;

    /** @var string */
    private $originalCwd;

    /** @var array<string> */
    private static $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalHome = getenv('HOME');
        $this->originalCwd = getcwd();

        $this->home = $this->makeTempDir('bb-home');
        putenv('HOME='.$this->home);

        $this->resetGlobals();
    }

    protected function tearDown(): void
    {
        @chdir($this->originalCwd);

        if ($this->originalHome === false) {
            putenv('HOME');
        } else {
            putenv('HOME='.$this->originalHome);
        }

        $this->resetGlobals();

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$tempDirs as $dir) {
            self::removeDirectory($dir);
        }

        self::$tempDirs = [];

        parent::tearDownAfterClass();
    }

    /**
     * Clears every global bin/bb sets from command line flags, plus the
     * prompt queue used by the getUserInput() double.
     */
    protected function resetGlobals(): void
    {
        $keys = [
            'bb_cli_project_url',
            'bb_cli_pr_title',
            'bb_cli_pr_description',
            'bb_cli_pr_destination',
            'bb_cli_pr_reviewers',
            'bb_cli_pr_draft',
            'bb_cli_interactive',
            'bb_cli_test_input',
            'bb_cli_test_prompts',
        ];

        foreach ($keys as $key) {
            unset($GLOBALS[$key]);
        }
    }

    /**
     * Queues answers for the getUserInput() double defined in tests/bootstrap.php.
     *
     * @param array<string> $answers
     */
    protected function queueInput(array $answers): void
    {
        $GLOBALS['bb_cli_test_input'] = $answers;
    }

    /**
     * Prompts recorded by the getUserInput() double.
     *
     * @return array<string>
     */
    protected function recordedPrompts(): array
    {
        return $GLOBALS['bb_cli_test_prompts'] ?? [];
    }

    /**
     * Writes the user config file that userConfig() reads.
     *
     * @param array<string, mixed> $config
     */
    protected function writeUserConfig(array $config): string
    {
        $path = $this->home.'/.bitbucket-rest-cli-config.json';
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * Writes a config holding working API token credentials.
     */
    protected function writeApiTokenConfig(string $email = 'dev@example.com', string $token = 'secret-token'): string
    {
        return $this->writeUserConfig([
            'auth' => [
                'email' => $email,
                'apiToken' => $token,
            ],
        ]);
    }

    /**
     * Captures everything the callback echoes, with ANSI colour codes stripped.
     */
    protected function captureOutput(callable $callback): string
    {
        ob_start();

        try {
            $callback();
        } finally {
            $output = ob_get_clean();
        }

        return $this->stripAnsi($output);
    }

    /**
     * Removes the colour escape sequences o() writes.
     */
    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * Creates a temp directory that is removed after the test class finishes.
     */
    protected function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        self::$tempDirs[] = $dir;

        return $dir;
    }

    /**
     * Creates a git repository with the given origin remote and returns its path.
     */
    protected function makeGitRepo(string $originUrl): string
    {
        $dir = $this->makeTempDir('bb-repo');

        exec(sprintf('git -C %s init --quiet 2>&1', escapeshellarg($dir)));
        exec(sprintf('git -C %s remote add origin %s 2>&1', escapeshellarg($dir), escapeshellarg($originUrl)));

        return $dir;
    }

    /**
     * Runs a PHP snippet in a child process against this checkout.
     *
     * Used for the code paths that call exit()/die(), which cannot run inside
     * the PHPUnit process. Composer's autoloader and APP_VERSION are set up for
     * the snippet, and HOME points at the test's fixture directory.
     *
     * @param  string      $code  Snippet body, without the opening <?php tag.
     * @param  string|null $cwd   Working directory, defaults to the fixture HOME.
     * @param  string|null $stdin Data to write to the child's stdin.
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    protected function runPhp(string $code, ?string $cwd = null, ?string $stdin = null): array
    {
        $preamble = sprintf(
            "require %s; require_once %s; if (!defined('APP_VERSION')) { define('APP_VERSION', 'v1.0.0'); }",
            var_export(dirname(__DIR__).'/vendor/autoload.php', true),
            var_export(dirname(__DIR__).'/src/utils/helpers.php', true)
        );

        return $this->runProcess(
            [PHP_BINARY, '-d', 'error_reporting=E_ALL & ~E_DEPRECATED', '-r', $preamble.$code],
            $cwd ?? $this->home,
            $stdin
        );
    }

    /**
     * Runs bin/bb in a child process.
     *
     * @param  array<string> $args
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    protected function runCli(array $args, ?string $cwd = null, ?string $stdin = null): array
    {
        return $this->runProcess(
            array_merge([PHP_BINARY, dirname(__DIR__).'/bin/bb'], $args),
            $cwd ?? $this->home,
            $stdin
        );
    }

    /**
     * Makes a directory of no-op `open`/`xdg-open` commands to put first on a
     * child process's PATH, so `bb browse` doesn't launch a real browser.
     *
     * @return string
     */
    private function stubBrowserOpeners(): string
    {
        $dir = $this->home.'/stub-bin';

        if (!is_dir($dir)) {
            mkdir($dir);
            foreach (['open', 'xdg-open'] as $opener) {
                file_put_contents("{$dir}/{$opener}", "#!/bin/sh\nexit 0\n");
                chmod("{$dir}/{$opener}", 0755);
            }
        }

        return $dir;
    }

    /**
     * @param  array<string> $command
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runProcess(array $command, string $cwd, ?string $stdin = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = $_ENV + $_SERVER;
        $env['HOME'] = $this->home;
        $env['PATH'] = $this->stubBrowserOpeners().PATH_SEPARATOR.($env['PATH'] ?? getenv('PATH'));
        unset($env['argv'], $env['argc']);
        $env = array_filter($env, 'is_scalar');

        $process = proc_open($command, $descriptors, $pipes, $cwd, $env);

        if (!is_resource($process)) {
            $this->fail('Could not start process: '.implode(' ', $command));
        }

        if (!is_null($stdin)) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'stdout' => $this->stripAnsi($stdout),
            'stderr' => $this->stripAnsi($stderr),
            'exitCode' => proc_close($process),
        ];
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
