
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>Flight Search - Modern & Professional</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 引入外部 CSS 檔案 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- 主要搜尋容器 -->
    <div class="search-container">
        <h1>探索蒼穹</h1>
        <div class="subtitle">即刻搜尋全球特價機票</div>

        <!-- 搜尋表單 -->
        <form method="POST" action="result.php" id="searchForm" novalidate>
            <!-- 使用 CSS Grid 進行表單佈局 -->
            <div class="form-layout-grid">
                
                <!-- 第 1 行: 機場選擇 -->
                <div class="form-group">
                    <label for="fromCode" class="form-label">出發地</label>
                    <select name="from" id="fromCode" class="form-select" required></select>
                </div>
                <button type="button" id="swapBtn" title="交換出發/目的地"><i class="bi bi-arrow-left-right"></i></button>
                <div class="form-group">
                    <label for="toCode" class="form-label">目的地</label>
                    <select name="to" id="toCode" class="form-select" required></select>
                </div>

                <!-- 第 2 行: 日期選擇 -->
                <div class="form-group">
                    <label for="departDate" class="form-label">出發日期</label>
                    <input type="date" class="form-control" name="date" id="departDate" required>
                </div>
                <!-- 用於對齊的空白佔位符 -->
                <div></div> 
                <div class="form-group return-date-container" id="return-date-container">
                    <label for="returnDate" class="form-label">回程日期</label>
                    <input type="date" class="form-control" name="return_date" id="returnDate">
                </div>

                <!-- 第 3 行: 乘客與幣別 -->
                 <div class="form-group">
                    <label class="form-label">乘客與艙等</label>
                    <div style="position: relative;">
                        <button class="form-control pax-button" type="button" id="openPaxBtn">
                            <i class="bi bi-people"></i> 1 位旅客, 經濟艙
                        </button>
                        <!-- 乘客選擇彈出視窗 -->
                        <div class="popover-card" id="paxPopover">
                            <div class="popover-row"><span>成人</span><div class="counter"><button type="button" data-counter="adults" data-delta="-1">-</button><span id="adults_val">1</span><button type="button" data-counter="adults" data-delta="1">+</button></div></div>
                            <div class="popover-row"><span>兒童</span><div class="counter"><button type="button" data-counter="children" data-delta="-1">-</button><span id="children_val">0</span><button type="button" data-counter="children" data-delta="1">+</button></div></div>
                            <div class="popover-row"><span>嬰兒</span><div class="counter"><button type="button" data-counter="infants" data-delta="-1">-</button><span id="infants_val">0</span><button type="button" data-counter="infants" data-delta="1">+</button></div></div>
                            <hr style="border-color: var(--border-color); margin: 12px 0;">
                            <div class="popover-row"><span>艙等</span><select class="form-select" id="cabinSelect"><option value="ECONOMY">經濟艙</option><option value="PREMIUM_ECONOMY">豪華經濟艙</option><option value="BUSINESS">商務艙</option><option value="FIRST">頭等艙</option></select></div>
                        </div>
                    </div>
                    <!-- 用於表單提交的隱藏欄位 -->
                    <input type="hidden" name="adults" id="adults" value="1">
                    <input type="hidden" name="children" id="children" value="0">
                    <input type="hidden" name="infants" id="infants" value="0">
                    <input type="hidden" name="cabin_class" id="cabin_class" value="ECONOMY">
                </div>

                <div></div> <!-- Spacer -->

                <div class="form-group">
                    <label for="currency" class="form-label">幣別</label>
                     <select class="form-select" name="currency" id="currency" required>
                        <option value="TWD" selected>新台幣 (TWD)</option>
                        <option value="USD">美元 (USD)</option>
                        <option value="JPY">日圓 (JPY)</option>
                        <option value="HKD">港幣 (HKD)</option>
                        <option value="EUR">歐元 (EUR)</option>
                    </select>
                </div>
            </div>

            <!-- 第 4 行: 選項切換 -->
            <div class="toggles-container grid-col-span-3">
                 <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="nonStopCheck" name="non_stop" value="1">
                    <label class="form-check-label" for="nonStopCheck">僅直飛</label>
                </div>
                <div class="trip-type-switch">
                    <span>單程</span>
                    <label class="switch">
                        <input type="checkbox" id="tripTypeToggle" name="trip_type" value="round">
                        <span class="slider"></span>
                    </label>
                    <span>來回</span>
                </div>
            </div>

            <!-- 提交按鈕 -->
            <button type="submit" class="btn btn-primary grid-col-span-3" id="submitBtn">
                <i class="bi bi-search"></i> 開始比價
            </button>
        </form>
    </div>

    <!-- 引入 JavaScript 檔案 -->
    <script src="js/main.js"></script> 
</body>
</html>
