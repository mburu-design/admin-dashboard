<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $baseURL = "https://core.myaccuratebook.com";
    
    // Handle both JSON and form data
    $input = null;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON input
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $startDate = $input['startDate'] ?? '';
        $endDate = $input['endDate'] ?? '';
        $token = $input['token'] ?? '';
        
        // If no token in body, check Authorization header
        if (empty($token)) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
            }
        }
    } else {
        // Form data
        $startDate = $_POST['startDate'] ?? '';
        $endDate = $_POST['endDate'] ?? '';
        $token = $_POST['token'] ?? '';
    }
    
    $logType = "login"; // Fixed to login
    $tokenN = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";

    $apiUrl = "$baseURL/getlogs/query?endDate=$endDate&startDate=$startDate&logType=$logType&token=$token";

    $headers = [
        "Authorization: $tokenN"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    
    if (curl_error($ch)) {
        echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    
    curl_close($ch);

    $responseData = json_decode($response, true);
    
    if (!$responseData || !isset($responseData['logs'])) {
        echo json_encode(['error' => 'Invalid API response']);
        exit;
    }

    $logs = $responseData['logs'];

    // Arrays to store analytics data
    $uniqueEmails = [];
    $successfulLogins = 0;
    $failedLogins = 0;
    $logRows = '';

    // Process each log entry
    foreach ($logs as $log) {
        $email = $log['email'] ?? 'N/A';
        $status = $log['status'] ?? 'N/A';
        $message = $log['message'] ?? 'N/A';
        $timestamp = $log['timestamp'] ?? 'N/A';
        $ip = $log['ip'] ?? 'N/A';
        $reason = $log['reason'] ?? 'N/A';

        // Build the log table rows
        $logRows .= "<tr>";
        $logRows .= "<td>" . htmlspecialchars($email) . "</td>";
        $logRows .= "<td>" . htmlspecialchars($status) . "</td>";
        $logRows .= "<td>" . htmlspecialchars($message) . "</td>";
        $logRows .= "<td>" . htmlspecialchars($timestamp) . "</td>";
        $logRows .= "<td>" . htmlspecialchars($ip) . "</td>";
        $logRows .= "<td>" . htmlspecialchars($reason) . "</td>";
        $logRows .= "</tr>";

        // Collect unique emails (only if email is not N/A)
        if ($email !== 'N/A' && !empty($email)) {
            $uniqueEmails[$email] = true;
        }

        // Count successful and failed logins
        if (strtoupper($status) === 'SUCCESS') {
            $successfulLogins++;
        } elseif (strtoupper($status) === 'FAILED') {
            $failedLogins++;
        }
    }

    // Build email table rows
    $emailRows = '';
    foreach (array_keys($uniqueEmails) as $email) {
        $emailRows .= "<tr>";
        $emailRows .= "<td>" . htmlspecialchars($email) . "</td>";
        $emailRows .= "</tr>";
    }

    // Prepare response data
    $responseData = [
        'logRows' => $logRows,
        'uniqueUserCount' => count($uniqueEmails),
        'successfulLogins' => $successfulLogins,
        'failedLogins' => $failedLogins,
        'emailRows' => $emailRows
    ];

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($responseData);
}
?>