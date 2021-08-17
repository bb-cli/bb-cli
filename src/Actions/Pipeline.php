<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

class Pipeline extends Base
{
    public const DEFAULT_METHOD = 'get';

    public const AVAILABLE_COMMANDS = [
        'get' => 'get, latest',
    ];

    public function get($pipeLineNumber = '')
    {
        $response = $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}");

        if (empty($pipeLineNumber)) {
            // if no pipeLineNumber is given, get last pipeLine
            $pipeLineNumber = $response['size'];
            $response = $this->makeRequest('GET', "/pipelines/{$pipeLineNumber}");
        }

        $repoPath = getRepoPath();
        e(
            [
                'id' => $pipeLineNumber,
                'creator' => array_get($response, 'creator.display_name'),
                'repository' => array_get($response, 'repository.name'),
                'target' => array_get($response, 'target.destination'),
                'state' => array_get($response, 'state.name'),
                'stateResult' => array_get($response, 'state.result.name'),
                'created' => array_get($response, 'created_on'),
                'completed' => array_get($response, 'completed_on'),
                'link' => "https://bitbucket.org/$repoPath/addon/pipelines/home#!/results/$pipeLineNumber",
            ],
            'blue'
        );
    }
}
