<?php

namespace Sb\JsonRpc;

use Sb\JsonRpc\Exception\JsonRpcClientException;

abstract class Client
{
    const OPTION_HEADERS = 'headers';

    private $endpoint;
    private $methodsMap;
    private $headers = [];

    public function __construct($endpoint, array $options = [])
    {
        $this->endpoint = $endpoint;
        if (array_key_exists(self::OPTION_HEADERS, $options)) {
            $this->headers = $options[self::OPTION_HEADERS];
        }

        $rc = new \ReflectionClass($this);
        $docBlock = $rc->getDocComment();

        if (preg_match_all("#@method.*? ([^ ]+)\(([^\)]*?)\)#uis", $docBlock, $methodMatches)) {
            foreach ($methodMatches[0] as $methodMatchKey => $methodMatch) {
                $method = $methodMatches[1][$methodMatchKey];
                $paramsMatches = explode(',', $methodMatches[2][$methodMatchKey]);

                $this->methodsMap[$method] = [];
                foreach ($paramsMatches as $paramsMatch) {
                    if (preg_match("#\\$(.+)#us", $paramsMatch, $m)) {
                        $this->methodsMap[$method][] = $m[1];
                    }
                }
            }
        }

    }
    
    /**
     * @param $name
     * @param array $arguments
     *
     * @return mixed
     * @throws JsonRpcClientException
     */
    public function __call($name, array $arguments = [])
    {
        $params = [];
        foreach ($arguments as $idx => $argument) {
            $params[$this->methodsMap[$name][$idx]] = $argument;
        }

        $name = str_replace('__', '.', $name);

        $data = [
            'jsonrpc' => '2.0',
            'method' => $name,
            'params' => $params,
            'id' => 1
        ];

        return $this->request($data);
    }
    
    /**
     * @param $payload
     *
     * @return mixed
     * @throws JsonRpcClientException
     */
    public function request($payload)
    {
        $ch = curl_init();
        if (!$ch) {
            throw new JsonRpcClientException('cURL init error');
        }
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($payload));

        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array_merge(['Content-Type: application/json'], $this->headers)
        );

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            throw new JsonRpcClientException('Connection error to '. $this->endpoint . ' Return code: ' . $httpCode . ' Message: ' . $error);
        }
        curl_close($ch);

        $response = \json_decode($response, true);

        if (!is_array($response)) {
            throw new JsonRpcClientException(JsonRpcClientException::INVALID_RESPONSE);
        }

        if (array_key_exists('error', $response)) {
            throw new JsonRpcClientException($response['error']['message'], $response['error']['code']);
        }

        return $response['result'];
    }
}