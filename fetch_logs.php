<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // For very large ranges, use streaming response
    set_time_limit(0); // No time limit for streaming
    ini_set('memory_limit', '512M');
    
    $baseURL = "https://core.myaccuratebook.com";
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $logType = $_POST['logType'];
    $token = $_POST['token'];
    $tokenN = "zWWq5BWO+anUMgWtimvvCguXwU=wAMnzI6grv9WkCFsIdkBydGV4SDZQQHNz";
    $streamMode = $_POST['stream'] ?? false; // Enable streaming for large ranges

    $start = DateTime::createFromFormat('YmdHis', $startDate);
    $end = DateTime::createFromFormat('YmdHis', $endDate);

    if (!$start || !$end) {
        echo json_encode(['error' => 'Invalid date format. Use YYYYMMDDHHMMSS.']);
        exit;
    }

    $totalDays = $start->diff($end)->days + 1;
    
    // If more than 30 days and streaming not explicitly requested, suggest streaming
    if ($totalDays > 30 && !$streamMode) {
        echo json_encode([
            'error' => 'Large date range detected',
            'suggestion' => 'Use stream=true for ranges over 30 days',
            'totalDays' => $totalDays,
            'estimatedTime' => round($totalDays * 2) . ' seconds'
        ]);
        exit;
    }

    if ($streamMode) {
        // Streaming mode - send progress updates
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        
        echo "Starting data fetch for $totalDays days...\n";
        flush();
    }

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 day'));

    $uniqueUsers = [];
    $uniqueBusinesses = [];
    $processedDays = 0;
    $totalLogs = 0;
    
    // Function to convert timestamp to East African Time (UTC+3)
    function convertToEAT($timestamp) {
        if ($timestamp === 'N/A' || empty($timestamp)) {
            return 'N/A';
        }
        
        try {
            $utcTime = new DateTime($timestamp, new DateTimeZone('UTC'));
            $eatTime = $utcTime->setTimezone(new DateTimeZone('Africa/Nairobi'));
            return $eatTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Fallback: add 3 hours manually if DateTime parsing fails
            $unixTime = strtotime($timestamp);
            if ($unixTime !== false) {
                return date('Y-m-d H:i:s', $unixTime + (3 * 3600));
            }
            return $timestamp; // Return original if conversion fails
        }
    }
    
    // For streaming, we'll process and output results incrementally
    if ($streamMode) {
        echo "Business Name,Business Email,Parent ID,Timestamp (EAT),Response Time,User Agent,Path,Method\n";
    } else {
        $logRowsArray = [];
    }

    $batchSize = $streamMode ? 5 : 10; // Smaller batches for streaming
    $dayUrls = [];
    $dayDates = [];

    foreach ($period as $day) {
        $dayStart = $day->format('Ymd') . '000000';
        $dayEnd = $day->format('Ymd') . '235959';
        $dayUrls[] = "$baseURL/getlogs/query?endDate=$dayEnd&logType=$logType&token=$token&startDate=$dayStart";
        $dayDates[] = $day->format('Y-m-d');
    }

    $batches = array_chunk($dayUrls, $batchSize);
    $dateBatches = array_chunk($dayDates, $batchSize);

    foreach ($batches as $batchIndex => $batch) {
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        foreach ($batch as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ["Authorization: $tokenN"],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[] = $ch;
        }

        // Execute batch
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        // Process batch results
        $batchLogs = [];
        foreach ($curlHandles as $handleIndex => $ch) {
            $response = curl_multi_getcontent($ch);
            
            if ($response !== false) {
                $decoded = json_decode($response, true);
                if ($decoded && isset($decoded['logs'])) {
                    $batchLogs = array_merge($batchLogs, $decoded['logs']);
                }
            }
            
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        
        // Process this batch's logs
        foreach ($batchLogs as $log) {
            $businessName = $log['business']['businessName'] ?? 'N/A';
            $businessEmail = $log['business']['businessEmail'] ?? 'N/A';
            $parentId = $log['parentId'] ?? 'N/A';
            $timestamp = $log['timestamp'] ?? 'N/A';
            $responseTime = $log['responseTime'] ?? 'N/A';
            $userAgent = $log['userAgent'] ?? 'N/A';
            $path = $log['path'] ?? 'N/A';
            $method = $log['method'] ?? 'N/A';

            // Convert timestamp to EAT
            $timestampEAT = convertToEAT($timestamp);

            if ($streamMode) {
                // Output CSV row immediately with EAT timestamp
                echo '"' . str_replace('"', '""', $businessName) . '","' . 
                     str_replace('"', '""', $businessEmail) . '","' . 
                     str_replace('"', '""', $parentId) . '","' . 
                     str_replace('"', '""', $timestampEAT) . '","' . 
                     str_replace('"', '""', $responseTime) . '","' . 
                     str_replace('"', '""', $userAgent) . '","' . 
                     str_replace('"', '""', $path) . '","' . 
                     str_replace('"', '""', $method) . '"' . "\n";
            } else {
                $logRowsArray[] = "<tr><td>" . htmlspecialchars($businessName) . "</td><td>" . 
                                 htmlspecialchars($businessEmail) . "</td><td>" . 
                                 htmlspecialchars($parentId) . "</td><td>" . 
                                 htmlspecialchars($timestampEAT) . "</td><td>" . 
                                 htmlspecialchars($responseTime) . "</td><td>" . 
                                 htmlspecialchars($userAgent) . "</td><td>" . 
                                 htmlspecialchars($path) . "</td><td>" . 
                                 htmlspecialchars($method) . "</td></tr>";
            }

            // Track analytics
            if ($parentId !== 'N/A') {
                $uniqueUsers[$parentId] = true;
            }

            if ($businessName !== 'N/A' && $businessEmail !== 'N/A') {
                $businessKey = $businessName . '|' . $businessEmail;
                
                // Use original timestamp for comparison, but store EAT version for display
                $logTimestamp = $timestamp !== 'N/A' ? $timestamp : null;
                $logTimestampEAT = $timestampEAT;
                
                if (!isset($uniqueBusinesses[$businessKey])) {
                    $uniqueBusinesses[$businessKey] = [
                        'name' => $businessName,
                        'email' => $businessEmail,
                        'lastSeen' => $logTimestamp,
                        'lastSeenEAT' => $logTimestampEAT,
                        'lastSeenUnix' => $logTimestamp ? strtotime($logTimestamp) : 0
                    ];
                } else {
                    // Update last seen if this timestamp is more recent
                    if ($logTimestamp && (!$uniqueBusinesses[$businessKey]['lastSeen'] || 
                        strtotime($logTimestamp) > strtotime($uniqueBusinesses[$businessKey]['lastSeen']))) {
                        $uniqueBusinesses[$businessKey]['lastSeen'] = $logTimestamp;
                        $uniqueBusinesses[$businessKey]['lastSeenEAT'] = $logTimestampEAT;
                        $uniqueBusinesses[$businessKey]['lastSeenUnix'] = strtotime($logTimestamp);
                    }
                }
            }
        }

        $processedDays += count($batch);
        $totalLogs += count($batchLogs);

        if ($streamMode) {
            echo "\n# Progress: $processedDays/$totalDays days processed, $totalLogs total logs found\n";
            flush();
        }

        usleep(100000); // Brief pause between batches
    }

    // Sort businesses by most recent seen (newest first)
    uasort($uniqueBusinesses, function($a, $b) {
        return $b['lastSeenUnix'] <=> $a['lastSeenUnix'];
    });

    if ($streamMode) {
        echo "\n# Summary:\n";
        echo "# Total logs: $totalLogs\n";
        echo "# Unique users: " . count($uniqueUsers) . "\n";
        echo "# Unique businesses: " . count($uniqueBusinesses) . "\n";
        echo "# Date range: " . $start->format('Y-m-d') . " to " . $end->format('Y-m-d') . "\n";
        echo "\n# Business Last Seen Summary (Sorted by Most Recent):\n";
        echo "Business Name,Business Email,Last Seen (EAT)\n";
        foreach ($uniqueBusinesses as $business) {
            echo '"' . str_replace('"', '""', $business['name']) . '","' . 
                 str_replace('"', '""', $business['email']) . '","' . 
                 str_replace('"', '""', $business['lastSeenEAT']) . '"' . "\n";
        }
    } else {
        // Standard JSON response with sorted businesses
        $businessRowsArray = [];
        foreach ($uniqueBusinesses as $business) {
            $businessRowsArray[] = "<tr><td>" . htmlspecialchars($business['name']) . 
                                  "</td><td>" . htmlspecialchars($business['email']) . 
                                  "</td><td>" . htmlspecialchars($business['lastSeenEAT']) . "</td></tr>";
        }

        $responseData = [
            'logRows' => implode('', $logRowsArray),
            'uniqueUserCount' => count($uniqueUsers),
            'businessRows' => implode('', $businessRowsArray),
            'totalLogs' => $totalLogs,
            'dateRange' => $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d'),
            'processedDays' => $processedDays,
            'businessAnalytics' => array_values($uniqueBusinesses) // Full business data with last seen, sorted by most recent
        ];

        header('Content-Type: application/json');
        echo json_encode($responseData);
    }
}
?>