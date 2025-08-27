
<?php
/**
 * utils.php
 * 一個包含整個應用程式中使用的輔助函式的集合。
 */

// 從一個獨立的資料檔案中載入航空公司名稱，以保持資料和邏輯分離。
$airlines = require __DIR__ . '/data/airlines.php';

/**
 * 根據給定的貨幣代碼獲取貨幣符號。
 *
 * @param string $currency 貨幣代碼 (例如, TWD)。
 * @return string 貨幣符號 (例如, NT$)，如果找不到則返回代碼本身。
 */
function getCurrencySymbol($currency) {
    $symbols = [
        'USD' => '$',
        'TWD' => 'NT$',
        'JPY' => '¥',
        'CNY' => '¥',
        'EUR' => '€',
        'HKD' => 'HK$'
    ];
    return $symbols[strtoupper($currency)] ?? strtoupper($currency);
}

/**
 * 根據給定的 IATA 代碼獲取航空公司名稱。
 *
 * @param string $code 航空公司的 IATA 代碼 (例如, CI)。
 * @return string 航空公司名稱，如果找不到則返回代碼本身。
 */
function getAirlineName($code) {
    global $airlines;
    return $airlines[$code] ?? $code;
}

/**
 * 將日期/時間字串格式化為更易讀的格式。
 *
 * @param string $dt 來自 API 的日期/時間字串。
 * @return string 格式化後的日期/時間 (Y-m-d H:i)。
 */
function formatDateTime($dt) {
    try {
        return (new DateTime($dt))->format('Y-m-d H:i');
    } catch (Exception $e) {
        return '日期無效';
    }
}

/**
 * 將 ISO 8601 持續時間字串或分鐘數轉換為人類可讀的格式。
 *
 * @param string|int $duration ISO 8601 持續時間 (例如, PT5H30M) 或分鐘數。
 * @return string 格式化後的持續時間 (例如, 5小時30分)。
 */
function formatDuration($duration) {
    $minutes = durationToMinutes($duration);
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%d小時%02d分", $hours, $mins);
}

/**
 * 將 ISO 8601 持續時間字串轉換為總分鐘數的整數。
 *
 * @param string|int $duration ISO 8601 持續時間或一個整數。
 * @return int 以分鐘為單位的持續時間。
 */
function durationToMinutes($duration) {
    if (is_numeric($duration)) {
        return (int)$duration;
    }
    if (is_string($duration) && preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $duration, $m)) {
        $hours = isset($m[1]) ? (int)$m[1] : 0;
        $minutes = isset($m[2]) ? (int)$m[2] : 0;
        return $hours * 60 + $minutes;
    }
    return 0;
}
