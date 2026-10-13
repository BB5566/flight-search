<?php
// 引入必要的檔案
require_once 'amadeus_api.php'; // API 客戶端類別
require_once 'utils.php';       // 輔助函式 (例如 getAirlineName)

function normalizeIataCode(string $value): string {
    return strtoupper(trim($value));
}

function isValidDateInput(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

$hasSearchRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$validationErrors = [];

// --- 1. 從 POST 獲取並清理輸入 --- 
// 從表單提交中獲取所有搜尋參數並進行清理。
$from = normalizeIataCode($_POST['from'] ?? '');
$to = normalizeIataCode($_POST['to'] ?? '');
$date = trim($_POST['date'] ?? '');
$tripType = ($_POST['trip_type'] ?? 'oneway') === 'round' ? 'round' : 'oneway';
$returnDate = ($tripType === 'round') ? trim($_POST['return_date'] ?? '') : '';
$currency = strtoupper(trim($_POST['currency'] ?? 'TWD'));
$adults = max(1, (int)($_POST['adults'] ?? 1));
$children = max(0, (int)($_POST['children'] ?? 0));
$infants = max(0, (int)($_POST['infants'] ?? 0));
$cabinClass = strtoupper(trim($_POST['cabin_class'] ?? 'ECONOMY'));
$nonStop = !empty($_POST['non_stop']);

$allowedCurrencies = ['TWD', 'USD', 'JPY', 'HKD', 'EUR'];
$allowedCabinClasses = ['ECONOMY', 'PREMIUM_ECONOMY', 'BUSINESS', 'FIRST'];

if ($hasSearchRequest) {
    if (!$from || !$to || !$date) {
        $validationErrors[] = '請完整填寫出發地、目的地與出發日期。';
    }
    if (!preg_match('/^[A-Z]{3}$/', $from) || !preg_match('/^[A-Z]{3}$/', $to)) {
        $validationErrors[] = '出發地與目的地必須是有效的三碼機場代碼。';
    }
    if ($from && $to && $from === $to) {
        $validationErrors[] = '出發地與目的地不能相同。';
    }
    if ($date && !isValidDateInput($date)) {
        $validationErrors[] = '出發日期格式不正確。';
    }
    if ($tripType === 'round') {
        if (!$returnDate) {
            $validationErrors[] = '來回行程請填寫回程日期。';
        } elseif (!isValidDateInput($returnDate)) {
            $validationErrors[] = '回程日期格式不正確。';
        } elseif (isValidDateInput($date) && $returnDate < $date) {
            $validationErrors[] = '回程日期不可早於出發日期。';
        }
    }
    if (!in_array($currency, $allowedCurrencies, true)) {
        $validationErrors[] = '幣別設定不正確，請重新搜尋。';
    }
    if (!in_array($cabinClass, $allowedCabinClasses, true)) {
        $validationErrors[] = '艙等設定不正確，請重新搜尋。';
    }
    if ($adults > 9 || $children > 9 || $infants > 9 || $infants > $adults) {
        $validationErrors[] = '旅客人數設定不正確，請重新調整。';
    }
}

// --- 2. 從 API 獲取航班資料 --- 
$outboundResult = [];
$returnResult = [];
$apiError = null;

// 僅在擁有最基本的必要參數時才繼續。
if ($hasSearchRequest && empty($validationErrors)) {
    $api = new AmadeusApi(true); // 實例化 API 客戶端 (true = 開啟除錯模式)

    // 準備用於 API 呼叫的參數陣列。
    $searchParams = array_filter([
        'originLocationCode' => $from,
        'destinationLocationCode' => $to,
        'departureDate' => $date,
        'adults' => $adults,
        'children' => $children,
        'infants' => $infants,
        'travelClass' => $cabinClass,
        'currencyCode' => $currency,
        'nonStop' => $nonStop ? 'true' : null, // API 需要字串 'true' 或 null
        'max' => 30 // 最多獲取 30 筆結果
    ]);

    // 呼叫 API 查詢去程航班。
    $outboundResult = $api->searchFlights($searchParams);

    // 如果第一次呼叫失敗，儲存錯誤訊息。
    if (isset($outboundResult['error'])) {
        $apiError = $outboundResult;
    } 
    // 如果第一次呼叫成功，且是來回行程，則搜尋回程航班。
    elseif ($tripType === 'round' && $returnDate) {
        $returnSearchParams = $searchParams;
        $returnSearchParams['originLocationCode'] = $to; // 交換出發地和目的地
        $returnSearchParams['destinationLocationCode'] = $from;
        $returnSearchParams['departureDate'] = $returnDate;
        
        $returnResult = $api->searchFlights($returnSearchParams);
        if (isset($returnResult['error'])) {
            $apiError = $returnResult; // 儲存第二次呼叫的錯誤訊息
        }
    }
}

// --- 3. 準備用於渲染的資料 --- 
$outboundFlights = $outboundResult['data'] ?? [];
$returnFlights = $returnResult['data'] ?? [];

/**
 * 從航班報價列表中計算最低價格和最短飛行時間。
 * @param array $flights - 航班報價列表。
 * @return array 包含 [最低價格, 最短時間] 的陣列。
 */
function getFlightStats($flights) {
    if (empty($flights)) return [null, null];
    $prices = array_map(fn($f) => (float)($f['price']['total'] ?? INF), $flights);
    $durations = array_map(fn($f) => durationToMinutes($f['itineraries'][0]['duration'] ?? INF), $flights);
    return [min($prices), min($durations)];
}

// 計算統計數據以顯示「最便宜」和「最快」的標籤。
[$outMinPrice, $outMinDuration] = getFlightStats($outboundFlights);
[$inMinPrice, $inMinDuration] = getFlightStats($returnFlights);

// 獲取結果中所有航空公司的唯一列表，用於篩選器下拉選單。
$availableAirlines = array_unique(array_filter(array_merge(
    array_column(array_map(fn($f) => $f['itineraries'][0]['segments'][0] ?? null, $outboundFlights), 'carrierCode'),
    array_column(array_map(fn($f) => $f['itineraries'][0]['segments'][0] ?? null, $returnFlights), 'carrierCode')
)));
sort($availableAirlines);

// --- 4. 渲染 HTML 頁面 --- 
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>航班搜尋結果 - <?= htmlspecialchars($from) ?> 至 <?= htmlspecialchars($to) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/results.css">
</head>
<body>
    <div class="result-container">
        <!-- 包含搜尋摘要的頁首 -->
        <header class="page-header">
            <a href="index.php" class="back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> 新的搜尋</a>
            <div class="flight-summary">
                <h2><?= htmlspecialchars($from) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i> <?= htmlspecialchars($to) ?></h2>
                <div class="chip-container">
                    <span class="chip"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= htmlspecialchars($date) ?><?= $returnDate ? ' - ' . htmlspecialchars($returnDate) : '' ?></span>
                    <span class="chip"><i class="bi bi-people" aria-hidden="true"></i> <?= $adults + $children + $infants ?> 位旅客</span>
                    <span class="chip"><i class="bi bi-briefcase" aria-hidden="true"></i> <?= htmlspecialchars($cabinClass) ?></span>
                    <?php if ($nonStop): ?><span class="chip"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> 僅直飛</span><?php endif; ?>
                </div>
            </div>
        </header>

        <main class="result-main">
        <?php if (!empty($outboundFlights)): ?>
            <!-- 客戶端篩選器控制項 -->
            <section class="filter-section" aria-label="航班篩選與排序">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <select class="form-select" id="airlineFilter" aria-label="依航空公司篩選">
                            <option value="">所有航空公司</option>
                            <?php foreach ($availableAirlines as $code) echo "<option value=\"".htmlspecialchars($code)."\">".htmlspecialchars(getAirlineName($code))."</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="sortMode" aria-label="航班排序方式">
                            <option value="price-asc">價格 (低至高)</option>
                            <option value="price-desc">價格 (高至低)</option>
                            <option value="duration-asc">時長 (短至長)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="stopsFilter" aria-label="依轉機次數篩選">
                            <option value="">任何轉機次數</option>
                            <option value="0">僅直飛</option>
                            <option value="1">最多 1 次轉機</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- 去程航班列表 -->
            <section class="flight-section outbound-section" aria-labelledby="outboundTitle">
                <h3 class="section-title mb-3" id="outboundTitle">
                    <i class="bi bi-airplane-engines me-2 text-primary" aria-hidden="true"></i>去程航班
                </h3>
                <div id="outboundList">
                    <?php foreach ($outboundFlights as $index => $offer) {
                        $isCheapest = ((float)($offer['price']['total'] ?? INF) === $outMinPrice);
                        $isFastest = (durationToMinutes($offer['itineraries'][0]['duration'] ?? INF) === $outMinDuration);
                        // 為每個報價引入可重複使用的航班卡片模板。
                        include 'templates/flight_card.php';
                    } ?>
                </div>
                <?php if (count($outboundFlights) > 10): ?><div class="text-center mt-3"><button class="btn btn-outline-secondary show-more-btn" id="moreOutbound">顯示更多去程航班</button></div><?php endif; ?>
            </section>

            <!-- 回程航班列表 (如果適用) -->
            <?php if (!empty($returnFlights)): ?>
                <section class="flight-section return-section" aria-labelledby="returnTitle">
                    <h3 class="section-title mt-5 mb-3" id="returnTitle">
                        <i class="bi bi-airplane-engines me-2 text-primary route-return-icon" aria-hidden="true"></i>回程航班
                    </h3>
                    <div id="returnList">
                        <?php foreach ($returnFlights as $index => $offer) {
                            $isCheapest = ((float)($offer['price']['total'] ?? INF) === $inMinPrice);
                            $isFastest = (durationToMinutes($offer['itineraries'][0]['duration'] ?? INF) === $inMinDuration);
                            include 'templates/flight_card.php';
                        } ?>
                    </div>
                    <?php if (count($returnFlights) > 10): ?><div class="text-center mt-3"><button class="btn btn-outline-secondary show-more-btn" id="moreReturn">顯示更多回程航班</button></div><?php endif; ?>
                </section>
            <?php endif; ?>

        <?php elseif (!empty($validationErrors)): ?>
            <div class="alert alert-warning mt-4 p-4" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-2 text-warning" aria-hidden="true"></i>
                    <h4 class="alert-heading mb-0 fs-5 fw-bold">輸入資料有誤</h4>
                </div>
                <ul class="mb-3 mt-2 ps-3">
                    <?php foreach ($validationErrors as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> 返回修改搜尋條件</a>
            </div>
        <?php elseif ($apiError): ?>
            <!-- 顯示 API 錯誤訊息 -->
            <div class="alert alert-danger mt-4 p-4" role="alert">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-exclamation-octagon-fill fs-4 me-2 text-danger" aria-hidden="true"></i>
                    <h4 class="alert-heading mb-0 fs-5 fw-bold">查詢失敗</h4>
                </div>
                <p class="mb-3"><?= htmlspecialchars($apiError['error'] ?? '發生未知錯誤') ?></p>
                <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> 返回重新搜尋</a>
            </div>
        <?php elseif ($hasSearchRequest): ?>
            <!-- 顯示找不到航班的訊息 -->
            <div class="alert alert-info mt-4 p-4 text-center" role="alert">
                <i class="bi bi-info-circle-fill fs-3 text-primary d-block mb-2" aria-hidden="true"></i>
                <h4 class="alert-heading fs-5 fw-bold">找不到符合條件的航班</h4>
                <p class="text-muted mb-3">請嘗試放寬您的搜尋條件，例如更換日期或允許轉機。</p>
                <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> 調整搜尋條件</a>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4 p-4 text-center" role="alert">
                <i class="bi bi-airplane fs-3 text-primary d-block mb-2" aria-hidden="true"></i>
                <h4 class="alert-heading fs-5 fw-bold">尚未開始搜尋</h4>
                <p class="text-muted mb-3">請先回到搜尋頁輸入出發地、目的地與日期。</p>
                <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i> 前往搜尋機票</a>
            </div>
        <?php endif; ?>
        </main>

    </div>
    <script src="js/results.js" defer></script>
</body>
</html>