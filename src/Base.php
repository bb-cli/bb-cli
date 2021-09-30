<?php

namespace BBCli\BBCli;

class Base
{
    public const DEFAULT_METHOD = 'DEFAULT_METHOD_NOT_DEFINED';
    public const AVAILABLE_COMMANDS = [];
    public const CHECK_GIT_FOLDER = true;

    public function __construct()
    {
        $currentClass = get_class($this);
    }

    public function makeRequest($method = 'GET', $url = '', $payload = [])
    {
        $this->checkAuth();

        $repoPath = getRepoPath();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.bitbucket.org/2.0/repositories/{$repoPath}{$url}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode(userConfig('auth.username').':'.userConfig('auth.appPassword')),
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

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpStatusCode < 200 || $httpStatusCode > 299) {
            if ($httpStatusCode === 401) {
                throw new \Exception('Authorization error, please check your credentials.', 1);
            }

            $allowedStatuses = [409];
            if (!in_array($httpStatusCode, $allowedStatuses)) {
                throw new \Exception('An error occurred, status code: '.$httpStatusCode, 1);
            }
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

    private function checkAuth()
    {
        if (!userConfig('auth')) {
            e('You have to configure auth info to use this command.', 'red');
            e('Run "bb auth" first.', 'yellow');
            exit(1);
        }
    }
}
