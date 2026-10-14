<?php

namespace BBCli\BBCli\Tests\Unit\Actions;

use BBCli\BBCli\Actions\Pipeline;
use BBCli\BBCli\Tests\Support\ActionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PipelineTest extends ActionTestCase
{
    private function pipeline(string $state = 'COMPLETED', string $result = 'SUCCESSFUL'): array
    {
        return [
            'creator' => ['display_name' => 'Ada Lovelace'],
            'repository' => ['name' => 'widgets'],
            'target' => ['ref_name' => 'main'],
            'state' => ['name' => $state, 'result' => ['name' => $result]],
            'created_on' => '2024-03-01T12:00:00Z',
            'completed_on' => '2024-03-01T12:05:00Z',
        ];
    }

    private function steps(): array
    {
        return ['values' => [
            [
                'name' => 'Build',
                'state' => ['name' => 'COMPLETED', 'result' => ['name' => 'SUCCESSFUL']],
                'duration_in_seconds' => 65,
            ],
            [
                'name' => 'Deploy',
                'state' => ['name' => 'IN_PROGRESS'],
            ],
        ]];
    }

    public function testViewPrintsPipelineDetailsStepsAndABuildLink(): void
    {
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, [
            '/pipelines/42/steps/' => $this->steps(),
            '/pipelines/42' => $this->pipeline(),
        ], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->view(42);
        });

        $this->assertSame('/pipelines/42', $recorded[0]['url']);
        $this->assertSame('fetching pipeline', $recorded[0]['label']);
        $this->assertSame('/pipelines/42/steps/', $recorded[1]['url']);
        $this->assertSame([
            'Id: 42',
            'Creator: Ada Lovelace',
            'Repository: widgets',
            'Target: main',
            'State: COMPLETED',
            'StateResult: SUCCESSFUL',
            'Created: 2024-03-01T12:00:00Z',
            'Completed: 2024-03-01T12:05:00Z',
            'Link: https://bitbucket.org/acme/widgets/addon/pipelines/home#!/results/42',
            'Steps:',
            'Build: SUCCESSFUL (1m 5s)',
            'Deploy: IN_PROGRESS',
        ], $this->lines($output));
    }

    public function testViewOmitsTheStepsSectionWhenThereAreNone(): void
    {
        $action = $this->actionRouting(Pipeline::class, [
            '/steps/' => ['values' => []],
            '/pipelines/42' => $this->pipeline(),
        ]);

        $output = $this->captureOutput(function () use ($action) {
            $action->view(42);
        });

        $this->assertNotContains('Steps:', $this->lines($output));
    }

    public function testViewReturnsTheRawResponseWithoutFetchingSteps(): void
    {
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, ['/pipelines/7' => $this->pipeline()], $recorded);

        $output = $this->captureOutput(function () use ($action, &$response) {
            $response = $action->view(7, true);
        });

        $this->assertSame($this->pipeline(), $response);
        $this->assertSame('', $output, 'Returning mode must not print anything.');
        $this->assertCount(1, $recorded, 'Polling must not fetch steps.');
    }

    public function testGetRemainsAnAliasForView(): void
    {
        $this->assertSame('view', (new Pipeline())->getMethodNameFromAlias('get'));
        $this->assertSame('view', (new Pipeline())->getMethodNameFromAlias('view'));
    }

    public function testLatestUsesThePipelineCountAsTheBuildNumber(): void
    {
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, [
            '/pipelines/13/steps/' => ['values' => []],
            '/pipelines/13' => $this->pipeline(),
            '/pipelines/' => ['size' => 13],
        ], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->latest();
        });

        $this->assertSame('/pipelines/', $recorded[0]['url']);
        $this->assertSame('fetching latest pipeline', $recorded[0]['label']);
        $this->assertSame('/pipelines/13', $recorded[1]['url']);
        $this->assertContains('Id: 13', $this->lines($output));
    }

    public function testRunPostsADefaultPipelineTargetForABranch(): void
    {
        $action = $this->action(Pipeline::class);
        $action->expects($this->once())
            ->method('makeRequest')
            ->with(
                'POST',
                '/pipelines/',
                [
                    'target' => [
                        'ref_type' => 'branch',
                        'type' => 'pipeline_ref_target',
                        'ref_name' => 'feature/x',
                    ],
                ],
                true,
                'running pipeline'
            )
            ->willReturn(['build_number' => 5]);

        $output = $this->captureOutput(function () use ($action) {
            $action->run('feature/x');
        });

        $this->assertContains('Build_number: 5', $this->lines($output));
    }

    public function testCustomPostsASelectorAndPrintsTheBuildLink(): void
    {
        $action = $this->action(Pipeline::class);
        $action->expects($this->once())
            ->method('makeRequest')
            ->with(
                'POST',
                '/pipelines/',
                [
                    'target' => [
                        'ref_type' => 'branch',
                        'type' => 'pipeline_ref_target',
                        'ref_name' => 'main',
                        'selector' => [
                            'type' => 'custom',
                            'pattern' => 'nightly',
                        ],
                    ],
                ],
                true,
                'running custom pipeline'
            )
            ->willReturn(['build_number' => 99]);

        $output = $this->captureOutput(function () use ($action) {
            $action->custom('main', 'nightly');
        });

        $this->assertSame(
            ['Link: https://bitbucket.org/acme/widgets/addon/pipelines/home#!/results/99'],
            $this->lines($output)
        );
    }

    public function testWaitReturnsImmediatelyForACompletedPipeline(): void
    {
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, [
            '/pipelines/8/steps/' => ['values' => []],
            '/pipelines/8' => $this->pipeline('COMPLETED'),
        ], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->wait(8);
        });

        $this->assertCount(3, $recorded, 'One status poll, then the final fetch and its steps for display.');
        $this->assertContains('State: COMPLETED', $this->lines($output));
    }

    public function testWaitLooksUpTheLatestPipelineWhenNoNumberIsGiven(): void
    {
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, [
            '/pipelines/4/steps/' => ['values' => []],
            '/pipelines/4' => $this->pipeline('COMPLETED'),
            '/pipelines/' => ['size' => 4],
        ], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->wait();
        });

        $this->assertSame('/pipelines/', $recorded[0]['url']);
        $this->assertContains('Pipeline: 4', $this->lines($output));
    }

    public function testWaitPollsUntilThePipelineCompletes(): void
    {
        $states = [
            $this->pipeline('IN_PROGRESS'),
            $this->pipeline('COMPLETED'),
            $this->pipeline('COMPLETED'),
            ['values' => []],
        ];

        $recorded = [];
        $action = $this->action(Pipeline::class);
        $action->method('makeRequest')->willReturnCallback(
            function ($method, $url) use (&$states, &$recorded) {
                $recorded[] = $url;

                return array_shift($states);
            }
        );

        $output = $this->captureOutput(function () use ($action) {
            $action->wait(3);
        });

        $this->assertCount(4, $recorded, 'Two polls, the final display fetch and its steps.');
        $this->assertStringContainsString('.', $output, 'A progress dot is printed between polls.');
        $this->assertContains('State: COMPLETED', $this->lines($output));
    }

    private function runEntry(int $number, string $state, string $result, string $branch, string $created): array
    {
        return [
            'build_number' => $number,
            'creator' => ['display_name' => 'Ada Lovelace'],
            'target' => ['ref_name' => $branch],
            'state' => ['name' => $state, 'result' => ['name' => $result]],
            'created_on' => $created,
        ];
    }

    public function testRecentListsRunsNewestFirst(): void
    {
        $recent = gmdate('Y-m-d\TH:i:s\Z', time() - 7200);
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, ['/pipelines/' => ['values' => [
            $this->runEntry(12, 'COMPLETED', 'SUCCESSFUL', 'main', $recent),
            $this->runEntry(11, 'IN_PROGRESS', '', 'feature/x', $recent),
            $this->runEntry(10, 'COMPLETED', 'FAILED', 'main', '2024-03-01T12:00:00Z'),
        ]]], $recorded);

        $output = $this->captureOutput(function () use ($action) {
            $action->recent();
        });

        $this->assertSame('GET', $recorded[0]['method']);
        $this->assertSame('/pipelines/?sort=-created_on&pagelen=20', $recorded[0]['url']);
        $this->assertSame('fetching recent pipelines', $recorded[0]['label']);
        $this->assertSame([
            '#12 SUCCESSFUL main Ada Lovelace 2 hours ago',
            '#11 IN_PROGRESS feature/x Ada Lovelace 2 hours ago',
            '#10 FAILED main Ada Lovelace Mar 01, 2024',
        ], array_map(function ($line) {
            return preg_replace('/\s+/', ' ', $line);
        }, $this->lines($output)));
    }

    public function testRecentFiltersByBranchAndHonoursTheLimit(): void
    {
        $GLOBALS['bb_cli_pipeline_branch'] = 'release/1.0';
        $GLOBALS['bb_cli_pipeline_limit'] = '5';
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, ['/pipelines/' => ['values' => []]], $recorded);

        $this->captureOutput(function () use ($action) {
            $action->recent();
        });

        $this->assertSame(
            '/pipelines/?sort=-created_on&pagelen=5&target.branch=release%2F1.0',
            $recorded[0]['url']
        );
    }

    public function testRecentSaysSoWhenThereAreNoRuns(): void
    {
        $action = $this->actionRouting(Pipeline::class, ['/pipelines/' => ['values' => []]]);

        $output = $this->captureOutput(function () use ($action) {
            $action->recent();
        });

        $this->assertSame(['No pipelines found.'], $this->lines($output));
    }

    #[DataProvider('invalidLimitProvider')]
    public function testRecentRejectsAnInvalidLimitBeforeRequesting(string $limit): void
    {
        $GLOBALS['bb_cli_pipeline_limit'] = $limit;
        $action = $this->action(Pipeline::class);
        $action->expects($this->never())->method('makeRequest');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('--limit');

        $action->recent();
    }

    public static function invalidLimitProvider(): array
    {
        return ['zero' => ['0'], 'negative' => ['-3'], 'text' => ['lots'], 'too big' => ['101']];
    }

    public function testListPrintsWorkflowsFromTheDefaultBranchFile(): void
    {
        $yaml = "pipelines:\n  default:\n    - step:\n        script: [a]\n  branches:\n    main:\n      - step: {script: [a]}\n  custom:\n    nightly:\n      - step: {script: [a]}\n";
        $recorded = [];
        $action = $this->action(Pipeline::class);
        $action->method('makeRequest')->willReturnCallback(
            function ($method, $url, $payload = [], $repo = true, $label = null) use ($yaml, &$recorded) {
                $recorded[] = [$method, $url, $label];

                return $url === '' ? ['mainbranch' => ['name' => 'trunk']] : $yaml;
            }
        );

        $output = $this->captureOutput(function () use ($action) {
            $action->list();
        });

        $this->assertSame(['GET', '', 'fetching repository'], $recorded[0]);
        $this->assertSame(['GET', '/src/trunk/bitbucket-pipelines.yml', 'fetching bitbucket-pipelines.yml'], $recorded[1]);
        $this->assertSame([
            'default',
            'branches',
            'main',
            'custom',
            'nightly',
        ], $this->lines($output));
    }

    public function testListReadsTheFileFromTheGivenBranch(): void
    {
        $GLOBALS['bb_cli_pipeline_branch'] = 'feature/x';
        $recorded = [];
        $action = $this->actionRouting(Pipeline::class, [
            '/src/' => "pipelines:\n  default:\n    - step: {script: [a]}\n",
        ], $recorded);

        $this->captureOutput(function () use ($action) {
            $action->list();
        });

        $this->assertCount(1, $recorded, 'An explicit branch needs no repository lookup.');
        $this->assertSame('/src/feature%2Fx/bitbucket-pipelines.yml', $recorded[0]['url']);
    }

    public function testListRejectsAResponseThatIsNotTheFile(): void
    {
        $GLOBALS['bb_cli_pipeline_branch'] = 'main';
        $action = $this->actionRouting(Pipeline::class, ['/src/' => ['unexpected' => 'json']]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('bitbucket-pipelines.yml');

        $action->list();
    }
}
