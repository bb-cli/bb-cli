<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

class Pipeline extends Base
{
    public const DEFAULT_METHOD = 'latest';

    public const AVAILABLE_COMMANDS = [
        'get' => 'get',
        'latest' => 'latest',
        'wait' => 'wait',
    ];

    public function get($pipeLineNumber, $return = false)
    {
        $response = $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}");

        if ($return) {
            return $response;
        }

        $repoPath = getRepoPath();
        e(
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

    public function wait($pipeLineNumber = null)
    {
        if (is_null($pipeLineNumber)) {
            $pipeLineNumber = $this->getLatestPipelineId();
            e('Pipeline: '. $pipeLineNumber, 'yellow');
        }

        $response = $this->get($pipeLineNumber, true);

        if (array_get($response, 'state.name') === 'COMPLETED') {
            e('');
            $this->get($pipeLineNumber, false);
            return;
        }

        e('.', 'yellow', '', '');
        sleep(2);

        $this->wait($pipeLineNumber);
    }

    public function latest()
    {
        return $this->get($this->getLatestPipelineId());
    }

    private function getLatestPipelineId()
    {
        return $this->makeRequest('GET', '/pipelines/')['size'];
    }
}
