<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$BASE_URL = 'https://crmbackend.myaccuratebook.com/api/admin';
$AUTH_TOKEN = 'zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz';

function uploadLeadFile($file, $source, $token) {
    global $BASE_URL, $AUTH_TOKEN;
    
    $url = $BASE_URL . '/leads/import?source=' . urlencode($source) . '&token=' . urlencode($token);
    
    $ch = curl_init($url);
    
    // Prepare file for upload
    $cFile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    
    $postData = [
        'file' => $cFile
    ];
    
    $headers = [
        'Authorization: ' . $AUTH_TOKEN
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes timeout for large files
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => 'Upload failed: ' . $error, 'httpCode' => $httpCode];
    }
    
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid response from server', 'raw' => $response];
    }
    
    return $decoded;
}

// Main execution
try {
    // Validate token
    $token = $_POST['token'] ?? '';
    if (empty($token)) {
        echo json_encode(['error' => 'Authentication token required']);
        exit;
    }
    
    // Validate source
    $source = $_POST['source'] ?? '';
    if (!in_array($source, ['meta', 'tiktok'])) {
        echo json_encode(['error' => 'Invalid source. Must be "meta" or "tiktok"']);
        exit;
    }
    
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'No file uploaded';
        if (isset($_FILES['file']['error'])) {
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'File is too large';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'File was only partially uploaded';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No file was uploaded';
                    break;
                default:
                    $errorMsg = 'File upload error';
            }
        }
        echo json_encode(['error' => $errorMsg]);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Validate file type
    $allowedMimes = [
        'text/csv',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream'
    ];
    
    $fileName = strtolower($file['name']);
    $isValidExtension = preg_match('/\.(csv|xlsx|xls)$/', $fileName);
    
    if (!$isValidExtension) {
        echo json_encode(['error' => 'Invalid file type. Only CSV and Excel files are allowed']);
        exit;
    }
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => 'File is too large. Maximum size is 10MB']);
        exit;
    }
    
    // Upload to backend
    $result = uploadLeadFile($file, $source, $token);
    
    // Return result
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>