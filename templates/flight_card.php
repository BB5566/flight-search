
<?php
// templates/flight_card.php

$price = (float)($offer['price']['total'] ?? 0);
$offerCurrency = $offer['price']['currency'] ?? $currency;
$itinerary = $offer['itineraries'][0] ?? [];
$segments = $itinerary['segments'] ?? [];
$firstSegment = $segments[0] ?? null;
$lastSegment = end($segments);
$totalDurationMinutes = durationToMinutes($itinerary['duration'] ?? 0);
$stops = count($segments) - 1;

if (!$firstSegment) return;

$departureTimestamp = $firstSegment['departure']['at'] ?? '';

?>
<div class="flight-card" 
     data-airline="<?= htmlspecialchars($firstSegment['carrierCode'] ?? '') ?>" 
     data-price="<?= $price ?>" 
     data-duration="<?= $totalDurationMinutes ?>" 
     data-departure="<?= htmlspecialchars($departureTimestamp) ?>" 
     data-stops="<?= $stops ?>">

    <?php if ($isCheapest): ?><div class="ribbon"><span>最便宜</span></div><?php endif; ?>
    <?php if ($isFastest && !$isCheapest): ?><div class="ribbon fast"><span>最快</span></div><?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <img src="https://pics.avs.io/120/60/<?= htmlspecialchars($firstSegment['carrierCode'] ?? '') ?>.png" 
                 alt="<?= htmlspecialchars(getAirlineName($firstSegment['carrierCode'] ?? '')) ?>" 
                 class="airline-logo d-inline-block align-middle">
            <span class="align-middle ms-2 fw-bold"><?= htmlspecialchars(getAirlineName($firstSegment['carrierCode'] ?? '')) ?></span>
        </div>
        <div class="price"><?= htmlspecialchars(getCurrencySymbol($offerCurrency)) ?><?= number_format($price) ?></div>
    </div>

    <div class="d-flex align-items-center">
        <div class="text-center">
            <div class="flight-time"><?= htmlspecialchars(substr(formatDateTime($firstSegment['departure']['at'] ?? ''), 11)) ?></div>
            <div class="flight-airport-code"><?= htmlspecialchars($firstSegment['departure']['iataCode'] ?? '') ?></div>
        </div>

        <div class="flight-path">
            <div class="line"></div>
        </div>

        <div class="text-center">
            <div class="flight-time"><?= htmlspecialchars(substr(formatDateTime($lastSegment['arrival']['at'] ?? ''), 11)) ?></div>
            <div class="flight-airport-code"><?= htmlspecialchars($lastSegment['arrival']['iataCode'] ?? '') ?></div>
        </div>
        
        <div class="text-center ms-5">
             <div class="flight-duration"><?= htmlspecialchars(formatDuration($itinerary['duration'] ?? 0)) ?></div>
             <div class="mt-1">
                <span class="badge rounded-pill badge-stop">
                    <?= $stops > 0 ? $stops . ' 次轉機' : '直飛' ?>
                </span>
            </div>
        </div>
    </div>
</div>
