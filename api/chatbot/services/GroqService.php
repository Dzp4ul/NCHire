<?php

class GroqApiException extends RuntimeException
{
    private $category;

    public function __construct($category, $message)
    {
        parent::__construct($message);
        $this->category = $category;
    }

    public function getCategory()
    {
        return $this->category;
    }
}

class GroqService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    private $apiKey;
    private $model;

    public function __construct()
    {
        $this->apiKey = ChatbotEnvLoader::get('GROQ_API_KEY', '');
        $this->model = ChatbotEnvLoader::get('GROQ_MODEL', 'openai/gpt-oss-120b');
    }

    public function isConfigured()
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    public function chat(array $messages, array $options = [])
    {
        if (!$this->isConfigured()) {
            throw new GroqApiException('missing_api_key', 'Groq API key is not configured.');
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_completion_tokens' => $options['max_completion_tokens'] ?? 700,
        ];

        if (isset($options['response_format']) && is_array($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            throw new GroqApiException('invalid_payload', 'Unable to encode Groq request payload.');
        }

        $timeout = max(5, min(60, (int)($options['timeout'] ?? 30)));
        $connectTimeout = max(3, min($timeout, (int)($options['connect_timeout'] ?? 10)));
        [$rawResponse, $httpCode] = function_exists('curl_init')
            ? $this->sendWithCurl($payloadJson, $timeout, $connectTimeout)
            : $this->sendWithStreams($payloadJson, $timeout);

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new GroqApiException('invalid_response', 'Groq returned an invalid response.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $categories = [
                400 => 'invalid_request',
                401 => 'auth_error',
                403 => 'auth_error',
                404 => 'model_not_found',
                408 => 'timeout',
                429 => 'rate_limit',
            ];
            $category = $categories[$httpCode] ?? ($httpCode >= 500 ? 'service_error' : 'api_error');
            $apiMessage = $decoded['error']['message'] ?? '';
            $apiMessage = is_scalar($apiMessage) ? trim(strip_tags((string)$apiMessage)) : '';
            $apiMessage = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $apiMessage) ?? '';
            if (strlen($apiMessage) > 500) $apiMessage = substr($apiMessage, 0, 500);
            $message = 'Groq request failed with HTTP ' . $httpCode . '.';
            if ($apiMessage !== '') $message .= ' ' . $apiMessage;
            throw new GroqApiException($category, $message);
        }

        $reply = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($reply) || trim($reply) === '') {
            throw new GroqApiException('empty_response', 'Groq returned an empty assistant message.');
        }

        return trim($reply);
    }

    private function sendWithCurl($payloadJson, $timeout, $connectTimeout)
    {
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new GroqApiException('network_error', $curlError ?: 'Groq request failed.');
        }

        return [$rawResponse, $httpCode];
    }

    private function sendWithStreams($payloadJson, $timeout)
    {
        if (!ini_get('allow_url_fopen')) {
            throw new GroqApiException('http_client_unavailable', 'No supported HTTP client is available in this PHP runtime.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Length: ' . strlen($payloadJson),
                ]),
                'content' => $payloadJson,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $rawResponse = @file_get_contents(self::ENDPOINT, false, $context);
        if ($rawResponse === false) {
            $error = error_get_last();
            throw new GroqApiException('network_error', $error['message'] ?? 'Groq request failed.');
        }

        $httpCode = $this->extractHttpStatusCode($http_response_header ?? []);
        if ($httpCode === 0) {
            throw new GroqApiException('invalid_response', 'Groq returned no HTTP status.');
        }

        return [$rawResponse, $httpCode];
    }

    private function extractHttpStatusCode(array $headers)
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
