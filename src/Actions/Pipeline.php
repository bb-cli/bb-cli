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
    public const DEFAULT_METHOD = 'latest';

    /**
     * Pipeline commands.
     */
    public const AVAILABLE_COMMANDS = [
        'get' => 'get',
        'latest' => 'latest',
        'wait' => 'wait',
        'run' => 'run',
    ];

    /**
     * Gets details of given pipeline.
     *
     * @param  int  $pipeLineNumber
     * @param  bool $return
     * @return void
     */
    public function get($pipeLineNumber, $return = false)
    {
        $response = $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}");

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

        $response = $this->get($pipeLineNumber, true);

        if (array_get($response, 'state.name') === 'COMPLETED') {
            o('');
            $this->get($pipeLineNumber, false);
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
        return $this->get($this->getLatestPipelineId());
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
        ]);

        o($response);
    }

    /**
     * For the last deploy running on the pipeline.
     */
    private function getLatestPipelineId()
    {
        return $this->makeRequest('GET', '/pipelines/')['size'];
    }
}
