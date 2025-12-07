<?php
// api/firefighters.php
// Mock danych strażaków + symulacja ruchu, bezruchu, kroków i alertów

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$now = time();

$globalActionId = 1234;

// Dane „bazowe” strażaków – pozycje startowe + zespół itp.
$baseFirefighters = array(
    array(
        'id'          => 'FF-01',
        'first_name'  => 'Jan',
        'last_name'   => 'Kowalski',
        'base_lat'    => 52.2297,   // Warszawa
        'base_lng'    => 21.0122,
        'team_name'   => 'Zespół Alfa',
        'team_number' => 'A-1',
        'status'      => 'IN_ACTION'
    ),
    array(
        'id'          => 'FF-02',
        'first_name'  => 'Anna',
        'last_name'   => 'Nowak',
        'base_lat'    => 50.0647,   // Kraków
        'base_lng'    => 19.9450,
        'team_name'   => 'Zespół Bravo',
        'team_number' => 'B-2',
        'status'      => 'IN_ACTION'
    ),
    array(
        'id'          => 'FF-03',
        'first_name'  => 'Piotr',
        'last_name'   => 'Zieliński',
        'base_lat'    => 51.1079,   // Wrocław
        'base_lng'    => 17.0385,
        'team_name'   => 'Zespół Charlie',
        'team_number' => 'C-3',
        'status'      => 'EN_ROUTE'
    ),
    array(
        'id'          => 'FF-04',
        'first_name'  => 'Katarzyna',
        'last_name'   => 'Wiśniewska',
        'base_lat'    => 53.4285,   // Szczecin
        'base_lng'    => 14.5528,
        'team_name'   => 'Zespół Delta',
        'team_number' => 'D-4',
        'status'      => 'STANDBY'
    )
);

// Parametry trajektorii / bezruchu
$cycleSeconds = 120;    // pełen cykl 120 s
$movePhase    = 60;     // 60 s ruchu + 60 s postoju
$radiusLat    = 0.005;  // ok. 500 m
$radiusLng    = 0.01;   // ok. 1 km

$firefightersOut = array();

for ($i = 0; $i < count($baseFirefighters); $i++) {
    $f = $baseFirefighters[$i];

    $phase = $i * 20;
    $cyclePos = ($now + $phase) % $cycleSeconds; // 0..119

    // Czy w ruchu, czy stoi
    $isMoving = $cyclePos < $movePhase;

    // Wyliczamy pozycję na okręgu
    if ($isMoving) {
        $progressMove = $cyclePos / $movePhase; // 0..1
        $angle = 2 * pi() * $progressMove;
    } else {
        // gdy stoi – końcowy punkt okręgu
        $angle = 2 * pi(); // 1 pełen obrót
    }

    $lat   = $f['base_lat'] + sin($angle) * $radiusLat;
    $lng   = $f['base_lng'] + cos($angle) * $radiusLng;

    // Bezruch
    if ($isMoving) {
        $secondsStill = 0;
        $lastMoveTs   = $now;
    } else {
        $secondsStill = $cyclePos - $movePhase; // 0..60
        $lastMoveTs   = $now - $secondsStill;
    }

    // suma czasu ruchu w całej „karierze” (dla kroków)
    $fullCycles     = floor(($now + $phase) / $cycleSeconds);
    $movingSecondsInCompleted = $fullCycles * $movePhase;
    $movingSecondsInCurrent   = $isMoving ? $cyclePos : $movePhase;
    $totalMovingSeconds       = $movingSecondsInCompleted + $movingSecondsInCurrent;

    // Kroki – np. 1.8 kroku na sekundę ruchu
    $steps = intval($totalMovingSeconds * 1.8);

    // Dane biometryczne / techniczne
    $heartRateBase = 90 + ($i * 5);
    $heartRate     = $heartRateBase + (($now + $i) % 40);      // ~90–150
    // Specjalnie podbijamy dla jednego żeby generować alert >180
    if ($i === 1 && $isMoving) {
        $heartRate += 40; // do ~190
    }

    $bodyTemp = 36.5 + ((($now / 10) + $i) % 8) / 10.0; // 36.5–37.2

    // Ambient – sztuczne dane
    $ambientTemp = 24 + (($now / 60 + $i) % 12); // 24–35

    $airLeft = max(0, 100 - ((intval($now / 5) + $i * 7) % 100));
    $battery = max(5, 100 - ((intval($now / 15) + $i * 11) % 95));

    // Status bazowy
    $status = $f['status'];
    $isOnline = true;

    // Prosta symulacja OFFLINE
    if ((($now + $i * 23) % 300) > 270) {
        $status   = 'OFFLINE';
        $isOnline = false;
    }

    // ALERTY
    $alerts = array();

    // MAN-DOWN: 30+ sekund bez ruchu
    if ($secondsStill >= 30 && $isOnline) {
        $alerts[] = 'MAN-DOWN';
    }

    // SOS – losowo / periodycznie
    if ((($now + $i * 17) % 97) === 0) {
        $alerts[] = 'SOS';
    }

    // niski poziom powietrza
    if ($airLeft < 20) {
        $alerts[] = 'LOW_AIR';
    }

    // tętno powyżej 180
    if ($heartRate > 180) {
        $alerts[] = 'HIGH_HEART_RATE';
    }

    // niski poziom baterii
    if ($battery < 20) {
        $alerts[] = 'LOW_BATTERY';
    }

    // wysoka temperatura otoczenia
    if ($ambientTemp > 32) {
        $alerts[] = 'HIGH_TEMPERATURE_AMBIENT';
    }

    // strażak offline
    if (!$isOnline) {
        $alerts[] = 'OFFLINE';
    }

    // jeśli są alerty – status = ALERT
    if (!empty($alerts) && $status !== 'OFFLINE') {
        $status = 'ALERT';
    }

    $firefightersOut[] = array(
        'id'             => $f['id'],
        'name'           => $f['first_name'] . ' ' . $f['last_name'],
        'lat'            => $lat,
        'lng'            => $lng,
        'team_name'      => $f['team_name'],
        'team_number'    => $f['team_number'],
        'action'         => 'ACT-' . ($globalActionId + $i),
        'status'         => $status,
        'is_online'      => $isOnline,
        'heart_rate'     => $heartRate,
        'body_temp'      => round($bodyTemp, 1),
        'ambient_temp'   => $ambientTemp,
        'air_left'       => $airLeft,
        'battery'        => $battery,
        'steps'          => $steps,
        'seconds_still'  => $secondsStill,
        'last_move_ts'   => $lastMoveTs,
        'alerts'         => $alerts
    );
}

$response = array(
    'timestamp'           => $now,
    'action_id'           => $globalActionId,
    'units'               => 3,
    'firefighters_online' => count($firefightersOut),
    'firefighters'        => $firefightersOut
);

echo json_encode($response);
exit;
