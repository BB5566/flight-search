<?php
/**
 * templates/flight_card.php
 * 
 * 這是一個可重複使用的模板，用於顯示單個航班的資訊卡片。
 * 它被 result.php 頁面多次引入以建立航班列表。
 *
 * @var array $offer         來自 API 的單個航班報價資料。
 * @var string $currency      用戶選擇的貨幣。
 * @var bool $isCheapest      此航班是否為結果中最便宜的。
 * @var bool $isFastest       此航班是否為結果中飛行時間最短的。
 * @var int $index           此航班在列表中的索引（用於「顯示更多」功能）。
 */

// --- 1. 從 $offer 陣列中提取並準備變數 ---
$price = (float)($offer['price']['total'] ?? 0);
$offerCurrency = $offer['price']['currency'] ?? $currency;
$itinerary = $offer['itineraries'][0] ?? [];
$segments = $itinerary['segments'] ?? [];
$firstSegment = $segments[0] ?? null;
$lastSegment = end($segments);
$totalDurationMinutes = durationToMinutes($itinerary['duration'] ?? 0);
$stops = count($segments) - 1;

// 如果沒有有效的航段資料，則不渲染此卡片。
if (!$firstSegment) return;

$departureTimestamp = $firstSegment['departure']['at'] ?? '';

?>
<!-- --- 2. HTML 結構 --- -->
<div class="flight-card<?= ($index ?? 0) >= 10 ? ' more-item d-none' : '' ?>" 
     data-airline="<?= htmlspecialchars($firstSegment['carrierCode'] ?? '') ?>" 
     data-price="<?= $price ?>" 
     data-duration="<?= $totalDurationMinutes ?>" 
     data-departure="<?= htmlspecialchars($departureTimestamp) ?>" 
     data-stops="<?= $stops ?>">

    <!-- 如果適用，顯示「最便宜」或「最快」的標籤 -->
    <?php if ($isCheapest): ?><div class="ribbon ribbon-cheap" aria-label="最便宜航班"><span>最便宜</span></div><?php endif; ?>
    <?php if ($isFastest && !$isCheapest): ?><div class="ribbon fast ribbon-fast" aria-label="飛行時間最短航班"><span>最快</span></div><?php endif; ?>

    <!-- 卡片頂部：航空公司和價格 -->
    <div class="card-top-row d-flex justify-content-between align-items-center mb-3">
        <div class="airline-info d-flex align-items-center">
            <img src="https://pics.avs.io/120/60/<?= htmlspecialchars($firstSegment['carrierCode'] ?? '') ?>.png" 
                 alt="<?= htmlspecialchars(getAirlineName($firstSegment['carrierCode'] ?? '')) ?> 標誌" 
                 class="airline-logo"
                 loading="lazy"
                 onerror="this.style.display='none'">
            <span class="airline-name fw-semibold ms-2"><?= htmlspecialchars(getAirlineName($firstSegment['carrierCode'] ?? '')) ?></span>
        </div>
        <div class="price" aria-label="票價每人 <?= htmlspecialchars(getCurrencySymbol($offerCurrency)) ?><?= number_format($price) ?>">
            <span class="price-symbol"><?= htmlspecialchars(getCurrencySymbol($offerCurrency)) ?></span><?= number_format($price) ?>
        </div>
    </div>

    <!-- 卡片中間：航班詳細資訊 -->
    <div class="flight-details-row d-flex align-items-center justify-content-between">
        <!-- 出發資訊 -->
        <div class="flight-point departure text-start">
            <div class="flight-time"><?= htmlspecialchars(substr(formatDateTime($firstSegment['departure']['at'] ?? ''), 11)) ?></div>
            <div class="flight-airport-code"><?= htmlspecialchars($firstSegment['departure']['iataCode'] ?? '') ?></div>
        </div>

        <!-- 飛行路徑線條與飛行時長 -->
        <div class="flight-path" aria-hidden="true">
            <div class="flight-duration"><?= htmlspecialchars(formatDuration($itinerary['duration'] ?? 0)) ?></div>
            <div class="flight-path-line">
                <span class="line-segment"></span>
                <i class="bi bi-airplane flight-plane-icon"></i>
                <span class="line-segment"></span>
            </div>
        </div>

        <!-- 抵達資訊 -->
        <div class="flight-point arrival text-end">
            <div class="flight-time"><?= htmlspecialchars(substr(formatDateTime($lastSegment['arrival']['at'] ?? ''), 11)) ?></div>
            <div class="flight-airport-code"><?= htmlspecialchars($lastSegment['arrival']['iataCode'] ?? '') ?></div>
        </div>
        
        <!-- 飛行轉機標籤 -->
        <div class="flight-meta text-end">
            <span class="badge rounded-pill badge-stop <?= $stops === 0 ? 'badge-direct' : '' ?>">
                <?= $stops > 0 ? $stops . ' 次轉機' : '直飛' ?>
            </span>
        </div>
    </div>
</div>