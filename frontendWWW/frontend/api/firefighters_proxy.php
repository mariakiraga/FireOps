<?php
/**
 * firefighters_proxy.php (PHP 5.3 compatible)
 * Proxy do pobierania danych z ngrok bez CORS
 */

header('Content-Type: application/json; charset=utf-8');

// Upstream URL
$remoteUrl = 'https://localhost:5000/firefighters/';

// Zezwalamy tylko na GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(array(
        'error' => 'method_not_allowed',
        'message' => 'Only GET allowed'
    ));
    exit;
}

// Przygotowanie requestu
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $remoteUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Nagłówki do ngroka
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'ngrok-skip-browser-warning: true',
    'Accept: application/json'
));

// SSL (wyłącz jeśli cert dziwny)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

// Timeouty
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// Wykonanie
$responseBody = curl_exec($ch);
$curlErrNo = curl_errno($ch);
$curlError = curl_error($ch);
$httpCode = 0;

if (!$curlErrNo) {
    $info = curl_getinfo($ch);
    $httpCode = isset($info['http_code']) ? $info['http_code'] : 0;
}

curl_close($ch);

// Błąd cURL
if ($curlErrNo) {
    header('HTTP/1.1 502 Bad Gateway');
    echo json_encode(array(
        'error' => 'curl_error',
        'message' => $curlError,
        'code' => $curlErrNo
    ));
    exit;
}

// Zły kod HTTP upstream
if ($httpCode < 200 || $httpCode >= 300) {
    header('HTTP/1.1 502 Bad Gateway');
    echo json_encode(array(
        'error' => 'upstream_error',
        'http_code' => $httpCode,
        'upstream_raw' => $responseBody
    ));
    exit;
}

// Sprawdź, czy JSON jest poprawny
$decoded = json_decode($responseBody, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    // Upstream zwrócił coś dziwnego — ale przepuść to dalej
    echo $responseBody;
    exit;
}

// OK — zwróć poprawny JSON
echo $responseBody;
exit;
?>
