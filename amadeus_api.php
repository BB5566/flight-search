<?php
require_once 'env.php';
loadEnv(); // 讀取 .env

// 啟用錯誤顯示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// API設置
// 允許用 .env 覆蓋端點：AMADEUS_API_ENDPOINT 或以 AMADEUS_ENV=test 使用測試環境
$__amadeus_env = $_ENV['AMADEUS_ENV'] ?? null; // test | prod
$__amadeus_endpoint = $_ENV['AMADEUS_API_ENDPOINT']
    ?? (($__amadeus_env === 'test') ? 'https://test.api.amadeus.com' : 'https://api.amadeus.com');
define('AMADEUS_API_ENDPOINT', $__amadeus_endpoint);
const DEBUG_MODE = true; // 設置為 true 時會記錄 API 回應
const API_TIMEOUT = 10;  // API 超時時間（秒）
const MAX_RETRIES = 2;   // API 重試次數
const TOKEN_CACHE_FILE = __DIR__ . '/.amadeus_token.cache';

// API 錯誤碼對應的訊息
const API_ERROR_MESSAGES = [
    'INVALID_CLIENT' => 'API 驗證失敗，請檢查憑證設定',
    'TOKEN_INVALID' => 'API Token 已失效，請重新取得',
    'INVALID_PARAMETER' => '請求參數無效',
    'RESOURCE_NOT_FOUND' => '找不到符合條件的航班',
    'RATE_LIMIT_EXCEEDED' => 'API 請求次數超過限制，請稍後再試',
    'SYSTEM_ERROR' => '系統錯誤，請稍後再試'
];

function debug_log($message) {
    if (DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] " . print_r($message, true));
    }
}

function handleApiError($response, $httpCode) {
    $errorCode = $response['errors'][0]['code'] ?? 'SYSTEM_ERROR';
    $errorDetail = $response['errors'][0]['detail'] ?? null;
    $message = API_ERROR_MESSAGES[$errorCode] ?? '發生未知錯誤';
    
    debug_log([
        'error_code' => $errorCode,
        'http_code' => $httpCode,
        'detail' => $errorDetail
    ]);
    
    return [
        'error' => $message,
        'code' => $errorCode,
        'detail' => $errorDetail,
        'http_code' => $httpCode
    ];
}

function makeApiRequest($url, $headers = [], $method = 'GET', $body = null, $retry = 0) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => $method
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        debug_log("API 請求錯誤: $error");
        if ($retry < MAX_RETRIES) {
            debug_log("重試請求 #" . ($retry + 1));
            return makeApiRequest($url, $headers, $method, $body, $retry + 1);
        }
        return ['error' => '網路連接錯誤，請稍後再試', 'http_code' => 0];
    }

    $decoded = json_decode($response, true);
    if ($httpCode !== 200) {
        // 若非 JSON，將原始回應塞進 detail 方便偵錯
        return handleApiError($decoded ?: ['errors' => [['detail' => $response]]], $httpCode);
    }

    if (DEBUG_MODE) {
        debug_log(['url' => $url, 'method' => $method, 'http_code' => $httpCode]);
    }

    return $decoded;
}

function readCachedToken() {
    if (!file_exists(TOKEN_CACHE_FILE)) return null;
    $raw = @file_get_contents(TOKEN_CACHE_FILE);
    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    if (!isset($data['token'], $data['expires_at'])) return null;
    // 提前 60 秒過期
    if (time() >= (int)$data['expires_at'] - 60) return null;
    return $data['token'];
}

function cacheToken($token, $expiresIn) {
    $payload = [
        'token' => $token,
        'expires_at' => time() + (int)$expiresIn
    ];
    @file_put_contents(TOKEN_CACHE_FILE, json_encode($payload));
}

function getAmadeusAccessToken($forceRefresh = false) {
    $clientId = $_ENV['AMADEUS_CLIENT_ID'] ?? null;
    $clientSecret = $_ENV['AMADEUS_CLIENT_SECRET'] ?? null;

    if (!$clientId || !$clientSecret || $clientId === '請填入您的API_KEY' || $clientSecret === '請填入您的API_SECRET') {
        debug_log('API憑證未設置或不正確。請檢查您的 .env 檔案。');
        return ['error' => 'API 憑證未設定或不正確', 'code' => 'INVALID_CREDENTIALS'];
    }

    if (!$forceRefresh) {
        $cached = readCachedToken();
        if ($cached) return $cached;
    }

    $data = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]);

    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    $url = AMADEUS_API_ENDPOINT . '/v1/security/oauth2/token';
    $result = makeApiRequest($url, $headers, 'POST', $data);
    
    if (isset($result['error'])) {
        return $result;
    }

    if (isset($result['access_token'])) {
        $token = $result['access_token'];
        $expires = $result['expires_in'] ?? 1700; // 秒
        cacheToken($token, (int)$expires);
        return $token;
    }
    return null;
}

function searchFlights($origin, $destination, $departureDate, $adults = 1, $max = 5, $children = 0, $travelClass = null, $currency = 'USD', $nonStop = null, $maxPrice = null, $includedAirlineCodes = null, $excludedAirlineCodes = null, $infants = 0) {
    static $token = null;
    if (!$token) {
        $tokenResult = getAmadeusAccessToken();
        if (is_array($tokenResult) && isset($tokenResult['error'])) {
            return $tokenResult;
        }
        $token = $tokenResult;
    }

    $query = [
        'originLocationCode' => strtoupper($origin),
        'destinationLocationCode' => strtoupper($destination),
        'departureDate' => $departureDate,
        'adults' => max(1, (int)$adults),
        'max' => max(1, (int)$max),
        'currencyCode' => $currency ?: 'USD'
    ];
    if ($children > 0) $query['children'] = (int)$children;
    if ($infants > 0) $query['infants'] = (int)$infants;
    if ($travelClass) $query['travelClass'] = $travelClass;
    if ($nonStop !== null) $query['nonStop'] = $nonStop ? 'true' : 'false';
    if ($maxPrice !== null && $maxPrice !== '') $query['maxPrice'] = (int)$maxPrice;
    if ($includedAirlineCodes) {
        if (is_array($includedAirlineCodes)) $includedAirlineCodes = implode(',', $includedAirlineCodes);
        $query['includedAirlineCodes'] = strtoupper(str_replace(' ', '', $includedAirlineCodes));
    }
    if ($excludedAirlineCodes) {
        if (is_array($excludedAirlineCodes)) $excludedAirlineCodes = implode(',', $excludedAirlineCodes);
        $query['excludedAirlineCodes'] = strtoupper(str_replace(' ', '', $excludedAirlineCodes));
    }
    $params = http_build_query($query);

    $headers = [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ];
    
    $url = AMADEUS_API_ENDPOINT . "/v2/shopping/flight-offers?$params";
    $result = makeApiRequest($url, $headers);

    // 如果是 token 失效，重試一次
    if (isset($result['error']) && (($result['code'] ?? null) === 'TOKEN_INVALID' || ($result['http_code'] ?? null) === 401)) {
        $tokenResult = getAmadeusAccessToken(true);
        if (is_array($tokenResult) && isset($tokenResult['error'])) {
            return $tokenResult;
        }
        $token = $tokenResult;
        if ($token) {
            $headers = ["Authorization: Bearer $token", "Accept: application/json"];
            $result = makeApiRequest($url, $headers);
        }
    }

    return $result;
}

// 優化月曆比價功能，使用 curl_multi 實現並行請求
function searchMonthLowestPrices($origin, $destination, $yearMonth, $adults = 1, $max = 1, $currency = 'USD') {
    $tokenResult = getAmadeusAccessToken();
    if (is_array($tokenResult) && isset($tokenResult['error'])) {
        debug_log('無法獲取API Token: ' . $tokenResult['error']);
        return $tokenResult;
    }
    $token = $tokenResult;

    $results = [];
    $days = cal_days_in_month(CAL_GREGORIAN, intval(substr($yearMonth, 5, 2)), intval(substr($yearMonth, 0, 4)));
    
    $mh = curl_multi_init();
    $handles = [];

    for ($d = 1; $d <= $days; $d++) {
        $date = $yearMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
        
        $query = http_build_query([
            'originLocationCode' => strtoupper($origin),
            'destinationLocationCode' => strtoupper($destination),
            'departureDate' => $date,
            'adults' => max(1, (int)$adults),
            'max' => max(1, (int)$max),
            'nonStop' => 'false',
            'currencyCode' => $currency ?: 'USD'
        ]);
        
        $url = AMADEUS_API_ENDPOINT . "/v2/shopping/flight-offers?$query";
        $headers = ["Authorization: Bearer $token", "Accept: application/json"];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$date] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($handles as $date => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $decoded = json_decode($response, true);

        if ($httpCode === 200 && !isset($decoded['errors'])) {
            $results[$date] = isset($decoded['data'][0]['price']['total']) 
                ? (float)$decoded['data'][0]['price']['total'] 
                : null;
        } else {
            $results[$date] = null;
            $errorDetail = $decoded['errors'][0]['detail'] ?? $response;
            debug_log("日期 $date 查詢失敗 (HTTP $httpCode): " . print_r($errorDetail, true));
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);

    return $results;
}

// 測試 API 連接
function testApiConnection() {
    $tokenResult = getAmadeusAccessToken();
    if (is_array($tokenResult) && isset($tokenResult['error'])) {
        return [
            'success' => false,
            'message' => 'API連接失敗: ' . $tokenResult['error'],
            'code' => $tokenResult['code'] ?? 'UNKNOWN'
        ];
    }
    
    return [
        'success' => $tokenResult !== null,
        'message' => $tokenResult ? 'API連接成功' : 'API連接失敗',
        'token' => $tokenResult ? substr($tokenResult, 0, 10) . '...' : null
    ];
}

// 如果直接訪問此檔案，執行測試
if (php_sapi_name() == 'cli' || isset($_GET['test'])) {
    $test = testApiConnection();
    echo json_encode($test, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
