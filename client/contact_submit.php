<?php
// ...new file...
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// read JSON or fallback to form-encoded
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$subject = trim($input['subject'] ?? 'Contact form submission');
$message = trim($input['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name, email and message are required.']);
    exit;
}

// basic email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}

// prevent header injection
$name = str_replace(["\r","\n"], [' ',' '], $name);
$subject = str_replace(["\r","\n"], [' ',' '], $subject);

// change this to your support address
$to = 'support@lms.com';
$body = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message\n\nSent: " . date('c') . "\n";

// Try to send email (may require SMTP configured in XAMPP)
$headers = "From: {$name} <{$email}>\r\nReply-To: {$email}\r\n";

$mailSent = false;
if (function_exists('mail')) {
    // suppress warning if mail not configured
    $mailSent = @mail($to, $subject, $body, $headers);
}

if ($mailSent) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
    exit;
}

// fallback: save to a server-side file (useful if mail() not configured)
$logDir = __DIR__ . '/submissions';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$filename = $logDir . '/contacts_' . date('Y-m-d') . '.log';
$entry = "----\n" . $body;
file_put_contents($filename, $entry, FILE_APPEND | LOCK_EX);

// respond success (admin will see file)
echo json_encode(['success' => true, 'message' => 'Message saved. We will review and get back to you.']);
exit;
?>