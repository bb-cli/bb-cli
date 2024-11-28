<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Env
 *
 * @see https://bb-cli.github.io/docs/commands/environment
 */
class Env extends Base
{
    /**
     * Env default command.
     */
    const DEFAULT_METHOD = 'environments';

    /**
     * Env commands.
     */
    const AVAILABLE_COMMANDS = [
        'environments' => 'list, l',
        'variables' => 'variables, v',
        'createVariable' => 'create-variable, c',
        'updateVariable' => 'update-variable, u',
    ];

    /**
     * List Environments.
     */
    public function environments()
    {
        $response = $this->makeRequest('GET', '/environments');

        foreach ($response['values'] as $env) {
            o(
                [
                    'uuid' => $env['uuid'],
                    'name' => $env['name'],
                ],
                'yellow'
            );
        }
    }

    /**
     * List Environment Variables.
     */
    public function variables($envUuid)
    {
        $response = $this->makeRequest('GET', "/deployments_config/environments/$envUuid/variables");

        foreach ($response['values'] as $var) {
            o(
                [
                    'uuid' => $var['uuid'],
                    'key' => $var['key'],
                    'value' => $var['value'] ?? '',
                    'secured' => $var['secured'] ? 'Yes' : 'No',
                ],
                'yellow'
            );
        }
    }

    /**
     * Create Environment Variable.
     */
    public function createVariable($envUuid, $key, $value, $secured = false)
    {
        $response = $this->makeRequest('POST', "/deployments_config/environments/$envUuid/variables", [
            'key' => $key,
            'value' => $value,
            'secured' => (bool) $secured,
        ]);

        $this->variableResponse($response);
    }

    /**
     * Update Environment Variable.
     */
    public function updateVariable($envUuid, $varUuid, $key, $value, $secured = false)
    {
        $response = $this->makeRequest('PUT', "/deployments_config/environments/$envUuid/variables/$varUuid", [
            'key' => $key,
            'value' => $value,
            'secured' => (bool) $secured,
        ]);

        $this->variableResponse($response);
    }

    /**
     * Print variable response.
     */
    private function variableResponse($response)
    {
        if (array_get($response, 'error')) {
            o($response['error']['message'], 'yellow');
            o($response['error']['detail'], 'red');
            exit(1);
        }

        o(
            [
                'uuid' => $response['uuid'],
                'key' => $response['key'],
                'value' => $response['value'] ?? '',
                'secured' => $response['secured'] ? 'Yes' : 'No',
            ],
            'yellow'
        );
    }
}
