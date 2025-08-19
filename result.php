<?php
require_once 'amadeus_api.php';

// 已移除本地幣別換算：直接使用 API 回傳的幣別與金額

// 取得幣別符號
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

// 取得航空公司中文名稱
function getAirlineName($code) {
    $airlines = [
        'CI' => '中華航空',
        'BR' => '長榮航空',
        'JL' => '日本航空',
        'NH' => '全日空',
        'CX' => '國泰航空',
        'KA' => '國泰港龍',
        'MH' => '馬來西亞航空',
        'SQ' => '新加坡航空',
        'TG' => '泰國航空',
        'VN' => '越南航空',
        'KE' => '大韓航空',
        'OZ' => '韓亞航空',
        'UA' => '聯合航空',
        'AA' => '美國航空',
        'DL' => '達美航空',
        'LH' => '漢莎航空',
        'AF' => '法國航空',
        'KL' => '荷蘭皇家航空',
        'BA' => '英國航空',
        'EK' => '阿聯酋航空',
        'QR' => '卡達航空',
        'TK' => '土耳其航空',
        'AY' => '芬蘭航空',
        'AE' => '華信航空',
        'IT' => '台灣虎航',
        'GE' => '復興航空',
        'B7' => '立榮航空'
    ];
    return $airlines[$code] ?? $code;
}

$from = $_POST['from'] ?? '';
$to = $_POST['to'] ?? '';
$date = $_POST['date'] ?? '';
$tripType = $_POST['trip_type'] ?? 'oneway';
$returnDate = $_POST['return_date'] ?? '';
$currency = $_POST['currency'] ?? 'TWD';
$adults = intval($_POST['adults'] ?? 1);
$children = intval($_POST['children'] ?? 0);
$cabinClass = $_POST['cabin_class'] ?? 'ECONOMY';
// 進階選項
$nonStop = isset($_POST['non_stop']) && $_POST['non_stop'] !== '' ? (bool)intval($_POST['non_stop']) : null;
$maxPrice = isset($_POST['max_price']) && $_POST['max_price'] !== '' ? (int)$_POST['max_price'] : null;
$includedAirlines = isset($_POST['included_airlines']) && $_POST['included_airlines'] !== '' ? trim($_POST['included_airlines']) : null;
$excludedAirlines = isset($_POST['excluded_airlines']) && $_POST['excluded_airlines'] !== '' ? trim($_POST['excluded_airlines']) : null;
$infants = intval($_POST['infants'] ?? 0);

function formatDateTime($dt) {
    $d = new DateTime($dt);
    return $d->format('Y-m-d H:i');
}

function formatDuration($duration) {
    // 支援 ISO8601（PTxHxM）或分鐘數
    if (is_string($duration)) {
        $minutes = 0;
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $duration, $m)) {
            $h = isset($m[1]) && $m[1] !== '' ? intval($m[1]) : 0;
            $min = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : 0;
            $minutes = $h * 60 + $min;
        }
    } else {
        $minutes = intval($duration);
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%d小時%02d分", $hours, $mins);
}

function durationToMinutes($duration) {
    if (is_numeric($duration)) return (int)$duration;
    $minutes = 0;
    if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/i', $duration, $m)) {
        $h = isset($m[1]) && $m[1] !== '' ? intval($m[1]) : 0;
        $min = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : 0;
        $minutes = $h * 60 + $min;
    }
    return $minutes;
}

// 已移除月曆/月份比價功能

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>航班搜尋結果 - 機票比價</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; color: #333; }
        .result-box { max-width: 1000px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 2px 16px #0001; padding: 32px; }
        .flight-card { border: 1px solid #e3e3e3; border-radius: 12px; padding: 20px; margin-bottom: 24px; background: #f9f9fb; transition: all 0.2s; }
        .flight-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px #0001; }
        .flight-header { font-size: 1.2em; font-weight: 600; margin-bottom: 12px; color: #2563eb; }
        .price { font-size: 1.4em; color: #0d6efd; font-weight: bold; }
        .back-link { margin-bottom: 24px; display: inline-block; color: #666; text-decoration: none; }
        .back-link:hover { color: #333; }
        .flight-detail { display: flex; align-items: center; margin: 15px 0; }
        .flight-time { font-size: 1.1em; font-weight: 500; }
        .flight-duration { color: #666; font-size: 0.9em; }
        .flight-path { flex: 1; margin: 0 20px; position: relative; }
        .flight-path::after { content: ''; position: absolute; top: 50%; left: 0; right: 0; border-top: 2px dashed #ccc; }
    /* 月曆顯示與最佳標籤樣式已移除 */
        .flight-info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .airline-logo { width: 32px; height: 32px; margin-right: 10px; }
        .stopover { color: #dc3545; font-size: 0.9em; }
        .filter-section { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .chip { display:inline-flex; align-items:center; gap:6px; background:#f3f4f6; color:#4b5563; padding:6px 10px; border-radius:999px; font-size:.85em; margin-right:6px; }
        .badge-stop { background:#e2e8f0; color:#334155; }
    .ribbon { font-size: .75em; background:#10b981; color:white; padding:2px 8px; border-radius: 999px; margin-left:8px; }
    .ribbon.fast { background:#f59e0b; }
    </style>
</head>
<body>
    <div class="result-box">
        <a href="index.php" class="back-link">
            <i class="bi bi-arrow-left"></i> 返回搜尋
        </a>
        
        <?php if ($from && $to): ?>
            <h2 class="mb-2">
                <?= htmlspecialchars($from) ?> <i class="bi bi-arrow-right"></i> <?= htmlspecialchars($to) ?>
                <?php if ($tripType === 'round'): ?>
                    <i class="bi bi-arrow-left-right"></i>
                <?php endif; ?>
            </h2>
            <div class="mb-4">
                <span class="chip"><i class="bi bi-calendar-event"></i> 出發：<?= htmlspecialchars($date ?: '-') ?><?php if ($tripType==='round' && $returnDate): ?>，回程：<?= htmlspecialchars($returnDate) ?><?php endif; ?></span>
                <span class="chip"><i class="bi bi-people"></i> 成人：<?= (int)$adults ?><?php if ($children): ?>，兒童：<?= (int)$children ?><?php endif; ?><?php if ($infants): ?>，嬰兒：<?= (int)$infants ?><?php endif; ?></span>
                <?php if ($cabinClass): ?><span class="chip"><i class="bi bi-luggage"></i> 艙等：<?= htmlspecialchars($cabinClass) ?></span><?php endif; ?>
                <?php if ($nonStop !== null): ?><span class="chip"><i class="bi bi-lightning"></i> <?= $nonStop ? '只看直飛' : '可轉機' ?></span><?php endif; ?>
                <?php if ($maxPrice): ?><span class="chip"><i class="bi bi-cash-coin"></i> 最高價：<?= (int)$maxPrice ?></span><?php endif; ?>
                <?php if ($includedAirlines): ?><span class="chip"><i class="bi bi-check-circle"></i> 僅：<?= htmlspecialchars($includedAirlines) ?></span><?php endif; ?>
                <?php if ($excludedAirlines): ?><span class="chip"><i class="bi bi-x-circle"></i> 排除：<?= htmlspecialchars($excludedAirlines) ?></span><?php endif; ?>
            </div>

            
                <?php 
                $max = 30; // 一次抓較多資料，前端再分段呈現
                $result = searchFlights($from, $to, $date, $adults, $max, $children, $cabinClass, $currency, $nonStop, $maxPrice, $includedAirlines, $excludedAirlines, $infants);
                $returnResult = ($tripType === 'round' && $returnDate)
                    ? searchFlights($to, $from, $returnDate, $adults, $max, $children, $cabinClass, $currency, $nonStop, $maxPrice, $includedAirlines, $excludedAirlines, $infants)
                    : null;
                ?>

                <?php if (isset($result['data'])): ?>
                    <!-- 篩選器 -->
                    <div class="filter-section">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">航空公司</label>
                                <select class="form-select" id="airlineFilter">
                                    <option value="">全部航空公司</option>
                                    <?php
                                    $airlines = array_unique(array_map(function($offer) {
                                        return $offer['itineraries'][0]['segments'][0]['carrierCode'];
                                    }, $result['data']));
                                    foreach ($airlines as $airline) {
                                        echo "<option value='$airline'>" . getAirlineName($airline) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">排序</label>
                                <select class="form-select" id="sortMode">
                                    <option value="price-asc">價格由低到高</option>
                                    <option value="price-desc">價格由高到低</option>
                                    <option value="duration-asc">總時長由短到長</option>
                                    <option value="duration-desc">總時長由長到短</option>
                                    <option value="depart-asc">起飛時間由早到晚</option>
                                    <option value="depart-desc">起飛時間由晚到早</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">起飛時間</label>
                                <select class="form-select" id="departureTime">
                                    <option value="">全部時段</option>
                                    <option value="morning">早上 (06:00-12:00)</option>
                                    <option value="afternoon">下午 (12:00-18:00)</option>
                                    <option value="evening">晚上 (18:00-24:00)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">轉機次數</label>
                                <select class="form-select" id="stopsFilter">
                                    <option value="">不限</option>
                                    <option value="0">直飛</option>
                                    <option value="1">至多 1 次</option>
                                    <option value="2">至多 2 次</option>
                                </select>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">價格以 API 回傳貨幣顯示</div>
                    </div>

                    <!-- 去程航班列表 -->
                    <h4 class="mb-3">去程航班</h4>
                    <?php 
                    // 標記去程最便宜/最快
                    $outPrices = array_map(fn($o) => (float)$o['price']['total'], $result['data']);
                    $outDurations = array_map(fn($o) => durationToMinutes($o['itineraries'][0]['duration']), $result['data']);
                    $outMinPrice = !empty($outPrices) ? min($outPrices) : null;
                    $outMinDuration = !empty($outDurations) ? min($outDurations) : null;
                    ?>
                    <div id="outboundList">
                    <?php foreach ($result['data'] as $idx => $offer):
                        $price = (float)$offer['price']['total'];
                        $offerCurrency = $offer['price']['currency'] ?? $currency;
                        $segments = $offer['itineraries'][0]['segments'];
                        $firstSegment = $segments[0];
                        $lastSegment = end($segments);
                        $depTs = $firstSegment['departure']['at'];
                        $totalMin = durationToMinutes($offer['itineraries'][0]['duration']);
                        $stops = max(0, count($segments) - 1);
                        $isCheapest = ($outMinPrice !== null && (float)$price == (float)$outMinPrice);
                        $isFastest = ($outMinDuration !== null && (int)$totalMin === (int)$outMinDuration);
                    ?>
                    <div class="flight-card <?= $idx >= 10 ? 'd-none more-item' : '' ?>" data-airline="<?= $firstSegment['carrierCode'] ?>" data-price="<?= $price ?>" data-duration="<?= $totalMin ?>" data-departure="<?= htmlspecialchars($depTs) ?>" data-stops="<?= $stops ?>">
                        <div class="flight-header d-flex justify-content-between align-items-center">
                            <div>
                                <img src="https://pics.avs.io/120/60/<?= $firstSegment['carrierCode'] ?>.png" alt="<?= getAirlineName($firstSegment['carrierCode']) ?>" class="airline-logo">
                                <?= getAirlineName($firstSegment['carrierCode']) ?>
                                <?php if ($isCheapest): ?><span class="ribbon">最便宜</span><?php endif; ?>
                                <?php if ($isFastest): ?><span class="ribbon fast">最快</span><?php endif; ?>
                            </div>
                            <div class="price"><?= getCurrencySymbol($offerCurrency) ?><?= number_format($price) ?></div>
                        </div>

                        <div class="flight-detail">
                            <div class="text-center">
                                <div class="flight-time"><?= formatDateTime($firstSegment['departure']['at']) ?></div>
                                <div><?= $firstSegment['departure']['iataCode'] ?></div>
                            </div>
                            <div class="flight-path text-center">
                                <div class="flight-duration">
                                    <i class="bi bi-clock"></i> <?= formatDuration($offer['itineraries'][0]['duration']) ?>
                                    <div class="mt-1">
                                        <span class="badge rounded-pill badge-stop"><?= count($segments) > 1 ? (count($segments) - 1) . ' 次轉機' : '直飛' ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="flight-time"><?= formatDateTime($lastSegment['arrival']['at']) ?></div>
                                <div><?= $lastSegment['arrival']['iataCode'] ?></div>
                            </div>
                        </div>

                        <?php if (count($segments) > 1): ?>
                            <div class="mt-3">
                                <small class="text-muted">
                                    轉機地點：
                                    <?php
                                    $stops = array_map(function($seg) {
                                        return $seg['arrival']['iataCode'];
                                    }, array_slice($segments, 0, -1));
                                    echo implode(' → ', $stops);
                                    ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <?php if (count($result['data']) > 10): ?>
                        <div class="text-center mt-2">
                            <button class="btn btn-outline-secondary btn-sm" id="moreOutbound">顯示更多</button>
                        </div>
                    <?php endif; ?>

            <?php if ($returnResult && isset($returnResult['data'])): ?>
                        <!-- 回程航班列表 -->
                        <h4 class="mb-3 mt-4">回程航班</h4>
                        <?php 
                        $inPrices = array_map(fn($o) => (float)$o['price']['total'], $returnResult['data']);
                        $inDurations = array_map(fn($o) => durationToMinutes($o['itineraries'][0]['duration']), $returnResult['data']);
                        $inMinPrice = !empty($inPrices) ? min($inPrices) : null;
                        $inMinDuration = !empty($inDurations) ? min($inDurations) : null;
                        ?>
                        <div id="returnList">
                        <?php foreach ($returnResult['data'] as $idx => $offer):
                $price = (float)$offer['price']['total'];
                $offerCurrency = $offer['price']['currency'] ?? $currency;
                            $segments = $offer['itineraries'][0]['segments'];
                            $firstSegment = $segments[0];
                            $lastSegment = end($segments);
                            $depTs = $firstSegment['departure']['at'];
                            $totalMin = durationToMinutes($offer['itineraries'][0]['duration']);
                            $stops = max(0, count($segments) - 1);
                            $isCheapest = ($inMinPrice !== null && (float)$price == (float)$inMinPrice);
                            $isFastest = ($inMinDuration !== null && (int)$totalMin === (int)$inMinDuration);
                        ?>
                        <div class="flight-card <?= $idx >= 10 ? 'd-none more-item' : '' ?>" data-airline="<?= $firstSegment['carrierCode'] ?>" data-price="<?= $price ?>" data-duration="<?= $totalMin ?>" data-departure="<?= htmlspecialchars($depTs) ?>" data-stops="<?= $stops ?>">
                            <div class="flight-header d-flex justify-content-between align-items-center">
                                <div>
                                    <img src="https://pics.avs.io/120/60/<?= $firstSegment['carrierCode'] ?>.png" alt="<?= getAirlineName($firstSegment['carrierCode']) ?>" class="airline-logo">
                                    <?= getAirlineName($firstSegment['carrierCode']) ?>
                                    <?php if ($isCheapest): ?><span class="ribbon">最便宜</span><?php endif; ?>
                                    <?php if ($isFastest): ?><span class="ribbon fast">最快</span><?php endif; ?>
                                </div>
                <div class="price"><?= getCurrencySymbol($offerCurrency) ?><?= number_format($price) ?></div>
                            </div>

                            <div class="flight-detail">
                                <div class="text-center">
                                    <div class="flight-time"><?= formatDateTime($firstSegment['departure']['at']) ?></div>
                                    <div><?= $firstSegment['departure']['iataCode'] ?></div>
                                </div>
                                <div class="flight-path text-center">
                                    <div class="flight-duration">
                                        <i class="bi bi-clock"></i> <?= formatDuration($offer['itineraries'][0]['duration']) ?>
                                        <div class="mt-1">
                                            <span class="badge rounded-pill badge-stop"><?= count($segments) > 1 ? (count($segments) - 1) . ' 次轉機' : '直飛' ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="flight-time"><?= formatDateTime($lastSegment['arrival']['at']) ?></div>
                                    <div><?= $lastSegment['arrival']['iataCode'] ?></div>
                                </div>
                            </div>

                            <?php if (count($segments) > 1): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        轉機地點：
                                        <?php
                                        $stops = array_map(function($seg) {
                                            return $seg['arrival']['iataCode'];
                                        }, array_slice($segments, 0, -1));
                                        echo implode(' → ', $stops);
                                        ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if (count($returnResult['data']) > 10): ?>
                            <div class="text-center mt-2">
                                <button class="btn btn-outline-secondary btn-sm" id="moreReturn">顯示更多</button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php 
                    $errMsg = $result['error'] ?? '查無符合條件的航班或發生API錯誤';
                    $errCode = $result['code'] ?? '';
                    $detail = $result['detail'] ?? '';
                    $http = $result['http_code'] ?? '';
                    ?>
                    <div class="alert alert-warning mt-4">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= htmlspecialchars($errMsg) ?>
                        <?php if ($errCode || $http || $detail): ?>
                            <div class="mt-1 small text-muted">
                                <?php if ($http): ?>HTTP 狀態：<?= htmlspecialchars((string)$http) ?><?php endif; ?>
                                <?php if ($errCode): ?>　錯誤代碼：<?= htmlspecialchars($errCode) ?><?php endif; ?>
                                <?php if ($detail): ?>　詳細：<?= htmlspecialchars($detail) ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-danger">參數錯誤。</div>
        <?php endif; ?>
    </div>

    <script>
    // 航班篩選功能
    function filterFlights() {
        const airline = document.getElementById('airlineFilter')?.value || '';
        const sortMode = document.getElementById('sortMode')?.value || 'price-asc';
        const departureTime = document.getElementById('departureTime')?.value || '';
        const stopsFilter = document.getElementById('stopsFilter')?.value || '';

        const flights = Array.from(document.getElementsByClassName('flight-card'));

        flights.forEach(flight => {
            const fAirline = flight.dataset.airline;
            const fStops = parseInt(flight.dataset.stops || '0', 10);
            let show = true;

            if (airline && fAirline !== airline) show = false;

            if (departureTime) {
                const timeStr = flight.querySelector('.flight-time').textContent.trim();
                const timePart = timeStr.includes(' ') ? timeStr.split(' ')[1] : timeStr;
                const hour = parseInt(timePart.split(':')[0], 10);
                if (departureTime === 'morning' && (hour < 6 || hour >= 12)) show = false;
                if (departureTime === 'afternoon' && (hour < 12 || hour >= 18)) show = false;
                if (departureTime === 'evening' && (hour < 18 || hour >= 24)) show = false;
            }

            if (stopsFilter !== '') {
                const maxStops = parseInt(stopsFilter, 10);
                if (isFinite(maxStops) && fStops > maxStops) show = false;
            }

            flight.style.display = show ? '' : 'none';
        });

        // 排序：按容器分別排序（去程與回程分開）
        const keyOf = (el) => {
            if (sortMode.startsWith('price')) return parseFloat(el.dataset.price || '0');
            if (sortMode.startsWith('duration')) return parseInt(el.dataset.duration || '0', 10);
            if (sortMode.startsWith('depart')) return Date.parse(el.dataset.departure || 0);
            return 0;
        };

        const groups = new Map();
        flights.forEach(f => {
            const parent = f.parentNode;
            if (!groups.has(parent)) groups.set(parent, []);
            if (f.style.display !== 'none') groups.get(parent).push(f);
        });

        groups.forEach((list, parent) => {
            list.sort((a, b) => {
                const aKey = keyOf(a); const bKey = keyOf(b);
                const desc = sortMode.endsWith('desc');
                return desc ? (bKey - aKey) : (aKey - bKey);
            }).forEach(el => parent.appendChild(el));
        });
    }

    // 顯示更多
    document.getElementById('moreOutbound')?.addEventListener('click', () => {
        document.querySelectorAll('#outboundList .more-item.d-none').forEach((el, i) => { if (i < 10) el.classList.remove('d-none'); });
        if (!document.querySelector('#outboundList .more-item.d-none')) document.getElementById('moreOutbound').remove();
    });
    document.getElementById('moreReturn')?.addEventListener('click', () => {
        document.querySelectorAll('#returnList .more-item.d-none').forEach((el, i) => { if (i < 10) el.classList.remove('d-none'); });
        if (!document.querySelector('#returnList .more-item.d-none')) document.getElementById('moreReturn').remove();
    });

    // 篩選器事件
    document.getElementById('airlineFilter')?.addEventListener('change', filterFlights);
    document.getElementById('sortMode')?.addEventListener('change', filterFlights);
    document.getElementById('departureTime')?.addEventListener('change', filterFlights);
    document.getElementById('stopsFilter')?.addEventListener('change', filterFlights);
    </script>
</body>
</html>
