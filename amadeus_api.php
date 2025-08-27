<?php
require_once 'env.php';
loadEnv();

ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * AmadeusApi 類別
 *
 * Amadeus Self-Service Flight Offers Search API 的一個封裝層。
 * 處理身份驗證 (OAuth2)、權杖快取和 API 請求。
 */
class AmadeusApi {
    // API 設定的常數
    private const API_TIMEOUT = 15;
    private const MAX_RETRIES = 2;
    private const TOKEN_CACHE_FILE = __DIR__ . '/.amadeus_token.cache';

    private string $endpoint;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $accessToken = null;
    private bool $debugMode;

    /**
     * AmadeusApi 類別的建構函式。
     *
     * @param bool $debugMode 是否啟用除錯模式，啟用後會記錄 API 請求和錯誤。
     */
    public function __construct(bool $debugMode = false) {
        $this->debugMode = $debugMode;
        $this->clientId = $_ENV['AMADEUS_CLIENT_ID'] ?? null;
        $this->clientSecret = $_ENV['AMADEUS_CLIENT_SECRET'] ?? null;

        // 從環境變數決定 API 端點 (測試或正式環境)
        $amadeusEnv = $_ENV['AMADEUS_ENV'] ?? 'test';
        $this->endpoint = $_ENV['AMADEUS_API_ENDPOINT'] 
            ?? (($amadeusEnv === 'test') ? 'https://test.api.amadeus.com' : 'https://api.amadeus.com');
    }

    /**
     * 如果啟用除錯模式，則記錄訊息。
     */
    private function log($message): void {
        if ($this->debugMode) {
            $timestamp = date('Y-m-d H:i:s');
            error_log("[$timestamp] " . print_r($message, true));
        }
    }

    /**
     * 處理 API 錯誤並將其格式化為一致的陣列。
     */
    private function handleApiError(array $response, int $httpCode): array {
        $error = $response['errors'][0] ?? [];
        $errorCode = $error['code'] ?? 'SYSTEM_ERROR';
        $errorDetail = $error['detail'] ?? '發生未知錯誤。';

        $this->log(['error_code' => $errorCode, 'http_code' => $httpCode, 'detail' => $errorDetail]);

        return ['error' => $errorDetail, 'code' => $errorCode, 'http_code' => $httpCode];
    }

    /**
     * 使用 cURL 發送一個通用的 API 請求。
     */
    private function makeRequest(string $url, array $headers = [], string $method = 'GET', ?string $body = null, int $retry = 0): array {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_CUSTOMREQUEST => $method,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->log("cURL 錯誤: $curlError");
            if ($retry < self::MAX_RETRIES) {
                $this->log("重試請求 #" . ($retry + 1));
                return $this->makeRequest($url, $headers, $method, $body, $retry + 1);
            }
            return ['error' => 'API 連接失敗', 'http_code' => 0];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            return $this->handleApiError($decoded ?: ['errors' => [['detail' => $response]]], $httpCode);
        }

        $this->log(['url' => $url, 'method' => $method, 'http_code' => $httpCode]);
        return $decoded ?? [];
    }

    /**
     * 獲取 API access token，如果快取可用則使用快取。
     */
    private function getAccessToken(bool $forceRefresh = false): ?string {
        if (!$forceRefresh && $this->accessToken) {
            return $this->accessToken;
        }

        if (!$forceRefresh) {
            $cached = $this->readCachedToken();
            if ($cached) {
                $this->accessToken = $cached;
                return $this->accessToken;
            }
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            $this->log('API 憑證未設定。');
            return null;
        }

        $url = $this->endpoint . '/v1/security/oauth2/token';
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $result = $this->makeRequest($url, $headers, 'POST', $body);

        if (isset($result['access_token'])) {
            $this->accessToken = $result['access_token'];
            $expiresIn = $result['expires_in'] ?? 1700;
            $this->cacheToken($this->accessToken, $expiresIn);
            return $this->accessToken;
        }

        return null;
    }

    private function readCachedToken(): ?string {
        if (!file_exists(self::TOKEN_CACHE_FILE)) return null;
        $raw = @file_get_contents(self::TOKEN_CACHE_FILE);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['token'], $data['expires_at'])) return null;
        if (time() >= (int)$data['expires_at'] - 60) return null; // 60 秒的緩衝時間
        return $data['token'];
    }

    private function cacheToken(string $token, int $expiresIn): void {
        $payload = ['token' => $token, 'expires_at' => time() + $expiresIn];
        @file_put_contents(self::TOKEN_CACHE_FILE, json_encode($payload));
    }

    /**
     * 根據提供的參數搜尋航班。
     *
     * @param array $params API 呼叫的搜尋參數。
     * @return array API 回應的關聯陣列。
     */
    public function searchFlights(array $params): array {
        if (!$this->getAccessToken()) {
            return ['error' => '無法獲取 API access token。'];
        }

        $queryParams = http_build_query($params);
        $url = $this->endpoint . "/v2/shopping/flight-offers?{$queryParams}";
        $headers = ["Authorization: Bearer {$this->accessToken}"];

        $result = $this->makeRequest($url, $headers);

        // 處理 token 過期並自動重試一次。
        if (isset($result['code']) && ($result['code'] === 38190 || ($result['http_code'] ?? null) === 401)) {
            $this->log('Access token 已過期，正在刷新並重試。');
            if ($this->getAccessToken(true)) { // 強制刷新
                $headers = ["Authorization: Bearer {$this->accessToken}"];
                $result = $this->makeRequest($url, $headers);
            }
        }

        return $result;
    }
}

/**
 * 為了向後相容而保留的舊版函式封裝。
 * 這允許 result.php 頁面呼叫一個簡單的函式，而無需了解類別的實作細節。
 */
function searchFlights($origin, $destination, $departureDate, $adults = 1, $max = 5, $children = 0, $travelClass = null, $currency = 'USD', $nonStop = null, $maxPrice = null, $includedAirlineCodes = null, $excludedAirlineCodes = null, $infants = 0) {
    $api = new AmadeusApi(true); // 啟用除錯模式

    $params = [
        'originLocationCode' => strtoupper($origin),
        'destinationLocationCode' => strtoupper($destination),
        'departureDate' => $departureDate,
        'adults' => max(1, (int)$adults),
        'max' => max(1, (int)$max),
        'currencyCode' => $currency ?: 'USD',
    ];

    if ($children > 0) $params['children'] = (int)$children;
    if ($infants > 0) $params['infants'] = (int)$infants;
    if ($travelClass) $params['travelClass'] = $travelClass;
    if ($nonStop !== null) $params['nonStop'] = $nonStop ? 'true' : 'false';
    if ($maxPrice !== null && $maxPrice !== '') $params['maxPrice'] = (int)$maxPrice;
    if ($includedAirlineCodes) $params['includedAirlineCodes'] = strtoupper(str_replace(' ', '', $includedAirlineCodes));
    if ($excludedAirlineCodes) $params['excludedAirlineCodes'] = strtoupper(str_replace(' ', '', $excludedAirlineCodes));

    return $api->searchFlights($params);
}