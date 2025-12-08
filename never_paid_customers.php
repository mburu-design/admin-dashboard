<?php
/**
 * Never Paid Customers Analysis API
 * Identifies customers who joined, used the system, but never made any payment
 * and their subscription has expired
 * 
 * Logic: 
 * 1. Group all subscriptions by businessId
 * 2. Check if ANY subscription has paid billing history
 * 3. Only include customers where ALL subscriptions have no payment
 * 4. Check if subscription end date has expired
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Handle fatal errors
function handleFatalError() {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        echo json_encode([
            'success' => false, 
            'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
}
register_shutdown_function('handleFatalError');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    
    try {
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            echo json_encode(['success' => false, 'error' => 'No input received']);
            exit;
        }
        
        $input = json_decode($rawInput, true);
        
        if (!$input) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
            exit;
        }
        
        $action = $input['action'] ?? '';
        
        // Handle test action
        if ($action === 'test') {
            echo json_encode(['success' => true, 'message' => 'PHP script is working correctly']);
            exit;
        }
        
        // Handle login action
        if ($action === 'login') {
            $loginUrl = "https://core.myaccuratebook.com/admin/login";
            $authToken = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";
            
            $loginData = json_encode([
                'email' => 'myaccuratebooks@gmail.com',
                'password' => 'Admin2024#'
            ]);
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $loginUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $loginData,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authToken
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo json_encode(['success' => false, 'error' => 'Login request failed: ' . $error]);
                exit;
            }
            
            if ($httpCode !== 200) {
                echo json_encode(['success' => false, 'error' => 'Login failed with status code: ' . $httpCode]);
                exit;
            }
            
            $loginResult = json_decode($response, true);
            
            if ($loginResult && isset($loginResult['token'])) {
                echo json_encode(['success' => true, 'token' => $loginResult['token']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid login response']);
            }
            exit;
        }
        
        // Handle analyze action
        if ($action === 'analyze') {
            $token = $input['token'] ?? '';
            $expiredPeriod = $input['expiredPeriod'] ?? 'all';
            $sortBy = $input['sortBy'] ?? 'expired_date_desc';
            
            if (empty($token)) {
                echo json_encode(['success' => false, 'error' => 'Token is required']);
                exit;
            }
            
            // Fetch all subscriptions
            $apiUrl = "https://core.myaccuratebook.com/admin/subscriptions/all?token=" . urlencode($token);
            $authToken = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authToken
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                echo json_encode(['success' => false, 'error' => 'API request failed: ' . $error]);
                exit;
            }
            
            if ($httpCode !== 200) {
                echo json_encode(['success' => false, 'error' => 'API request failed with status: ' . $httpCode]);
                exit;
            }
            
            $subscriptionData = json_decode($response, true);
            
            if (!$subscriptionData || !isset($subscriptionData['rows'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid API response format']);
                exit;
            }
            
            $allSubscriptions = $subscriptionData['rows'];
            $today = new DateTime();
            
            // Group all subscriptions by business
            $businessData = [];
            
            foreach ($allSubscriptions as $subscription) {
                $businessId = $subscription['business_id'] ?? '';
                if (empty($businessId)) continue;
                
                $business = $subscription['Business'] ?? [];
                $package = $subscription['Package'] ?? [];
                $packageName = $package['name'] ?? 'Unknown';
                $billingHistories = $subscription['BillingHistories'] ?? [];
                $subscriptionEnd = $subscription['end_date'] ?? '';
                $subscriptionStart = $subscription['start_date'] ?? $subscription['createdAt'] ?? '';
                
                if (!isset($businessData[$businessId])) {
                    $businessData[$businessId] = [
                        'businessId' => $businessId,
                        'businessName' => $business['company_name'] ?? 'N/A',
                        'email' => $business['company_email'] ?? 'N/A',
                        'phone' => $business['phone_number'] ?? 'N/A',
                        'createdAt' => $business['createdAt'] ?? 'N/A',
                        'subscriptions' => [],
                        'hasPaidAnywhere' => false,
                        'totalSubscriptions' => 0
                    ];
                }
                
                $businessData[$businessId]['totalSubscriptions']++;
                
                // Check if this subscription has any paid billing history
                $hasPaidBilling = false;
                foreach ($billingHistories as $billing) {
                    if (isset($billing['status']) && $billing['status'] === 'paid') {
                        $hasPaidBilling = true;
                        $businessData[$businessId]['hasPaidAnywhere'] = true;
                        break;
                    }
                }
                
                // Store subscription details
                $businessData[$businessId]['subscriptions'][] = [
                    'packageName' => $packageName,
                    'subscriptionStart' => $subscriptionStart,
                    'subscriptionEnd' => $subscriptionEnd,
                    'hasPaidBilling' => $hasPaidBilling,
                    'billingHistoryCount' => count($billingHistories)
                ];
            }
            
            // Analyze each business to find those who never paid and subscription expired
            $neverPaidCustomers = [];
            
            foreach ($businessData as $businessId => $business) {
                // Skip if customer has paid on ANY subscription
                if ($business['hasPaidAnywhere']) {
                    continue;
                }
                
                // Find the latest subscription end date
                $latestEndDate = null;
                $earliestStartDate = null;
                $packagesList = [];
                
                foreach ($business['subscriptions'] as $sub) {
                    $packagesList[] = $sub['packageName'];
                    
                    if (!empty($sub['subscriptionStart'])) {
                        try {
                            $startDate = new DateTime($sub['subscriptionStart']);
                            if ($earliestStartDate === null || $startDate < $earliestStartDate) {
                                $earliestStartDate = $startDate;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                    
                    if (!empty($sub['subscriptionEnd'])) {
                        try {
                            $endDate = new DateTime($sub['subscriptionEnd']);
                            if ($latestEndDate === null || $endDate > $latestEndDate) {
                                $latestEndDate = $endDate;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                }
                
                // Skip if no valid end date found
                if ($latestEndDate === null) {
                    continue;
                }
                
                // Check if subscription has expired
                if ($latestEndDate > $today) {
                    // Still active, skip
                    continue;
                }
                
                // Customer never paid and subscription expired!
                $daysSinceExpiry = $today->diff($latestEndDate)->days;
                
                // Apply expired period filter
                if ($expiredPeriod !== 'all') {
                    $periodDays = intval($expiredPeriod);
                    if ($daysSinceExpiry > $periodDays) {
                        continue;
                    }
                }
                
                // Calculate days used (from start to end)
                $daysUsed = 0;
                if ($earliestStartDate !== null && $latestEndDate !== null) {
                    $daysUsed = $earliestStartDate->diff($latestEndDate)->days;
                }
                
                $neverPaidCustomers[] = [
                    'businessId' => $businessId,
                    'businessName' => $business['businessName'],
                    'email' => $business['email'],
                    'phone' => $business['phone'],
                    'createdAt' => $business['createdAt'],
                    'packages' => implode(', ', array_unique($packagesList)),
                    'totalSubscriptions' => $business['totalSubscriptions'],
                    'subscriptionStart' => $earliestStartDate ? $earliestStartDate->format('Y-m-d') : 'N/A',
                    'subscriptionEnd' => $latestEndDate->format('Y-m-d'),
                    'daysSinceExpiry' => $daysSinceExpiry,
                    'daysUsed' => $daysUsed,
                    'endDateTimestamp' => $latestEndDate->getTimestamp()
                ];
            }
            
            // Sort results
            usort($neverPaidCustomers, function($a, $b) use ($sortBy) {
                switch ($sortBy) {
                    case 'expired_date_desc':
                        return $b['endDateTimestamp'] - $a['endDateTimestamp'];
                    case 'expired_date_asc':
                        return $a['endDateTimestamp'] - $b['endDateTimestamp'];
                    case 'days_used_desc':
                        return $b['daysUsed'] - $a['daysUsed'];
                    case 'days_used_asc':
                        return $a['daysUsed'] - $b['daysUsed'];
                    case 'name_asc':
                        return strcmp($a['businessName'], $b['businessName']);
                    case 'name_desc':
                        return strcmp($b['businessName'], $a['businessName']);
                    default:
                        return $b['endDateTimestamp'] - $a['endDateTimestamp'];
                }
            });
            
            // Calculate summary statistics
            $totalNeverPaid = count($neverPaidCustomers);
            $totalDaysUsed = 0;
            $totalDaysSinceExpiry = 0;
            $recentExpiries = 0;
            
            foreach ($neverPaidCustomers as $customer) {
                $totalDaysUsed += $customer['daysUsed'];
                $totalDaysSinceExpiry += $customer['daysSinceExpiry'];
                
                if ($customer['daysSinceExpiry'] <= 30) {
                    $recentExpiries++;
                }
            }
            
            $avgDaysUsed = $totalNeverPaid > 0 ? round($totalDaysUsed / $totalNeverPaid) : 0;
            $avgDaysSinceExpiry = $totalNeverPaid > 0 ? round($totalDaysSinceExpiry / $totalNeverPaid) : 0;
            
            // Remove timestamp from customer data before sending
            foreach ($neverPaidCustomers as &$customer) {
                unset($customer['endDateTimestamp']);
            }
            
            echo json_encode([
                'success' => true,
                'totalNeverPaid' => $totalNeverPaid,
                'avgDaysUsed' => $avgDaysUsed,
                'avgDaysSinceExpiry' => $avgDaysSinceExpiry,
                'recentExpiries' => $recentExpiries,
                'customers' => $neverPaidCustomers
            ]);
            exit;
        }
        
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    } catch (Error $e) {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}
?>