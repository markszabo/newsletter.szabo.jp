<?php
// Cloudflare Turnstile server side validation using cURL
// https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
function validateTurnstile($token, $secret, $remoteip = null) {
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    $data = [
        'secret' => $secret,
        'response' => $token
    ];

    if ($remoteip) {
        $data['remoteip'] = $remoteip;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout to prevent hanging

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    // If cURL itself failed to connect, return an internal error structure
    if ($response === false) {
        error_log("Turnstile cURL connection failed: " . $curlError);
        return ['success' => false, 'error-codes' => ['internal-error']];
    }

    return json_decode($response, true);
}
?>
