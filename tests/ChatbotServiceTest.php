<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/chatbot/services/EnvLoader.php';
require_once __DIR__ . '/../api/chatbot/services/GroqService.php';
require_once __DIR__ . '/../api/chatbot/services/RecruitmentDataService.php';
require_once __DIR__ . '/../api/chatbot/services/ChatbotToolService.php';
require_once __DIR__ . '/../api/chatbot/services/ChatbotService.php';

$database = getenv('NCHIRE_DB_NAME') ?: 'nchire_e2e';
if (!str_ends_with($database, '_e2e')) {
    fwrite(STDERR, "Refusing to run chatbot service tests outside an _e2e database.\n");
    exit(1);
}

final class FakeGroqService extends GroqService
{
    public array $requests = [];
    public array $lastApplicationIds = [];

    public function complete(array $messages, array $options = [])
    {
        $this->requests[] = ['messages' => $messages, 'options' => $options];
        $requestNumber = count($this->requests);
        if ($requestNumber % 2 === 1) {
            $arguments = ['status' => 'pending', 'limit' => 10];
            if ($requestNumber > 1) {
                $arguments = [
                    'application_ids' => $this->lastApplicationIds,
                    'requirement_state' => 'complete',
                    'limit' => 10,
                ];
            }
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_test_' . $requestNumber,
                        'type' => 'function',
                        'function' => [
                            'name' => 'search_applications',
                            'arguments' => json_encode($arguments),
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
                'model' => 'fake',
                'usage' => [],
            ];
        }

        $toolMessage = end($messages);
        $toolResult = json_decode((string)($toolMessage['content'] ?? ''), true);
        $this->lastApplicationIds = array_values(array_filter(array_map(
            static fn($row) => (int)($row['application_id'] ?? 0),
            $toolResult['records'] ?? []
        )));
        $count = (int)($toolResult['total_applications'] ?? -1);
        return [
            'message' => [
                'role' => 'assistant',
                'content' => "There are **{$count} pending applications** in the records available to you.",
            ],
            'finish_reason' => 'stop',
            'model' => 'fake',
            'usage' => [],
        ];
    }
}

final class FormattingFailureGroqService extends GroqService
{
    private int $requestCount = 0;

    public function complete(array $messages, array $options = [])
    {
        $this->requestCount++;
        if ($this->requestCount === 1) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_fallback',
                        'type' => 'function',
                        'function' => [
                            'name' => 'search_applications',
                            'arguments' => json_encode(['status' => 'pending']),
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
                'model' => 'fake',
                'usage' => [],
            ];
        }
        throw new GroqApiException('rate_limit', 'Synthetic formatting-call rate limit.');
    }
}

final class TableFormattingGroqService extends GroqService
{
    private int $requestCount = 0;

    public function complete(array $messages, array $options = [])
    {
        $this->requestCount++;
        if ($this->requestCount === 1) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_table',
                        'type' => 'function',
                        'function' => [
                            'name' => 'search_teaching_loads',
                            'arguments' => json_encode(['status' => 'open', 'limit' => 10]),
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
                'model' => 'fake',
                'usage' => [],
            ];
        }
        return [
            'message' => [
                'role' => 'assistant',
                'content' => "### Vacant Teaching Loads\n| Load ID | Job Title | Program | Available |\n|---|---|---|---|\n| 10 | Software Engineering | BS Computer Science | 2 |",
            ],
            'finish_reason' => 'stop',
            'model' => 'fake',
            'usage' => [],
        ];
    }
}

function serviceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_SESSION = [];
$conn = new mysqli('127.0.0.1', 'root', '', $database);
$conn->set_charset('utf8mb4');
$email = 'secretary.e2e@example.test';
$stmt = $conn->prepare('SELECT id, full_name AS name, email, role, department FROM admin_users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fake = new FakeGroqService();
$service = new ChatbotService($conn, $admin, $fake);
$response = $service->handle('How many applicants are currently pending?');

serviceAssert($response['success'] === true, 'Grounded chatbot request should succeed.');
serviceAssert($response['grounded'] === true, 'Successful database answer must be marked grounded.');
serviceAssert(str_contains($response['reply'], '**1 pending applications**'), 'Final answer must use the verified database count.');
serviceAssert(str_contains($response['reply'], 'Data checked:'), 'Grounded response must include a retrieval time.');
serviceAssert(count($fake->requests) === 2, 'Tool orchestration must use one selection call and one final-answer call.');
serviceAssert(($fake->requests[0]['options']['tool_choice'] ?? null) === 'required', 'The intent call must require a backend tool.');
serviceAssert(!isset($fake->requests[1]['options']['tools']), 'The final-answer call must not resend tool definitions or execute another tool.');

$history = $service->getHistory();
serviceAssert(count($history) === 2 && $history[0]['role'] === 'user' && $history[1]['role'] === 'assistant', 'Conversation history must be stored per authenticated session.');

$followUp = $service->handle('Which of them have completed all required documents?');
serviceAssert($followUp['success'] === true && $followUp['grounded'] === true, 'A contextual follow-up must perform another grounded query.');
serviceAssert(count($fake->requests) === 4, 'A follow-up must perform a fresh tool call and final-answer call.');
$contextMessages = $fake->requests[2]['messages'];
$contextText = implode("\n", array_map(static fn($item) => (string)($item['content'] ?? ''), $contextMessages));
serviceAssert(str_contains($contextText, 'CONVERSATION REFERENCES'), 'The follow-up must receive bounded identifier-only conversation references.');
serviceAssert(str_contains($contextText, 'application_id'), 'The conversation references must retain the previously retrieved application ID.');
$followUpTool = end($fake->requests[3]['messages']);
$followUpToolResult = json_decode((string)($followUpTool['content'] ?? ''), true);
serviceAssert(isset($followUpToolResult['retrieved_at']), 'The follow-up must use a freshly retrieved tool result.');

$service->clear();
serviceAssert($service->getHistory() === [], 'Clear must remove conversation history.');

$_SESSION = [];
$fallbackService = new ChatbotService($conn, $admin, new FormattingFailureGroqService());
$fallback = $fallbackService->handle('How many applications are pending?');
serviceAssert($fallback['success'] === true && $fallback['grounded'] === true, 'A formatting-only Groq failure must retain the verified database answer.');
serviceAssert(str_contains($fallback['reply'], 'The data was verified'), 'The deterministic fallback must clearly identify verified data.');
serviceAssert(str_contains($fallback['reply'], 'Data checked:'), 'The deterministic fallback must retain the retrieval timestamp.');

$_SESSION = [];
$tableService = new ChatbotService($conn, $admin, new TableFormattingGroqService());
$tableResponse = $tableService->handle('Show the vacant teaching loads.');
serviceAssert($tableResponse['success'] === true && $tableResponse['grounded'] === true, 'A table-shaped model response must remain grounded.');
serviceAssert(!str_contains($tableResponse['reply'], '|---'), 'Markdown table separators must not reach the narrow chat panel.');
serviceAssert(str_contains($tableResponse['reply'], '1. **Software Engineering**'), 'Markdown tables must become readable numbered record blocks.');
serviceAssert(str_contains($tableResponse['reply'], '**Available:** 2'), 'Normalized record blocks must preserve verified table fields.');

echo "Chatbot orchestration tests passed.\n";

