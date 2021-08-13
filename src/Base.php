<?php

namespace BBCli\BBCli;

class Base
{
    public $configFilePath;
    public const DEFAULT_METHOD = 'DEFAULT_METHOD_NOT_DEFINED';
    public const AVAILABLE_COMMANDS = [];
    public const CHECK_GIT_FOLDER = true;

    public function __construct()
    {
        $this->createConfigFileIfNotExists();

        $currentClass = get_class($this);
        if ($currentClass::CHECK_GIT_FOLDER) {
            if (!is_dir(getcwd().'/.git')) {
                e('ERROR: No git repository found in current directory.', 'red');
                exit(1);
            }
        }
    }

    public function makeRequest($method = 'GET', $url = '', $payload = [])
    {
        $repoPath = getRepoPath();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.bitbucket.org/2.0/repositories/{$repoPath}{$url}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode(config('auth.username').':'.config('auth.appPassword')),
        ]);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
            die;
        }

        curl_close($ch);

        $jsonResult = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $result;
        }

        if (array_get($jsonResult, 'type') === 'error') {
            throw new \Exception(array_get($jsonResult, 'error.message'), 1);
        }

        return $jsonResult;
    }

    public function getMethodNameFromAlias($alias)
    {
        $currentClass = get_class($this);

        foreach ($currentClass::AVAILABLE_COMMANDS as $method => $methodAliases) {
            $methodAliases = array_map(
                'trim',
                explode(', ', $methodAliases)
            );

            if (in_array($alias, $methodAliases)) {
                return $method;
            }
        }

        return false;
    }

    private function createConfigFileIfNotExists()
    {
        $this->configFilePath = getenv('HOME').'/.bitbucket-rest-cli-config.json';

        if (!file_exists(dirname($this->configFilePath))) {
            mkdir(dirname($this->configFilePath), 0600, true);
        }

        if (!file_exists($this->configFilePath)) {
            file_put_contents($this->configFilePath, '{}');
            chmod($this->configFilePath, 0600);
        }
    }
}
