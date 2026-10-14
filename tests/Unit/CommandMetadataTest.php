<?php

namespace BBCli\BBCli\Tests\Unit;

use BBCli\BBCli\Actions\Auth;
use BBCli\BBCli\Actions\Branch;
use BBCli\BBCli\Actions\Browse;
use BBCli\BBCli\Actions\Env;
use BBCli\BBCli\Actions\Pipeline;
use BBCli\BBCli\Actions\Pr;
use BBCli\BBCli\Actions\PrDetails;
use BBCli\BBCli\Actions\Upgrade;
use BBCli\BBCli\Base;
use BBCli\BBCli\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Guards the command tables every action exposes to bin/bb: aliases must map to
 * real methods, help metadata must line up, and autocomplete must stay stable.
 */
class CommandMetadataTest extends TestCase
{
    /**
     * The action table bin/bb dispatches on.
     */
    public static function actionProvider(): array
    {
        return [
            'pr' => [Pr::class],
            'pr-details' => [PrDetails::class],
            'pipeline' => [Pipeline::class],
            'branch' => [Branch::class],
            'auth' => [Auth::class],
            'browse' => [Browse::class],
            'upgrade' => [Upgrade::class],
            'env' => [Env::class],
        ];
    }

    #[DataProvider('actionProvider')]
    public function testEveryAliasMapsToAPublicMethod(string $class): void
    {
        $this->assertIsArray($class::AVAILABLE_COMMANDS);

        foreach ($class::AVAILABLE_COMMANDS as $method => $aliases) {
            $this->assertTrue(
                method_exists($class, $method),
                "{$class}::AVAILABLE_COMMANDS points at missing method {$method}()."
            );

            $reflection = new \ReflectionMethod($class, $method);

            $this->assertTrue(
                $reflection->isPublic(),
                "{$class}::{$method}() must be public to be callable from bin/bb."
            );

            $this->assertNotSame('', trim($aliases), "{$class}::{$method}() has no alias.");
        }
    }

    #[DataProvider('actionProvider')]
    public function testDefaultMethodExistsAndIsPublic(string $class): void
    {
        $default = $class::DEFAULT_METHOD;

        $this->assertNotSame(
            Base::DEFAULT_METHOD,
            $default,
            "{$class} must define its own DEFAULT_METHOD."
        );
        $this->assertTrue(method_exists($class, $default), "{$class}::{$default}() is missing.");
        $this->assertTrue((new \ReflectionMethod($class, $default))->isPublic());
    }

    #[DataProvider('actionProvider')]
    public function testEveryAliasResolvesBackToItsMethod(string $class): void
    {
        $action = new $class();

        $this->assertIsArray($class::AVAILABLE_COMMANDS);

        foreach ($class::AVAILABLE_COMMANDS as $method => $aliases) {
            foreach (array_map('trim', explode(',', $aliases)) as $alias) {
                $this->assertSame(
                    $method,
                    $action->getMethodNameFromAlias($alias),
                    "Alias '{$alias}' should resolve to {$method}()."
                );
            }
        }
    }

    #[DataProvider('actionProvider')]
    public function testAliasesAreUniqueWithinAnAction(string $class): void
    {
        $seen = [];

        $this->assertIsArray($class::AVAILABLE_COMMANDS);

        foreach ($class::AVAILABLE_COMMANDS as $method => $aliases) {
            foreach (array_map('trim', explode(',', $aliases)) as $alias) {
                $this->assertNotSame('', $alias);

                if (isset($seen[$alias])) {
                    $this->fail("Alias '{$alias}' is used by both {$seen[$alias]}() and {$method}().");
                }

                $seen[$alias] = $method;
            }
        }
    }

    #[DataProvider('actionProvider')]
    public function testHelpMetadataCoversEveryCommand(string $class): void
    {
        if (empty($class::AVAILABLE_COMMANDS)) {
            $this->assertTrue(defined("{$class}::ACTION_DESCRIPTION"), "{$class} needs an ACTION_DESCRIPTION.");

            return;
        }

        $details = defined("{$class}::COMMAND_DETAILS") ? $class::COMMAND_DETAILS : [];

        foreach (array_keys($class::AVAILABLE_COMMANDS) as $method) {
            $this->assertArrayHasKey($method, $details, "{$class}::COMMAND_DETAILS is missing {$method}.");
            $this->assertArrayHasKey('args', $details[$method]);
            $this->assertNotSame('', trim($details[$method]['description'] ?? ''));
        }

        $this->assertSame(
            [],
            array_diff(array_keys($details), array_keys($class::AVAILABLE_COMMANDS)),
            "{$class}::COMMAND_DETAILS documents commands that do not exist."
        );
    }

    #[DataProvider('actionProvider')]
    public function testActionsDeclareADescriptionForTheTopLevelHelp(string $class): void
    {
        $this->assertTrue(defined("{$class}::ACTION_DESCRIPTION"), "{$class} needs an ACTION_DESCRIPTION.");
        $this->assertNotSame('', trim($class::ACTION_DESCRIPTION));
    }

    public function testUnknownAliasesResolveToFalse(): void
    {
        $this->assertFalse((new Pr())->getMethodNameFromAlias('nope'));
        $this->assertFalse((new Branch())->getMethodNameFromAlias(''));
    }

    public function testAutocompleteListsTheFirstAliasOfEachCommandSorted(): void
    {
        $output = $this->captureOutput(function () {
            (new Branch())->listCommandsForAutocomplete();
        });

        $this->assertSame('list name user', $output);
    }

    public function testAutocompleteForPipelineUsesShortAliases(): void
    {
        $output = $this->captureOutput(function () {
            (new Pipeline())->listCommandsForAutocomplete();
        });

        $this->assertSame('custom latest list recent run view wait', $output);
    }

    public function testAutocompleteForAnActionWithoutCommandsIsEmpty(): void
    {
        $output = $this->captureOutput(function () {
            (new Upgrade())->listCommandsForAutocomplete();
        });

        $this->assertSame('', $output);
    }

    public function testActionsThatDoNotNeedARepositorySkipTheGitFolderCheck(): void
    {
        $this->assertFalse(Auth::CHECK_GIT_FOLDER);
        $this->assertFalse(Upgrade::CHECK_GIT_FOLDER);
        $this->assertTrue(Pr::CHECK_GIT_FOLDER);
        $this->assertTrue(Branch::CHECK_GIT_FOLDER);
        $this->assertTrue(Browse::CHECK_GIT_FOLDER);
        $this->assertTrue(Env::CHECK_GIT_FOLDER);
        $this->assertTrue(Pipeline::CHECK_GIT_FOLDER);
    }

    public function testTheActionTableInBinBbMatchesTheTestedActions(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/bin/bb');

        preg_match('/\$actions = \[(.*?)\];/s', $source, $matches);
        $this->assertNotEmpty($matches, 'Could not find the $actions table in bin/bb.');

        preg_match_all('/\\\\BBCli\\\\BBCli\\\\Actions\\\\(\w+)::class/', $matches[1], $classes);

        $registered = array_map(
            function ($name) {
                return 'BBCli\\BBCli\\Actions\\'.$name;
            },
            $classes[1]
        );

        $tested = array_map(
            function ($row) {
                return $row[0];
            },
            array_values(self::actionProvider())
        );

        sort($registered);
        sort($tested);

        $this->assertSame($tested, $registered, 'bin/bb registers actions that this suite does not cover.');
    }
}
