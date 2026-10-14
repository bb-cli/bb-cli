<?php

namespace BBCli\BBCli\Tests\Unit\Actions;

use BBCli\BBCli\Actions\PipelineDefinitions;
use BBCli\BBCli\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PipelineDefinitionsTest extends TestCase
{
    public function testReadsEverySectionInFileOrder(): void
    {
        $yaml = <<<'YAML'
image: php:8.3

pipelines:
  default:
    - step:
        script:
          - composer test
  branches:
    main:
      - step:
          script:
            - echo main
    'feature/*':
      - step:
          script:
            - echo feature
  tags:
    "v*":
      - step:
          script:
            - echo tag
  pull-requests:
    '**':
      - step:
          script:
            - echo pr
  custom:
    nightly:
      - step:
          script:
            - echo nightly
YAML;

        $this->assertSame([
            'default' => [],
            'branches' => ['main', 'feature/*'],
            'tags' => ['v*'],
            'pull-requests' => ['**'],
            'custom' => ['nightly'],
        ], PipelineDefinitions::parse($yaml));
    }

    public function testSequencesAtTheSameIndentAsTheirKeyAreNotMistakenForNames(): void
    {
        $yaml = "pipelines:\n  branches:\n    main:\n    - step:\n        script:\n        - echo hi\n    dev:\n    - step:\n        name: x: y\n";

        $this->assertSame(['branches' => ['main', 'dev']], PipelineDefinitions::parse($yaml));
    }

    public function testIgnoresCommentsBlankLinesAndStepBodies(): void
    {
        $yaml = <<<'YAML'
# top comment
definitions:
  steps:
    - step: &build
        name: Build
        script:
          - |
            custom:
              not-a-pipeline: true

pipelines: # trailing comment
  # a comment inside
  custom:

    deploy: # comment on a name
      - step: *build
    'with # hash':
      - step: *build
YAML;

        $this->assertSame(
            ['custom' => ['deploy', 'with # hash']],
            PipelineDefinitions::parse($yaml)
        );
    }

    public function testStopsAtTheNextTopLevelKey(): void
    {
        $yaml = "pipelines:\n  default:\n    - step: {script: [a]}\nclone:\n  depth: 1\n  custom:\n    nope: 1\n";

        $this->assertSame(['default' => []], PipelineDefinitions::parse($yaml));
    }

    public function testRejectsAFileWithoutPipelines(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No pipelines found');

        PipelineDefinitions::parse("image: php:8.3\n");
    }

    public function testRejectsAnEmptyPipelinesSection(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No pipelines found');

        PipelineDefinitions::parse("pipelines:\nimage: x\n");
    }

    #[DataProvider('unsupportedProvider')]
    public function testFailsLoudlyOnConstructsItCannotRead(string $yaml, string $message): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        PipelineDefinitions::parse($yaml);
    }

    public static function unsupportedProvider(): array
    {
        return [
            'flow style pipelines' => ["pipelines: {default: []}\n", 'flow style'],
            'alias for a section' => ["pipelines:\n  branches: *shared\n", 'alias'],
            'merge key' => ["pipelines:\n  branches:\n    <<: *shared\n    main: []\n", 'merge key'],
            'tab indent' => ["pipelines:\n\tdefault:\n", 'tab'],
        ];
    }
}
