<?php
// Quick tester to simulate a conversation with ai_chat.php
// Run from command line: php test_ai_chat.php

$base = 'http://localhost/' . rawurlencode('online LMS') . '/client/ai_chat.php';
$cookieFile = sys_get_temp_dir() . '/ai_chat_cookie.txt';

function call($msg, $reset = false) {
    global $base, $cookieFile;
    $payload = ['message' => $msg];
    if ($reset) $payload['reset'] = true;
    $ch = curl_init($base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "cURL error: " . curl_error($ch) . "\n";
        return;
    }
    curl_close($ch);
    echo "REQUEST: $msg\n";
    echo "RESPONSE: $resp\n\n";
}

// Example flow
call('', true); // reset
call('hi');
call('alice');
call('list all courses');
call('What is the price?');
call('How do I enroll?');

?>