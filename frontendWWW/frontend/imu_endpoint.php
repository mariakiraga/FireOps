<?php
// imu_endpoint.php (PHP 5.3)

// Zawsze JSON
header('Content-Type: application/json; charset=utf-8');

// --- Helper funkcja dla PHP 5.3 ---
function send_status($code, $message) {
    $statusTexts = array(
        200 => 'OK',
        400 => 'Bad Request',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error'
    );

    $text = isset($statusTexts[$code]) ? $statusTexts[$code] : 'Unknown Status';
    header("HTTP/1.1 " . $code . " " . $text);

    echo json_encode(array(
        'status'  => ($code === 200 ? 'ok' : 'error'),
        'message' => $message
    ));
    exit;
}

// Zezwalamy tylko na POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_status(405, 'Only POST method is allowed.');
}

// Pobierz dane JSON
$rawInput = file_get_contents('php://input');

if ($rawInput === false || $rawInput === '') {
    send_status(400, 'Empty request body.');
}

// Sprawdź JSON
$data = json_decode($rawInput, true);
if ($data === null) {
    send_status(400, 'Invalid JSON payload.');
}

// Katalog na logi
$dir = __DIR__ . '/imu_logs';
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

// batchTimestamp z JSON-a (ms)
$batchTimestamp = isset($data['batchTimestamp']) ? (int)$data['batchTimestamp'] : time() * 1000;

// Nazwa pliku
$filename = 'imu_' . date('Ymd_His', (int)($batchTimestamp / 1000)) . '_' . uniqid() . '.json';
$filepath = $dir . '/' . $filename;

// Zapis surowego JSON-a do pliku
$result = @file_put_contents($filepath, $rawInput . PHP_EOL, LOCK_EX);
if ($result === false) {
    send_status(500, 'Failed to write data to file.');
}

/*
 * --- GENEROWANIE POZYCJI NA ELIPSIE ---
 *
 * t = sekundy wyliczone z batchTimestamp (ms)
 * x, y = pozycje w metrach, maksymalnie 50 m od środka
 */

// t w sekundach (float)
$tSeconds = $batchTimestamp / 1000.0;

// Parametry elipsy (w metrach), <= 50
$A = 40.0; // półoś w osi X
$B = 25.0; // półoś w osi Y (obie < 50)

// Prędkość kątowa (rad/s) – jak szybko "biegniemy" po elipsie
$omega = 0.05; // możesz podkręcić / zmniejszyć

// Kąt
$theta = $omega * $tSeconds;

// Pozycje na elipsie
$x = $A * cos($theta);
$y = $B * sin($theta);

// Tablica positions wg formatu:
// "positions": [
//   {"t": 0.0, "pos": [0.0, 0.0]}
// ]
// Tu zwracamy jeden punkt dla aktualnego batchTimestamp.
// Kolejne wywołania z innymi batchTimestamp dadzą kolejne punkty na elipsie.
$positions = array(
    array(
        't'   => $tSeconds,
        'pos' => array($x, $y)
    )
);

// Sukces – rozszerzona odpowiedź
header('HTTP/1.1 200 OK');
echo json_encode(array(
    'status'         => 'ok',
    'message'        => 'IMU batch saved.',
    'file'           => $filename,
    'batchTimestamp' => $batchTimestamp,
    'positions'      => $positions
));
exit;
