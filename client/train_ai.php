<?php
// Simple trainer to add Q/A pairs to ai_knowledge.json
// POST JSON: { "question": "How do I enroll?", "answer": "To enroll..." }
// Use locally (admin only). No auth implemented — add if needed.

header('Content-Type: application/json');

$kb_path = __DIR__ . '/ai_knowledge.json';
if (!file_exists($kb_path)) file_put_contents($kb_path, json_encode([]));

$input = json_decode(file_get_contents('php://input'), true);
$question = trim($input['question'] ?? '');
$answer = trim($input['answer'] ?? '');

if ($question === '' || $answer === '') {
    http_response_code(400);
    echo json_encode(['error' => 'question and answer required']);
    exit();
}

$kb = json_decode(file_get_contents($kb_path), true) ?? [];
$kb[] = ['q' => $question, 'a' => $answer, 'created_at' => date('c')];
file_put_contents($kb_path, json_encode($kb, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['status' => 'ok', 'entry' => end($kb)]);
