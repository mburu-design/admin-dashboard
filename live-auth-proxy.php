<?php
/**
 * Live Server Authentication Proxy
 * Handles authentication for live server deployment
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Get the request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        exit();
    }

    $action = $data['action'] ?? 'login';

    if ($action === 'login') {
        // Handle login
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Email and password are required']);
            exit();
        }

        // Make request to the actual API
        $apiUrl = 'https://core.myaccuratebook.com/admin/login';
        $authToken = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";

        $loginData = json_encode([
            'email' => $email,
            'password' => $password
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $loginData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: ' . $authToken
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            echo json_encode(['success' => false, 'error' => 'API request failed: ' . $error]);
            exit();
        }

        if ($httpCode === 200) {
            $loginResult = json_decode($response, true);
            if ($loginResult && isset($loginResult['token'])) {
                echo json_encode([
                    'success' => true,
                    'token' => $loginResult['token'],
                    'message' => $loginResult['message'] ?? 'Login successful'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid login response']);
            }
        } else {
            $errorResponse = json_decode($response, true);
            echo json_encode([
                'success' => false, 
                'error' => $errorResponse['message'] ?? 'Login failed'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
</content>
</invoke>