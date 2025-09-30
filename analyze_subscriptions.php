<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Catch any fatal errors and return JSON
function handleFatalError() {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']]);
    }
}
register_shutdown_function('handleFatalError');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    
    try {
        // Get JSON input
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
        
        // Handle test action
        if (isset($input['action']) && $input['action'] === 'test') {
            echo json_encode(['success' => true, 'message' => 'PHP script is working correctly']);
            exit;
        }
    
        // Handle login action
        if (isset($input['action']) && $input['action'] === 'login') {
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
        
        // Get parameters
        $token = $input['token'] ?? '';
        $filterType = $input['filterType'] ?? '';
        $startDate = $input['startDate'] ?? '';
        $endDate = $input['endDate'] ?? '';
        
        if (empty($token)) {
            echo json_encode(['error' => 'Token is required']);
            exit;
        }
        
        if (empty($filterType)) {
            echo json_encode(['error' => 'Filter type is required']);
            exit;
        }
        
        // Fetch all subscriptions from API
        $apiUrl = "https://core.myaccuratebook.com/admin/subscriptions/all?token=" . urlencode($token);
        $authToken = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
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
            echo json_encode(['error' => 'API request failed: ' . $error]);
            exit;
        }
        
        if ($httpCode !== 200) {
            echo json_encode(['error' => 'API request failed with status code: ' . $httpCode]);
            exit;
        }
        
        $subscriptionData = json_decode($response, true);
        
        if (!$subscriptionData || !isset($subscriptionData['rows'])) {
            echo json_encode(['error' => 'Invalid API response format']);
            exit;
        }
        
        $allSubscriptions = $subscriptionData['rows'];
        
        // Calculate date ranges based on filter type
        $today = new DateTime();
        $filterStartDate = null;
        $filterEndDate = null;
        $isFirstPaymentFilter = strpos($filterType, 'first_payment') === 0;
        
        switch ($filterType) {
            case 'expiring_today':
                $filterStartDate = clone $today;
                $filterEndDate = clone $today;
                break;
                
            case 'expiring_this_week':
                $filterStartDate = clone $today;
                $filterEndDate = (clone $today)->add(new DateInterval('P7D'));
                break;
                
            case 'expiring_next_week':
                $filterStartDate = (clone $today)->add(new DateInterval('P7D'));
                $filterEndDate = (clone $today)->add(new DateInterval('P14D'));
                break;
                
            case 'expiring_this_month':
                $filterStartDate = clone $today;
                $filterEndDate = new DateTime($today->format('Y-m-t'));
                break;
                
            case 'expiring_next_month':
                $filterStartDate = new DateTime($today->format('Y-m-01'));
                $filterStartDate->add(new DateInterval('P1M'));
                $filterEndDate = new DateTime($filterStartDate->format('Y-m-t'));
                break;
                
            case 'expired_last_week':
                $filterEndDate = (clone $today)->sub(new DateInterval('P1D'));
                $filterStartDate = (clone $today)->sub(new DateInterval('P8D'));
                break;
                
            case 'expired_this_month':
                $filterStartDate = new DateTime($today->format('Y-m-01'));
                $filterEndDate = clone $today;
                break;
                
            case 'first_payment_today':
                $filterStartDate = clone $today;
                $filterStartDate->setTime(0, 0, 0);
                $filterEndDate = clone $today;
                $filterEndDate->setTime(23, 59, 59);
                break;
                
            case 'first_payment_this_week':
                $filterStartDate = (clone $today)->modify('monday this week');
                $filterEndDate = (clone $today)->modify('sunday this week')->setTime(23, 59, 59);
                break;
                
            case 'first_payment_this_month':
                $filterStartDate = new DateTime($today->format('Y-m-01'));
                $filterEndDate = new DateTime($today->format('Y-m-t'));
                $filterEndDate->setTime(23, 59, 59);
                break;
                
            case 'first_payment_last_7_days':
                $filterStartDate = (clone $today)->sub(new DateInterval('P7D'))->setTime(0, 0, 0);
                $filterEndDate = clone $today;
                $filterEndDate->setTime(23, 59, 59);
                break;
                
            case 'first_payment_last_30_days':
                $filterStartDate = (clone $today)->sub(new DateInterval('P30D'))->setTime(0, 0, 0);
                $filterEndDate = clone $today;
                $filterEndDate->setTime(23, 59, 59);
                break;
                
            case 'first_payment_custom':
            case 'custom_range':
                if (empty($startDate) || empty($endDate)) {
                    echo json_encode(['error' => 'Start date and end date are required for custom range']);
                    exit;
                }
                try {
                    $filterStartDate = new DateTime($startDate);
                    $filterStartDate->setTime(0, 0, 0);
                    $filterEndDate = new DateTime($endDate);
                    $filterEndDate->setTime(23, 59, 59);
                } catch (Exception $e) {
                    echo json_encode(['error' => 'Invalid date format']);
                    exit;
                }
                break;
                
            case 'all_active':
            case 'unpaid_only':
                // No date filtering needed
                break;
                
            default:
                echo json_encode(['error' => 'Invalid filter type']);
                exit;
        }
        
        // Function to convert UTC to EAT
        function convertToEAT($utcDateString) {
            if (empty($utcDateString)) return 'N/A';
            
            try {
                $utcDate = new DateTime($utcDateString, new DateTimeZone('UTC'));
                $eatDate = $utcDate->setTimezone(new DateTimeZone('Africa/Nairobi'));
                return $eatDate->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                return $utcDateString;
            }
        }
        
        // Function to calculate days until expiry
        function calculateDaysUntilExpiry($endDate) {
            if (empty($endDate)) return 'N/A';
            
            try {
                $today = new DateTime();
                $expiry = new DateTime($endDate);
                $diff = $today->diff($expiry);
                
                if ($expiry < $today) {
                    return -$diff->days;
                } else {
                    return $diff->days;
                }
            } catch (Exception $e) {
                return 'N/A';
            }
        }
        
        // Function to determine status based on expiry
        function getSubscriptionStatus($endDate) {
            if (empty($endDate)) return 'unknown';
            
            try {
                $today = new DateTime();
                $expiry = new DateTime($endDate);
                
                if ($expiry < $today) {
                    return 'expired';
                } elseif ($expiry <= (clone $today)->add(new DateInterval('P7D'))) {
                    return 'expiring_soon';
                } else {
                    return 'active';
                }
            } catch (Exception $e) {
                return 'unknown';
            }
        }
        
        // Function to get first payment from billing history
        function getFirstPayment($billingHistories) {
            if (empty($billingHistories)) {
                return null;
            }
            
            // Sort billing histories by payment_date to find the first payment
            $sortedBilling = $billingHistories;
            usort($sortedBilling, function($a, $b) {
                $dateA = isset($a['transactionDate']) ? $a['transactionDate'] : '';
                $dateB = isset($b['transactionDate']) ? $b['transactionDate'] : '';
                
                if (empty($dateA)) return 1;
                if (empty($dateB)) return -1;
                
                try {
                    $dtA = new DateTime($dateA);
                    $dtB = new DateTime($dateB);
                    return $dtA <=> $dtB;
                } catch (Exception $e) {
                    return 0;
                }
            });
            
            // Return the first (earliest) billing history entry with a payment date
            foreach ($sortedBilling as $billing) {
                if (isset($billing['transactionDate']) && !empty($billing['transactionDate']) && 
                    isset($billing['status']) && $billing['status'] === 'paid') {
                    return $billing;
                }
            }
            
            return null;
        }
        
        // Filter and process subscriptions
        $filteredSubscriptions = [];
        $totalRevenue = 0;
        $paidCount = 0;
        $unpaidCount = 0;
        $conversionCount = 0;
        $totalFirstPayments = 0;
        
        foreach ($allSubscriptions as $subscription) {
            $business = $subscription['Business'] ?? [];
            $package = $subscription['Package'] ?? [];
            $billingHistories = $subscription['BillingHistories'] ?? [];
            
            $businessName = $business['company_name'] ?? 'N/A';
            $businessEmail = $business['company_email'] ?? 'N/A';
            $phoneNumber = $business['phone_number'] ?? 'N/A';
            $packageName = $package['name'] ?? 'N/A';
            $startDateRaw = $subscription['start_date'] ?? '';
            $endDateRaw = $subscription['end_date'] ?? '';
            $status = $subscription['status'] ?? 'unknown';
            
            // Convert dates
            $startDate = convertToEAT($startDateRaw);
            $endDate = convertToEAT($endDateRaw);
            $daysUntilExpiry = calculateDaysUntilExpiry($endDateRaw);
            $subscriptionStatus = getSubscriptionStatus($endDateRaw);
            
            // Determine payment status and revenue
            $isPaid = !empty($billingHistories);
            $paymentStatus = $isPaid ? 'paid' : 'unpaid';
            $revenue = 0;
            
            if ($isPaid) {
                foreach ($billingHistories as $billing) {
                    if (isset($billing['amount']) && $billing['status'] === 'paid') {
                        $revenue += floatval($billing['amount']);
                    }
                }
                $paidCount++;
                $totalRevenue += $revenue;
            } else {
                $unpaidCount++;
            }
            
            // Get first payment information if this is a conversion report
            $firstPayment = null;
            $firstPaymentDate = null;
            $firstPaymentDateRaw = null;
            $firstPaymentAmount = 0;
            
            if ($isFirstPaymentFilter) {
                $firstPayment = getFirstPayment($billingHistories);
                if ($firstPayment) {
                    $firstPaymentDateRaw = $firstPayment['payment_date'] ?? '';
                    $firstPaymentDate = convertToEAT($firstPaymentDateRaw);
                    $firstPaymentAmount = floatval($firstPayment['amount'] ?? 0);
                }
            }
            
            // Apply filters
            $shouldInclude = false;
            
            switch ($filterType) {
                case 'first_payment_today':
                case 'first_payment_this_week':
                case 'first_payment_this_month':
                case 'first_payment_last_7_days':
                case 'first_payment_last_30_days':
                case 'first_payment_custom':
                    // Filter by first payment date
                    if ($firstPayment && !empty($firstPaymentDateRaw)) {
                        try {
                            $paymentDateObj = new DateTime($firstPaymentDateRaw);
                            if ($paymentDateObj >= $filterStartDate && $paymentDateObj <= $filterEndDate) {
                                $shouldInclude = true;
                                $conversionCount++;
                                $totalFirstPayments += $firstPaymentAmount;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                    break;
                
                case 'expiring_today':
                case 'expiring_this_week':
                case 'expiring_next_week':
                case 'expiring_this_month':
                case 'expiring_next_month':
                    if (!empty($endDateRaw)) {
                        try {
                            $endDateObj = new DateTime($endDateRaw);
                            if ($endDateObj >= $filterStartDate && $endDateObj <= $filterEndDate) {
                                $shouldInclude = true;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                    break;
                    
                case 'expired_last_week':
                case 'expired_this_month':
                    if (!empty($endDateRaw)) {
                        try {
                            $endDateObj = new DateTime($endDateRaw);
                            if ($endDateObj >= $filterStartDate && $endDateObj <= $filterEndDate && $endDateObj < $today) {
                                $shouldInclude = true;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                    break;
                    
                case 'custom_range':
                    if (!empty($endDateRaw)) {
                        try {
                            $endDateObj = new DateTime($endDateRaw);
                            if ($endDateObj >= $filterStartDate && $endDateObj <= $filterEndDate) {
                                $shouldInclude = true;
                            }
                        } catch (Exception $e) {
                            // Skip invalid dates
                        }
                    }
                    break;
                    
                case 'all_active':
                    if ($status === 'active') {
                        $shouldInclude = true;
                    }
                    break;
                    
                case 'unpaid_only':
                    if (!$isPaid) {
                        $shouldInclude = true;
                    }
                    break;
            }
            
            if ($shouldInclude) {
                $filteredSubscriptions[] = [
                    'id' => $subscription['id'] ?? '',
                    'businessName' => $businessName,
                    'businessEmail' => $businessEmail,
                    'phoneNumber' => $phoneNumber,
                    'packageName' => $packageName,
                    'status' => $status,
                    'subscriptionStatus' => $subscriptionStatus,
                    'paymentStatus' => $paymentStatus,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'startDateRaw' => $startDateRaw,
                    'endDateRaw' => $endDateRaw,
                    'daysUntilExpiry' => $daysUntilExpiry,
                    'revenue' => $revenue,
                    'isPaid' => $isPaid,
                    'billingHistories' => $billingHistories,
                    'firstPaymentDate' => $firstPaymentDate,
                    'firstPaymentDateRaw' => $firstPaymentDateRaw,
                    'firstPaymentAmount' => $firstPaymentAmount
                ];
            }
        }
        
        // Sort by appropriate field
        if ($isFirstPaymentFilter) {
            // Sort by first payment date (most recent first)
            usort($filteredSubscriptions, function($a, $b) {
                if ($a['firstPaymentDateRaw'] === $b['firstPaymentDateRaw']) return 0;
                if (empty($a['firstPaymentDateRaw'])) return 1;
                if (empty($b['firstPaymentDateRaw'])) return -1;
                
                try {
                    $dateA = new DateTime($a['firstPaymentDateRaw']);
                    $dateB = new DateTime($b['firstPaymentDateRaw']);
                    return $dateB <=> $dateA; // Descending order
                } catch (Exception $e) {
                    return 0;
                }
            });
        } else {
            // Sort by end date (soonest expiry first)
            usort($filteredSubscriptions, function($a, $b) {
                if ($a['endDateRaw'] === $b['endDateRaw']) return 0;
                if (empty($a['endDateRaw'])) return 1;
                if (empty($b['endDateRaw'])) return -1;
                
                try {
                    $dateA = new DateTime($a['endDateRaw']);
                    $dateB = new DateTime($b['endDateRaw']);
                    return $dateA <=> $dateB;
                } catch (Exception $e) {
                    return 0;
                }
            });
        }
        
        // Generate HTML table rows
        $tableRows = '';
        foreach ($filteredSubscriptions as $sub) {
            // Status badge classes
            $statusClass = '';
            switch ($sub['subscriptionStatus']) {
                case 'active':
                    $statusClass = 'status-active';
                    break;
                case 'expired':
                    $statusClass = 'status-expired';
                    break;
                case 'expiring_soon':
                    $statusClass = 'status-expiring';
                    break;
                default:
                    $statusClass = '';
            }
            
            $paymentClass = $sub['isPaid'] ? 'paid' : 'unpaid';
            
            // Format days until expiry
            $daysDisplay = '';
            if ($sub['daysUntilExpiry'] === 'N/A') {
                $daysDisplay = 'N/A';
            } elseif ($sub['daysUntilExpiry'] < 0) {
                $daysDisplay = 'Expired ' . abs($sub['daysUntilExpiry']) . ' days ago';
            } elseif ($sub['daysUntilExpiry'] == 0) {
                $daysDisplay = 'Expires today';
            } else {
                $daysDisplay = $sub['daysUntilExpiry'] . ' days';
            }
            
            $tableRows .= '<tr>';
            $tableRows .= '<td>' . htmlspecialchars($sub['businessName']) . '</td>';
            $tableRows .= '<td>' . htmlspecialchars($sub['businessEmail']) . '</td>';
            $tableRows .= '<td>' . htmlspecialchars($sub['phoneNumber']) . '</td>';
            $tableRows .= '<td>' . htmlspecialchars($sub['packageName']) . '</td>';
            $tableRows .= '<td><span class="status-badge ' . $statusClass . '">' . 
                         htmlspecialchars(ucfirst($sub['subscriptionStatus'])) . '</span></td>';
            $tableRows .= '<td><span class="payment-status ' . $paymentClass . '">' . 
                         htmlspecialchars(ucfirst($sub['paymentStatus'])) . '</span></td>';
            
            // Add first payment date column for conversion reports
            if ($isFirstPaymentFilter) {
                $tableRows .= '<td class="first-payment-col">' . 
                             htmlspecialchars($sub['firstPaymentDate'] ?? 'N/A') . '</td>';
            }
            
            $tableRows .= '<td>' . htmlspecialchars($sub['startDate']) . '</td>';
            $tableRows .= '<td>' . htmlspecialchars($sub['endDate']) . '</td>';
            $tableRows .= '<td>' . htmlspecialchars($daysDisplay) . '</td>';
            $tableRows .= '<td>KSH ' . number_format($sub['revenue'], 2) . '</td>';
            $tableRows .= '</tr>';
        }
        
        // Prepare response
        $response = [
            'success' => true,
            'totalSubscriptions' => count($filteredSubscriptions),
            'paidSubscriptions' => $paidCount,
            'unpaidSubscriptions' => $unpaidCount,
            'totalRevenue' => number_format($totalRevenue, 2),
            'filterType' => $filterType,
            'dateRange' => '',
            'subscriptions' => $filteredSubscriptions,
            'tableRows' => $tableRows
        ];
        
        // Add conversion-specific metrics
        if ($isFirstPaymentFilter) {
            $response['conversionCount'] = $conversionCount;
            $response['avgRevenue'] = $conversionCount > 0 ? 
                number_format($totalFirstPayments / $conversionCount, 2) : '0.00';
        }
        
        // Add date range info
        if ($filterStartDate && $filterEndDate) {
            $response['dateRange'] = $filterStartDate->format('Y-m-d') . ' to ' . $filterEndDate->format('Y-m-d');
        } else {
            switch ($filterType) {
                case 'all_active':
                    $response['dateRange'] = 'All active subscriptions';
                    break;
                case 'unpaid_only':
                    $response['dateRange'] = 'All unpaid subscriptions';
                    break;
                default:
                    $response['dateRange'] = ucwords(str_replace('_', ' ', $filterType));
            }
        }

        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'PHP Exception: ' . $e->getMessage()]);
        exit;
    } catch (Error $e) {
        echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
        exit;
    }
    
} else {
    echo json_encode(['error' => 'Only POST method allowed']);
}
?>