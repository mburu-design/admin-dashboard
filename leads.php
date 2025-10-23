<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$BASE_URL = 'https://crmbackend.myaccuratebook.com/api/admin';
$AUTH_TOKEN = 'zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz';

function makeRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    global $BASE_URL, $AUTH_TOKEN;
    
    $url = $BASE_URL . $endpoint;
    if ($token && $method === 'GET') {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'token=' . $token;
    }
    
    $ch = curl_init($url);
    
    $headers = [
        'Authorization: ' . $AUTH_TOKEN,
        'Content-Type: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    if ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => $error, 'httpCode' => $httpCode];
    }
    
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response', 'raw' => $response];
    }
    
    return $decoded;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$token = $input['token'] ?? '';

switch ($action) {
    case 'fetchLeads':
        $stage = $input['stage'] ?? '';
        $startDate = $input['startDate'] ?? '';
        $endDate = $input['endDate'] ?? '';
        $search = $input['search'] ?? '';
        
        $endpoint = '/leads/getAll';
        $result = makeRequest($endpoint, 'GET', null, $token);
        
        if (isset($result['error'])) {
            echo json_encode(['error' => $result['error']]);
            exit;
        }
        
        $leads = $result['data'] ?? [];
        
        // Apply filters
        if ($stage) {
            $leads = array_filter($leads, function($lead) use ($stage) {
                return $lead['stage'] === $stage;
            });
        }
        
        if ($startDate) {
            $leads = array_filter($leads, function($lead) use ($startDate) {
                return strtotime($lead['created_at']) >= strtotime($startDate);
            });
        }
        
        if ($endDate) {
            $leads = array_filter($leads, function($lead) use ($endDate) {
                // Add 23:59:59 to end date to include the entire day
                return strtotime($lead['created_at']) <= strtotime($endDate . ' 23:59:59');
            });
        }
        
        if ($search) {
            $search = strtolower($search);
            $leads = array_filter($leads, function($lead) use ($search) {
                return stripos($lead['name'], $search) !== false ||
                       stripos($lead['email'], $search) !== false ||
                       stripos($lead['phone'], $search) !== false;
            });
        }
        
        $leads = array_values($leads); // Re-index array
        
        // Generate stats
        $stats = [
            'total' => count($leads),
            'new' => count(array_filter($leads, fn($l) => $l['stage'] === 'new')),
            'assigned' => count(array_filter($leads, fn($l) => $l['stage'] === 'assigned')),
            'inProcess' => count(array_filter($leads, fn($l) => $l['stage'] === 'in_process')),
            'converted' => count(array_filter($leads, fn($l) => $l['stage'] === 'converted')),
            'recycled' => count(array_filter($leads, fn($l) => $l['stage'] === 'recycled')),
            'dead' => count(array_filter($leads, fn($l) => $l['stage'] === 'dead')),
            'contacted' => count(array_filter($leads, fn($l) => $l['isContacted'] === true))
        ];
        
        echo json_encode([
            'success' => true,
            'leads' => $leads,
            'stats' => $stats
        ]);
        break;
        
    case 'updateLead':
        $leadId = $input['leadId'] ?? '';
        $updateData = $input['data'] ?? [];
        
        if (!$leadId || empty($updateData)) {
            echo json_encode(['error' => 'Lead ID and update data required']);
            exit;
        }
        
        $endpoint = '/leads/update/' . $leadId;
        $result = makeRequest($endpoint, 'PATCH', $updateData, $token);
        
        echo json_encode($result);
        break;

    case 'getAdmins':
        $endpoint = '/getAllAdmin';
        $result = makeRequest($endpoint, 'GET', null, $token);
        
        if (isset($result['error'])) {
            echo json_encode(['error' => $result['error']]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'admins' => $result['data'] ?? []
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>