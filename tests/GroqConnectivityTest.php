<?php

require_once __DIR__ . '/../api/chatbot/services/EnvLoader.php';
require_once __DIR__ . '/../api/chatbot/services/GroqService.php';

ChatbotEnvLoader::load(dirname(__DIR__));

try {
    $groq = new GroqService();
    $reply = $groq->chat([
        ['role' => 'system', 'content' => 'Return one JSON object only.'],
        ['role' => 'user', 'content' => 'Return a JSON object whose status field is ok.'],
    ], [
        'temperature' => 0,
        'max_completion_tokens' => 300,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'connectivity_check',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => ['status' => ['type' => 'string', 'enum' => ['ok']]],
                    'required' => ['status'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'timeout' => 20,
        'connect_timeout' => 8,
    ]);
    $decoded = json_decode($reply, true);
    if (!is_array($decoded)) throw new RuntimeException('Groq returned non-JSON content.');
    echo "Groq connectivity test passed.\n";
} catch (GroqApiException $e) {
    fwrite(STDERR, 'Groq connectivity test failed: ' . $e->getCategory() . ' - ' . $e->getMessage() . "\n");
    exit(1);
}
