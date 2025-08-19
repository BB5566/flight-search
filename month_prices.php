<?php
require_once 'amadeus_api.php';
header('Content-Type: application/json; charset=utf-8');

$from = $_GET['from'] ?? ($_POST['from'] ?? '');
$to = $_GET['to'] ?? ($_POST['to'] ?? '');
$yearMonth = $_GET['yearMonth'] ?? ($_POST['yearMonth'] ?? '');
$adults = isset($_GET['adults']) ? intval($_GET['adults']) : (isset($_POST['adults']) ? intval($_POST['adults']) : 1);
$currency = $_GET['currency'] ?? ($_POST['currency'] ?? 'USD');

if (!$from || !$to || !$yearMonth || !preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
    http_response_code(400);
    echo json_encode([ 'success' => false, 'error' => 'INVALID_PARAMS' ]);
    exit;
}

try {
    $prices = searchMonthLowestPrices($from, $to, $yearMonth, $adults, 1, $currency);
    if (!is_array($prices)) {
        echo json_encode([ 'success' => false, 'error' => 'NO_DATA' ]);
        exit;
    }
    // 過濾 null 並計算統計
    $vals = array_values(array_filter($prices, fn($v) => $v !== null));
    if (empty($vals)) {
        echo json_encode([ 'success' => false, 'error' => 'NO_DATA' ]);
        exit;
    }

    sort($vals, SORT_NUMERIC);
    $min = $vals[0];
    $max = $vals[count($vals)-1];
    $percentile = function(array $a, float $p) {
        $n = count($a); if ($n === 0) return null; $idx = ($n - 1) * $p; $lo = (int)floor($idx); $hi = (int)ceil($idx);
        if ($lo === $hi) return $a[$lo];
        $w = $idx - $lo; return $a[$lo] * (1 - $w) + $a[$hi] * $w;
    }; 
    $q1 = $percentile($vals, 0.25);
    $median = $percentile($vals, 0.5);
    $q3 = $percentile($vals, 0.75);

    echo json_encode([
        'success' => true,
        'prices' => $prices,
        'currency' => $currency,
        'stats' => [ 'min' => $min, 'max' => $max, 'q1' => $q1, 'median' => $median, 'q3' => $q3 ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([ 'success' => false, 'error' => 'SERVER_ERROR', 'detail' => $e->getMessage() ]);
}
