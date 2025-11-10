<?php
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
        
        // Handle login
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
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authToken
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                echo json_encode(['success' => false, 'error' => 'Login failed']);
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
        
        // Handle analysis
        if ($action === 'analyze') {
            $token = $input['token'] ?? '';
            $filterType = $input['filterType'] ?? '';
            $startDate = $input['startDate'] ?? '';
            $endDate = $input['endDate'] ?? '';
            
            if (empty($token)) {
                echo json_encode(['success' => false, 'error' => 'Token is required']);
                exit;
            }
            
            // Fetch all payments from API
            $apiUrl = "https://core.myaccuratebook.com/admin/getAllSubscriptionPaymnets?token=" . urlencode($token);
            $authToken = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: ' . $authToken
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                echo json_encode(['success' => false, 'error' => 'API request failed']);
                exit;
            }
            
            $paymentData = json_decode($response, true);
            
            if (!$paymentData || !isset($paymentData['data']['transactions'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid API response']);
                exit;
            }
            
            $allTransactions = $paymentData['data']['transactions'];
            
            // Calculate date range
            $today = new DateTime();
            $filterStartDate = null;
            $filterEndDate = null;
            
            switch ($filterType) {
                case 'today':
                    $filterStartDate = clone $today;
                    $filterStartDate->setTime(0, 0, 0);
                    $filterEndDate = clone $today;
                    $filterEndDate->setTime(23, 59, 59);
                    break;
                    
                case 'this_week':
                    $filterStartDate = (clone $today)->modify('monday this week')->setTime(0, 0, 0);
                    $filterEndDate = (clone $today)->modify('sunday this week')->setTime(23, 59, 59);
                    break;
                    
                case 'this_month':
                    $filterStartDate = new DateTime($today->format('Y-m-01 00:00:00'));
                    $filterEndDate = new DateTime($today->format('Y-m-t 23:59:59'));
                    break;
                    
                case 'last_7_days':
                    $filterStartDate = (clone $today)->sub(new DateInterval('P7D'))->setTime(0, 0, 0);
                    $filterEndDate = clone $today;
                    $filterEndDate->setTime(23, 59, 59);
                    break;
                    
                case 'last_30_days':
                    $filterStartDate = (clone $today)->sub(new DateInterval('P30D'))->setTime(0, 0, 0);
                    $filterEndDate = clone $today;
                    $filterEndDate->setTime(23, 59, 59);
                    break;
                    
                case 'custom':
                    if (empty($startDate) || empty($endDate)) {
                        echo json_encode(['success' => false, 'error' => 'Start and end dates required']);
                        exit;
                    }
                    $filterStartDate = new DateTime($startDate . ' 00:00:00');
                    $filterEndDate = new DateTime($endDate . ' 23:59:59');
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid filter type']);
                    exit;
            }
            
            // Group transactions by business ID
            $businessTransactions = [];
            foreach ($allTransactions as $transaction) {
                $businessId = $transaction['businessId'] ?? '';
                if (empty($businessId)) continue;
                
                // Skip if no billing date
                if (empty($transaction['billingDate'])) continue;
                
                if (!isset($businessTransactions[$businessId])) {
                    $businessTransactions[$businessId] = [];
                }
                
                $businessTransactions[$businessId][] = $transaction;
            }
            
            // Analyze First-Time Payments and Renewals
            $conversions = [];
            $renewals = [];
            $totalRevenue = 0;
            $totalTransactionCount = 0;
            $totalRenewalRevenue = 0;
            $totalRenewalTransactionCount = 0;
            
            foreach ($businessTransactions as $businessId => $transactions) {
                // Sort transactions by billing date (oldest first)
                usort($transactions, function($a, $b) {
                    try {
                        $dateA = new DateTime($a['billingDate']);
                        $dateB = new DateTime($b['billingDate']);
                        return $dateA <=> $dateB;
                    } catch (Exception $e) {
                        return 0;
                    }
                });
                
                // Get the very first transaction for this business
                $firstTransaction = $transactions[0];
                
                try {
                    $firstTransactionDate = new DateTime($firstTransaction['billingDate']);
                    
                    // Check if the first transaction falls within our date range (FIRST-TIME CUSTOMER)
                    if ($firstTransactionDate >= $filterStartDate && $firstTransactionDate <= $filterEndDate) {
                        $conversions[] = [
                            'businessId' => $businessId,
                            'businessName' => $firstTransaction['businessName'] ?? 'N/A',
                            'email' => $firstTransaction['email'] ?? 'N/A',
                            'phone' => $firstTransaction['phoneNumber'] ?? 'N/A',
                            'firstPaymentDate' => $firstTransaction['billingDate'],
                            'amount' => floatval($firstTransaction['amount'] ?? 0),
                            'paymentMethod' => $firstTransaction['paymentMethod'] ?? 'N/A',
                            'status' => $firstTransaction['status'] ?? 'N/A',
                            'currency' => $firstTransaction['currency'] ?? 'KSH'
                        ];
                        
                        $totalRevenue += floatval($firstTransaction['amount'] ?? 0);
                        $totalTransactionCount++;
                    }
                    // Check if first transaction is BEFORE the date range (potential renewal customer)
                    else if ($firstTransactionDate < $filterStartDate) {
                        // This business had their first payment before our range
                        // Now check if they have any payments WITHIN our range (renewals)
                        foreach ($transactions as $transaction) {
                            try {
                                $transactionDate = new DateTime($transaction['billingDate']);
                                
                                // If this transaction is within our date range, it's a RENEWAL
                                if ($transactionDate >= $filterStartDate && $transactionDate <= $filterEndDate) {
                                    $renewals[] = [
                                        'businessId' => $businessId,
                                        'businessName' => $transaction['businessName'] ?? 'N/A',
                                        'email' => $transaction['email'] ?? 'N/A',
                                        'phone' => $transaction['phoneNumber'] ?? 'N/A',
                                        'paymentDate' => $transaction['billingDate'],
                                        'amount' => floatval($transaction['amount'] ?? 0),
                                        'paymentMethod' => $transaction['paymentMethod'] ?? 'N/A',
                                        'status' => $transaction['status'] ?? 'N/A',
                                        'currency' => $transaction['currency'] ?? 'KSH',
                                        'totalPayments' => count($transactions),
                                        'firstPaymentDate' => $firstTransaction['billingDate']
                                    ];
                                    
                                    $totalRenewalRevenue += floatval($transaction['amount'] ?? 0);
                                    $totalRenewalTransactionCount++;
                                }
                            } catch (Exception $e) {
                                continue;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Skip invalid dates
                    continue;
                }
            }
            
            // Sort conversions by date (newest first)
            usort($conversions, function($a, $b) {
                $dateA = new DateTime($a['firstPaymentDate']);
                $dateB = new DateTime($b['firstPaymentDate']);
                return $dateB <=> $dateA;
            });
            
            // Sort renewals by date (newest first)
            usort($renewals, function($a, $b) {
                $dateA = new DateTime($a['paymentDate']);
                $dateB = new DateTime($b['paymentDate']);
                return $dateB <=> $dateA;
            });
            
            $avgPayment = $totalTransactionCount > 0 ? round($totalRevenue / $totalTransactionCount, 2) : 0;
            $avgRenewalPayment = $totalRenewalTransactionCount > 0 ? round($totalRenewalRevenue / $totalRenewalTransactionCount, 2) : 0;
            
            $response = [
                'success' => true,
                // First-Time Customer Data
                'totalConversions' => count($conversions),
                'totalRevenue' => $totalRevenue,
                'avgPayment' => $avgPayment,
                'totalTransactions' => $totalTransactionCount,
                'conversions' => $conversions,
                // Renewal Data
                'totalRenewals' => count($renewals),
                'totalRenewalRevenue' => $totalRenewalRevenue,
                'avgRenewalPayment' => $avgRenewalPayment,
                'totalRenewalTransactions' => $totalRenewalTransactionCount,
                'renewals' => $renewals,
                // Common
                'dateRange' => $filterStartDate->format('Y-m-d') . ' to ' . $filterEndDate->format('Y-m-d')
            ];
            
            echo json_encode($response);
            exit;
        }
        
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
}
?>