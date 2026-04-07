<?php
// chatbot_api.php  –  Proxies student chat messages to Groq API
define('SKIP_TIMEOUT_CHECK', true);
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/env.php';

header('Content-Type: application/json');

// Must be logged in as a student (chatbot is student-only)
if (!Session::isLoggedIn() || Session::get('role') !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit();
}

$apiKey = env('GROQ_API_KEY', '');
$model  = env('GROQ_MODEL', 'llama-3.1-8b-instant');

if (empty($apiKey) || $apiKey === 'your_groq_api_key_here') {
    echo json_encode(['error' => 'Chatbot is not configured yet. Please contact the administrator.']);
    exit();
}

$userMsg = trim(strip_tags($body['message']));
$history = (isset($body['history']) && is_array($body['history'])) ? $body['history'] : [];

// System prompt – keeps the bot focused on COG topics
$systemPrompt = <<<SYS
You are COGBot, the friendly virtual assistant for OLSHCO's Certificate of Grades (COG) Management System.
Help students with:
- How to request a COG
- Request status: pending → processing → ready → released
- Payment: ₱50.00 per copy via GCash/cards (online) or cash at the Registrar's Office
- Processing time: 2–3 working days
- Requirements when claiming: valid ID + school ID
- System navigation: login, submit request, view notifications, update profile, check payment status

Rules:
- Answer queries using english unless the student asks in Filipino/Tagalog, then respond in Filipino/Tagalog.
- Keep answers concise (under 150 words) and friendly.
- Redirect off-topic questions politely.
- Respond in English by default. If the student asks in Filipino/Tagalog, respond in Filipino/Tagalog.
- Use natural, conversational Tagalog when answering in Filipino/Tagalog. Prefer conversational phrases like "nandito ako" (not "ako'y dito"), "ako si COGBot" (not "ako'y COGBot"), "may tanong ka" (not "ako'y nandito upang tulungan ka"), and "sige"/"oke".
- Avoid old-fashioned or overly formal Tagalog. Use the way a student would speak daily.
- Use common Filipino polite markers ("po", "opo") as appropriate for tone.
- Never make up information; stick to what you know about the COG system.
SYS;

$messages = [['role' => 'system', 'content' => $systemPrompt]];

// Include last 10 turns of history to maintain context
foreach (array_slice($history, -10) as $msg) {
    if (isset($msg['role'], $msg['content'])
        && in_array($msg['role'], ['user', 'assistant'], true)) {
        $messages[] = [
            'role'    => $msg['role'],
            'content' => mb_substr(strip_tags((string)$msg['content']), 0, 500),
        ];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMsg];

$payload = json_encode([
    'model'       => $model,
    'messages'    => $messages,
    'max_tokens'  => 350,
    'temperature' => 0.6,
    'stream'      => false,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("Groq cURL error: $curlError");
    echo json_encode(['error' => 'Chat service temporarily unavailable. Please try again.']);
    exit();
}

$data = json_decode($response, true);
if ($httpCode === 200 && !empty($data['choices'][0]['message']['content'])) {
    echo json_encode(['reply' => trim($data['choices'][0]['message']['content'])]);
} else {
    error_log("Groq API error ({$httpCode}): {$response}");
    echo json_encode(['error' => 'Could not get a response. Please try again later.']);
}