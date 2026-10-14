<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Pipeline
 * All commands for pipeline.
 *
 * @see https://bb-cli.github.io/docs/commands/pipeline
 */
class Pipeline extends Base
{
    /**
     * Pipeline default command.
     */
    const DEFAULT_METHOD = 'latest';

    /**
     * Pipeline commands.
     */
    const AVAILABLE_COMMANDS = [
        'list' => 'list, ls',
        'recent' => 'recent',
        'view' => 'view, get',
        'latest' => 'latest',
        'wait' => 'wait',
        'run' => 'run',
        'custom' => 'custom, c',
    ];

    const ACTION_DESCRIPTION = 'Trigger and monitor pipelines';

    const COMMAND_DETAILS = [
        'list'   => ['args' => '',                     'description' => 'List the pipelines defined in bitbucket-pipelines.yml'],
        'recent' => ['args' => '',                     'description' => 'List recent pipeline runs'],
        'view'   => ['args' => '<pipeline>',           'description' => 'Show details and steps of a pipeline run'],
        'latest' => ['args' => '',                     'description' => 'Show the latest pipeline'],
        'wait'   => ['args' => '[<pipeline>]',         'description' => 'Wait for a pipeline to complete'],
        'run'    => ['args' => '<branch>',             'description' => 'Run the default pipeline on a branch'],
        'custom' => ['args' => '<branch> <pipeline>', 'description' => 'Run a named pipeline on a branch'],
    ];

    /**
     * Lists the pipelines defined in the repository's bitbucket-pipelines.yml.
     *
     * Read from the repository's main branch unless --branch names another.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function list()
    {
        $branch = $GLOBALS['bb_cli_pipeline_branch'] ?? null;
        if ($branch === null) {
            $branch = array_get(
                $this->makeRequest('GET', '', [], true, 'fetching repository'),
                'mainbranch.name'
            );
        }

        $yaml = $this->makeRequest(
            'GET',
            '/src/'.rawurlencode($branch).'/bitbucket-pipelines.yml',
            [],
            true,
            'fetching bitbucket-pipelines.yml'
        );

        if (!is_string($yaml)) {
            throw new \Exception("Could not read bitbucket-pipelines.yml on '{$branch}'.", 1);
        }

        foreach (PipelineDefinitions::parse($yaml) as $section => $names) {
            o($section, 'cyan');
            foreach ($names as $name) {
                o("  {$name}", 'yellow');
            }
        }
    }

    /**
     * Lists recent pipeline runs, newest first.
     *
     * --limit sets how many (default 20, at most 100) and --branch filters
     * them to one branch.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function recent()
    {
        $limit = $GLOBALS['bb_cli_pipeline_limit'] ?? '20';
        if (!ctype_digit((string) $limit) || $limit < 1 || $limit > 100) {
            throw new \Exception('--limit must be a number between 1 and 100.', 1);
        }

        $url = "/pipelines/?sort=-created_on&pagelen={$limit}";
        if (isset($GLOBALS['bb_cli_pipeline_branch'])) {
            $url .= '&target.branch='.rawurlencode($GLOBALS['bb_cli_pipeline_branch']);
        }

        $runs = array_get($this->makeRequest('GET', $url, [], true, 'fetching recent pipelines'), 'values', []);

        if (!$runs) {
            o('No pipelines found.', 'yellow');
            return;
        }

        foreach ($runs as $run) {
            o(implode('  ', [
                '#'.array_get($run, 'build_number'),
                $this->outcome($run),
                array_get($run, 'target.ref_name'),
                array_get($run, 'creator.display_name'),
                format_relative_timestamp(array_get($run, 'created_on')),
            ]), 'yellow');
        }
    }

    /**
     * Gets details of given pipeline, with its steps.
     *
     * @param  int  $pipeLineNumber
     * @param  bool $return Return the raw response instead of printing.
     * @return void
     */
    public function view($pipeLineNumber, $return = false)
    {
        $response = $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}", [], true, 'fetching pipeline');

        if ($return) {
            return $response;
        }

        $repoPath = getRepoPath();
        o(
            [
                'id' => $pipeLineNumber,
                'creator' => array_get($response, 'creator.display_name'),
                'repository' => array_get($response, 'repository.name'),
                'target' => array_get($response, 'target.ref_name'),
                'state' => array_get($response, 'state.name'),
                'stateResult' => array_get($response, 'state.result.name'),
                'created' => array_get($response, 'created_on'),
                'completed' => array_get($response, 'completed_on'),
                'link' => "https://bitbucket.org/$repoPath/addon/pipelines/home#!/results/$pipeLineNumber",
            ],
            'yellow'
        );

        $this->printSteps($pipeLineNumber);
    }

    /**
     * Hangs on terminal, until given pipeline finishes.
     *
     * @param  int $pipeLineNumber
     * @return void
     */
    public function wait($pipeLineNumber = null)
    {
        if (is_null($pipeLineNumber)) {
            $pipeLineNumber = $this->getLatestPipelineId();
            o('Pipeline: '. $pipeLineNumber, 'yellow');
        }

        $response = $this->view($pipeLineNumber, true);

        if (array_get($response, 'state.name') === 'COMPLETED') {
            o('');
            $this->view($pipeLineNumber, false);
            return;
        }

        o('.', 'yellow', '', '');
        sleep(2);

        $this->wait($pipeLineNumber);
    }

    /**
     * Gets details of latest pipeline.
     */
    public function latest()
    {
        return $this->view($this->getLatestPipelineId());
    }

    /**
     * Run pipeline for given branch.
     */
    public function run($branch)
    {
        $response = $this->makeRequest('POST', '/pipelines/', [
            'target' => [
                'ref_type' => 'branch',
                'type' => 'pipeline_ref_target',
                'ref_name' => $branch
            ]
        ], true, 'running pipeline');

        o($response);
    }

    /**
     * Run pipeline for given branch on given pipeline.
     */
    public function custom($branch, $pipeline)
    {
        $response = $this->makeRequest('POST', '/pipelines/', [
            'target' => [
                'ref_type' => 'branch',
                'type' => 'pipeline_ref_target',
                'ref_name' => $branch,
                'selector' => [
                    'type' => 'custom',
                    'pattern' => $pipeline,
                ],
            ]
        ], true, 'running custom pipeline');

        $pipeLineNumber = $response['build_number'];
        $repoPath = getRepoPath();

        o(
            [
                'link' => "https://bitbucket.org/$repoPath/addon/pipelines/home#!/results/$pipeLineNumber",
            ],
            'yellow'
        );
    }

    /**
     * Prints a pipeline's steps, if it has any.
     */
    private function printSteps($pipeLineNumber)
    {
        $steps = array_get(
            $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}/steps/", [], true, 'fetching pipeline steps'),
            'values',
            []
        );

        if (!$steps) {
            return;
        }

        o('Steps:', 'cyan');
        foreach ($steps as $step) {
            $line = '  '.array_get($step, 'name').': '.$this->outcome($step);
            $seconds = array_get($step, 'duration_in_seconds');
            if ($seconds !== null) {
                $line .= ' ('.($seconds >= 60 ? intdiv($seconds, 60).'m '.($seconds % 60).'s' : $seconds.'s').')';
            }
            o($line, 'yellow');
        }
    }

    /**
     * The result of a finished run or step, otherwise its state.
     */
    private function outcome($item)
    {
        if (array_get($item, 'state.name') === 'COMPLETED') {
            return array_get($item, 'state.result.name', 'COMPLETED');
        }

        return array_get($item, 'state.name');
    }

    /**
     * For the last deploy running on the pipeline.
     */
    private function getLatestPipelineId()
    {
        return $this->makeRequest('GET', '/pipelines/', [], true, 'fetching latest pipeline')['size'];
    }
}
