<?php
session_start();
header('Content-Type: application/json');

// Load course data to make the bot "smarter"
$all_courses = include __DIR__ . '/study_content.php';

// Load small knowledge base for generic Q/A (created by trainer)
$kb_path = __DIR__ . '/ai_knowledge.json';
if (!file_exists($kb_path)) {
    file_put_contents($kb_path, json_encode([]));
}
$kb = json_decode(file_get_contents($kb_path), true) ?? [];

// Simulate a small delay to make it feel more real
usleep(500000);

$input = json_decode(file_get_contents('php://input'), true);
$message = strtolower(trim($input['message'] ?? ''));
// Conversation state stored in session
$conv = &$_SESSION['ai_conv'];

// If client requests reset
if (isset($input['reset']) && $input['reset']) {
    $conv = null;
    echo json_encode(['reply' => 'Conversation reset. Hello! What is your name?', 'next_question' => 'What is your name?', 'expecting' => 'name']);
    exit();
}

if (empty($message)) {
    echo json_encode(['reply' => 'Please say something so I can help.', 'next_question' => 'You can say "hi" or ask about a course', 'expecting' => 'any']);
    exit();
}

// --- Start of logic ---
$reply = null;
$next_question = null;
$expecting = null; // what the bot expects next: name/intent/course/any

// 1. Handle Greetings
if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false) {
    // Start a new conversation if none
    if (empty($conv)) {
        $conv = ['stage' => 'ask_name'];
        $reply = "Hi! I don't think we've met. What's your name?";
        $next_question = "What's your name?";
        $expecting = 'name';
    } else {
        $user_name = $_SESSION['user_name'] ?? 'there';
        $reply = "Hello, $user_name! How can I help you with our courses today? You can ask me to list all courses, or ask about a specific topic like 'PHP' or 'Data Science'.";
        $next_question = 'You can ask about courses or say "list all courses"';
        $expecting = 'intent';
    }
}

// KB matching: exact, substring, fuzzy (only if no reply yet)
if (!$reply && !empty($kb)) {
    // exact match
    foreach ($kb as $item) {
        if (strtolower(trim($item['q'])) === $message) {
            $reply = $item['a'];
            $next_question = 'Anything else I can help with?';
            $expecting = 'any';
            break;
        }
    }
}

if (!$reply && !empty($kb)) {
    // substring match (question contains keyword that matches question text)
    foreach ($kb as $item) {
        if ($item['q'] && strpos($message, strtolower($item['q'])) !== false) {
            $reply = $item['a'];
            $next_question = 'Would you like more details?';
            $expecting = 'any';
            break;
        }
    }
}

if (!$reply && !empty($kb)) {
    // fuzzy match using levenshtein for small strings
    $best = null;
    $bestDist = PHP_INT_MAX;
    foreach ($kb as $item) {
        $q = strtolower(trim($item['q']));
        if ($q === '') continue;
        $dist = levenshtein($q, $message);
        if ($dist < $bestDist) {
            $bestDist = $dist;
            $best = $item;
        }
    }
    // choose fuzzy result only if reasonably close
    if ($best && $bestDist <= max(2, (int)(strlen($message) * 0.25))) {
        $reply = $best['a'];
        $next_question = 'Did that answer your question?';
        $expecting = 'any';
    }
}

// 2. List all courses
if (!$reply && (strpos($message, 'list all') !== false || strpos($message, 'all courses') !== false || $message === 'courses')) {
    $course_titles = array_map(fn($c) => $c['title'], $all_courses);
    $reply = "We offer the following courses: " . implode(', ', $course_titles) . ". Which one would you like to know more about?";
    $next_question = 'Which course would you like to know more about?';
    $expecting = 'course';
}

// 3. Check for questions about a specific course
if (!$reply) {
    foreach ($all_courses as $slug => $details) {
        // Check if the course title is in the message
        if (strpos($message, strtolower($details['title'])) !== false) {
            if (strpos($message, 'prerequisite') !== false) {
                $reply = "The prerequisites for '{$details['title']}' are: " . implode(', ', $details['prerequisites']) . ".";
            } elseif (strpos($message, 'duration') !== false || strpos($message, 'how long') !== false) {
                $reply = "The '{$details['title']}' course takes about {$details['duration']} to complete.";
            } elseif (strpos($message, 'instructor') !== false || strpos($message, 'who teaches') !== false) {
                $reply = "'{$details['title']}' is taught by {$details['instructor']}.";
            } else {
                // General description if no specific question
                $reply = "The '{$details['title']}' course is about: {$details['description']}";
                $next_question = 'Would you like to enroll or hear about prerequisites?';
                $expecting = 'action';
            }
            break; // Found a match, stop searching
        }
    }
}

// 4. If no specific course was asked about, check for keywords
if (!$reply) {
    $keywords = ['html', 'css', 'javascript', 'php', 'mysql', 'python', 'data science', 'design', 'ui', 'ux', 'marketing', 'seo'];
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $found_courses = [];
            foreach ($all_courses as $slug => $details) {
                $search_area = strtolower($details['title'] . ' ' . implode(' ', $details['tags']));
                if (strpos($search_area, $keyword) !== false) {
                    $found_courses[] = "'{$details['title']}'";
                }
            }
            if (!empty($found_courses)) {
                $reply = "For '{$keyword}', you might be interested in: " . implode(' or ', $found_courses) . ".";
                $next_question = 'Would you like details on any of these courses?';
                $expecting = 'course';
                break;
            }
        }
    }
}

// 5. Handle generic fallback questions
if (!$reply) {
    // Check if we are expecting a name from the conversation state
    if (!empty($conv) && ($conv['stage'] ?? '') === 'ask_name' && $message !== '') {
        // Save name and prompt for intent
        $_SESSION['user_name'] = ucwords($message);
        $conv['stage'] = 'ask_intent';
        $reply = "Nice to meet you, {$_SESSION['user_name']}! What would you like help with today? (e.g., list courses, find PHP courses, pricing)";
        $next_question = 'What do you want help with?';
        $expecting = 'intent';
    } else {
    if (strpos($message, 'price') !== false || strpos($message, 'cost') !== false) {
        $reply = "All our courses are available for enrollment upon registration. For specific pricing details, please check the main course catalog or contact support.";
            $next_question = 'Would you like help finding pricing for a specific course?';
            $expecting = 'course';
    } elseif (strpos($message, 'support') !== false || strpos($message, 'help') !== false) {
        $reply = "If you need support, you can use the contact form on our homepage or email us directly at support@lms.com.";
            $next_question = 'Do you want the contact page or an email address?';
            $expecting = 'contact';
    } elseif (strpos($message, 'thank') !== false) {
        $reply = "You're welcome! Let me know if you have any other questions.";
            $next_question = 'Anything else I can help with?';
            $expecting = 'any';
    }
    }
}

// 6. Default "I don't know" response
if (!$reply) {
    $reply = "I'm sorry, I can only answer basic questions about our courses. For more detailed help, please use the contact form.";
    // provide a gentle prompt so the client UI can continue the flow
    if (!$next_question) $next_question = 'Would you like to contact support or browse courses?';
    if (!$expecting) $expecting = 'any';
}

$payload = ['reply' => $reply];
if ($next_question) $payload['next_question'] = $next_question;
if ($expecting) $payload['expecting'] = $expecting;

echo json_encode($payload);
?>