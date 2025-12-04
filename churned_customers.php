<?php
/**
 * Churned Customers Analysis API
 * Identifies paying customers who did not renew their subscriptions
 * 
 * Logic: Accumulates all payment frequencies from the first payment date.
 * expectedEndDate = firstPaymentDate + sum(all billing frequencies)
 * If calculated end date is in the past = churned customer.
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

/**
 * Get billing frequency (in months) based on payment amount
 * Returns array with months to add and plan name
 */
function getBillingFrequencyFromAmount($amount) {
    $amount = floatval($amount);
    
    // Price to billing frequency mapping
    $priceMap = [
        0 => ['months' => 1, 'plan' => 'Free Monthly'],
        1 => ['months' => 12, 'plan' => 'Test Payment'], // For test payments like KSH 1
        500 => ['months' => 1, 'plan' => 'Standard Monthly'],
        800 => ['months' => 1, 'plan' => 'Gold Monthly'],
        1400 => ['months' => 3, 'plan' => 'Standard Quarterly'],
        2200 => ['months' => 3, 'plan' => 'Gold Quarterly'],
        2800 => ['months' => 6, 'plan' => 'Standard Semi-annually'],
        4600 => ['months' => 6, 'plan' => 'Gold Semi-annually'],
        5500 => ['months' => 12, 'plan' => 'Standard Annually'],
        9000 => ['months' => 12, 'plan' => 'Gold Annually'],
    ];
    
    // Exact match
    if (isset($priceMap[$amount])) {
        return $priceMap[$amount];
    }
    
    // Find closest match (for amounts that might be slightly different)
    $closestAmount = null;
    $closestDiff = PHP_INT_MAX;
    
    foreach (array_keys($priceMap) as $price) {
        $diff = abs($price - $amount);
        if ($diff < $closestDiff && $diff <= 50) { // Allow 50 KSH tolerance
            $closestDiff = $diff;
            $closestAmount = $price;
        }
    }
    
    if ($closestAmount !== null) {
        return $priceMap[$closestAmount];
    }
    
    // Default to monthly if unknown amount
    return ['months' => 1, 'plan' => 'Unknown Plan'];
}

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
            $churnPeriod = $input['churnPeriod'] ?? 'all';
            $packageFilter = $input['packageFilter'] ?? 'all';
            $sortBy = $input['sortBy'] ?? 'churn_date_desc';
            
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
            
            // Group all billing histories by business
            $businessData = [];
            $allPackages = [];
            
            foreach ($allSubscriptions as $subscription) {
                $businessId = $subscription['business_id'] ?? '';
                if (empty($businessId)) continue;
                
                
                $business = $subscription['Business'] ?? [];
                $package = $subscription['Package'] ?? [];
                $packageName = $package['name'] ?? 'Unknown';
                $billingHistories = $subscription['BillingHistories'] ?? [];
                $package_id= $subscription['package_id'];
                 if($package_id > 1 )
                    continue;
                // Track packages
                if (!in_array($packageName, $allPackages) && $packageName !== 'Unknown') {
                    $allPackages[] = $packageName;
                }
                
                if (!isset($businessData[$businessId])) {
                    $businessData[$businessId] = [
                        'businessId' => $businessId,
                        'businessName' => $business['company_name'] ?? 'N/A',
                        'email' => $business['company_email'] ?? 'N/A',
                        'phone' => $business['phone_number'] ?? 'N/A',
                        'allBillingHistories' => [],
                        'packages' => []
                    ];
                }
                
                $businessData[$businessId]['packages'][] = $packageName;
                
                // Collect all paid billing histories
                foreach ($billingHistories as $billing) {
                    if (isset($billing['status']) && $billing['status'] === 'paid') {
                        $businessData[$businessId]['allBillingHistories'][] = $billing;
                    }
                }
            }
            
            // Analyze each business to determine if they're churned
            $churnedCustomers = [];
            $packageChurnCount = [];
            
            foreach ($businessData as $businessId => $business) {
                $billingHistories = $business['allBillingHistories'];
                
                // Skip businesses that have never paid
                if (empty($billingHistories)) {
                    continue;
                }
                if
                
                // Sort billing histories by date (oldest first)
                usort($billingHistories, function($a, $b) {
                    $dateA = $a['billingDate'] ?? $a['createdAt'] ?? '';
                    $dateB = $b['billingDate'] ?? $b['createdAt'] ?? '';
                    return strcmp($dateA, $dateB);
                });
                
                // Calculate subscription end by accumulating all payments
                $firstPaymentDate = null;
                $lastPaymentDate = null;
                $lastPaymentAmount = 0;
                $lastPlan = 'Unknown';
                $totalMonths = 0;
                $totalRevenue = 0;
                $totalPayments = count($billingHistories);
                
                foreach ($billingHistories as $billing) {
                    $amount = floatval($billing['amount'] ?? 0);
                    $totalRevenue += $amount;
                    
                    $billingDateRaw = $billing['billingDate'] ?? $billing['createdAt'] ?? '';
                    if (!empty($billingDateRaw)) {
                        try {
                            $billingDate = new DateTime($billingDateRaw);
                            
                            // Track first payment date
                            if ($firstPaymentDate === null) {
                                $firstPaymentDate = $billingDate;
                            }
                            
                            // Track last payment
                            $lastPaymentDate = $billingDate;
                            $lastPaymentAmount = $amount;
                            
                            // Get billing frequency and accumulate months
                            $frequency = getBillingFrequencyFromAmount($amount);
                            $totalMonths += $frequency['months'];
                            $lastPlan = $frequency['plan'];
                            
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                }
                
                // Must have a valid first payment to analyze
                if ($firstPaymentDate === null || $totalMonths === 0) {
                    continue;
                }
                
                // Calculate expected subscription end date
                // expectedEndDate = firstPaymentDate + totalMonths
                $calculatedEndDate = clone $firstPaymentDate;
                $calculatedEndDate->add(new DateInterval('P' . $totalMonths . 'M'));
                
                // Determine package name from last plan
                $lastPackage = 'Unknown';
                if (strpos($lastPlan, 'Standard') !== false) {
                    $lastPackage = 'Standard Package';
                } elseif (strpos($lastPlan, 'Gold') !== false) {
                    $lastPackage = 'Gold Package';
                } elseif (strpos($lastPlan, 'Free') !== false) {
                    $lastPackage = 'Free Package';
                }
                
                // Check if subscription has expired (calculated end date is in the past)
                if ($calculatedEndDate > $today) {
                    // Customer is still active, skip
                    continue;
                }
                
                // Customer has churned!
                $daysSinceChurn = $today->diff($calculatedEndDate)->days;
                
                // Apply churn period filter
                if ($churnPeriod !== 'all') {
                    $periodDays = intval($churnPeriod);
                    if ($daysSinceChurn > $periodDays) {
                        continue;
                    }
                }
                
                // Apply package filter
                if ($packageFilter !== 'all' && $lastPackage !== $packageFilter) {
                    continue;
                }
                
                // Calculate tenure (first payment to calculated end date)
                $tenure = $firstPaymentDate->diff($calculatedEndDate)->days;
                
                // Track package churn count
                if (!isset($packageChurnCount[$lastPackage])) {
                    $packageChurnCount[$lastPackage] = 0;
                }
                $packageChurnCount[$lastPackage]++;
                
                $churnedCustomers[] = [
                    'businessId' => $businessId,
                    'businessName' => $business['businessName'],
                    'email' => $business['email'],
                    'phone' => $business['phone'],
                    'lastPackage' => $lastPackage,
                    'lastPlan' => $lastPlan,
                    'lastPaymentAmount' => $lastPaymentAmount,
                    'firstPaymentDate' => $firstPaymentDate->format('Y-m-d'),
                    'lastPaymentDate' => $lastPaymentDate ? $lastPaymentDate->format('Y-m-d') : 'N/A',
                    'lastSubscriptionEnd' => $calculatedEndDate->format('Y-m-d'),
                    'daysSinceChurn' => $daysSinceChurn,
                    'totalPayments' => $totalPayments,
                    'totalMonthsPurchased' => $totalMonths,
                    'totalRevenue' => $totalRevenue,
                    'tenure' => $tenure,
                    'lastEndDateTimestamp' => $calculatedEndDate->getTimestamp()
                ];
            }
            
            // Sort results
            usort($churnedCustomers, function($a, $b) use ($sortBy) {
                switch ($sortBy) {
                    case 'churn_date_desc':
                        return $b['lastEndDateTimestamp'] - $a['lastEndDateTimestamp'];
                    case 'churn_date_asc':
                        return $a['lastEndDateTimestamp'] - $b['lastEndDateTimestamp'];
                    case 'revenue_desc':
                        return $b['totalRevenue'] - $a['totalRevenue'];
                    case 'revenue_asc':
                        return $a['totalRevenue'] - $b['totalRevenue'];
                    case 'tenure_desc':
                        return $b['tenure'] - $a['tenure'];
                    case 'tenure_asc':
                        return $a['tenure'] - $b['tenure'];
                    default:
                        return $b['lastEndDateTimestamp'] - $a['lastEndDateTimestamp'];
                }
            });
            
            // Calculate summary statistics
            $totalChurned = count($churnedCustomers);
            $totalRevenue = 0;
            $totalTenure = 0;
            $totalDaysSinceChurn = 0;
            $recentChurns = 0;
            
            foreach ($churnedCustomers as $customer) {
                $totalRevenue += $customer['totalRevenue'];
                $totalTenure += $customer['tenure'];
                $totalDaysSinceChurn += $customer['daysSinceChurn'];
                
                if ($customer['daysSinceChurn'] <= 30) {
                    $recentChurns++;
                }
            }
            
            $avgRevenue = $totalChurned > 0 ? round($totalRevenue / $totalChurned, 2) : 0;
            $avgTenure = $totalChurned > 0 ? round($totalTenure / $totalChurned) : 0;
            $avgDaysSinceChurn = $totalChurned > 0 ? round($totalDaysSinceChurn / $totalChurned) : 0;
            
            // Find top churned package
            $topChurnPackage = null;
            if (!empty($packageChurnCount)) {
                arsort($packageChurnCount);
                $topPackageName = array_key_first($packageChurnCount);
                $topChurnPackage = [
                    'name' => $topPackageName,
                    'count' => $packageChurnCount[$topPackageName]
                ];
            }
            
            // Remove timestamp from customer data before sending
            foreach ($churnedCustomers as &$customer) {
                unset($customer['lastEndDateTimestamp']);
            }
            
            sort($allPackages);
            
            echo json_encode([
                'success' => true,
                'totalChurned' => $totalChurned,
                'totalRevenue' => $totalRevenue,
                'avgRevenue' => $avgRevenue,
                'avgTenure' => $avgTenure,
                'avgDaysSinceChurn' => $avgDaysSinceChurn,
                'recentChurns' => $recentChurns,
                'topChurnPackage' => $topChurnPackage,
                'packages' => $allPackages,
                'customers' => $churnedCustomers
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