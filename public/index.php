<?php
require __DIR__ . '/vendor/autoload.php';

use App\OtpController;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set header JSON
header('Content-Type: application/json');

// Ambil header X-User-Id
$userHeader = $_SERVER['HTTP_X_USER_ID'] ?? null;
if (! $userHeader || ! ctype_digit($userHeader)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Missing or invalid X-User-Id header']));
}
$userId = (int)$userHeader;

// Routing sederhana
$uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];
$ctrl   = new OtpController();

if ($uri === '/otp/generate' && $method === 'POST') {
    $ctrl->generate($userId);
    exit;
}

if ($uri === '/otp/validate' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $code = $body['otp_code'] ?? '';
    if (! is_string($code) || ! preg_match('/^\d{'.getenv('OTP_LENGTH').'}$/', $code)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid OTP format']));
    }
    $ctrl->validate($userId, $code);
    exit;
}

// 404 jika tidak ada route
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);