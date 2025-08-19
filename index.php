<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>機票比價 - 全球機票搜尋比價</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='32' fill='%232563eb'/%3E%3Cpath d='M10 36l44-12-6 10-14 4 4 10-6 2-5-11-11 3z' fill='%23fff'/%3E%3C/svg%3E">
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.8/dist/axios.min.js"></script>
    <style>
        body { background: #f5f7fb; }
        .search-box { max-width: 820px; margin: 40px auto; background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.06); padding: 28px; border: 1px solid #eef2f7; }
        h1 { text-align: center; margin-bottom: 6px; color: #2563eb; font-weight: 800; letter-spacing: .5px; }
        .subtitle { text-align: center; color: #6b7280; margin-bottom: 24px; font-size: 0.95rem; }
        .tip { color: #0d6efd; font-size: 0.92em; margin-bottom: 16px; padding: 12px; background: #f8f9ff; border-radius: 8px; }
        .section-title { font-weight: 700; color: #111827; margin: 12px 0 8px; }
        .btn-primary { width: 100%; padding: 12px; font-size: 1.05em; }
        .airport-search { position: relative; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; max-height: 240px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .search-result-item { padding: 10px 12px; cursor: pointer; }
        .search-result-item:hover { background: #f3f4f6; }
        .region-title { font-weight: 600; padding: 6px 12px; color: #64748b; background: #f8fafc; }
        .date-warning { color: #856404; background-color: #fff3cd; padding: 8px; border-radius: 6px; margin-top: 6px; font-size: 0.9em; display: none; }
        .popover-card{border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08);padding:12px;background:#fff;width:280px;}
        .popover-row{display:flex;align-items:center;justify-content:space-between;margin:8px 0;}
        .counter{display:flex;align-items:center;gap:8px}
    </style>
</head>
<body>
    <div class="search-box">
        <h1><i class="bi bi-airplane"></i> 機票比價</h1>
        <div class="subtitle">專業搜尋介面，支援單程/來回、艙等、直飛、航空公司限制與價格上限等條件</div>
        <div class="tip"><i class="bi bi-info-circle"></i> 使用 Amadeus 測試端點進行查詢，實際票價與供應以航空公司/旅行社為準。</div>

        <form method="POST" action="result.php" onsubmit="return onSubmitSearch(event)" id="searchForm" novalidate>
            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-8">
                    <div class="section-title">航點</div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">出發地</label>
                            <div class="airport-search">
                                <input type="text" class="form-control" id="fromSearch" placeholder="輸入機場名稱或城市" autocomplete="off">
                                <input type="hidden" name="from" id="fromCode">
                                <div class="search-results" id="fromResults"></div>
                            </div>
                            <div class="mt-1">
                                <span class="badge text-bg-light me-1 quick-airport" data-code="TPE">TPE</span>
                                <span class="badge text-bg-light me-1 quick-airport" data-code="KHH">KHH</span>
                                <span class="badge text-bg-light me-1 quick-airport" data-code="HKG">HKG</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">目的地</label>
                            <div class="airport-search">
                                <input type="text" class="form-control" id="toSearch" placeholder="輸入機場名稱或城市" autocomplete="off">
                                <input type="hidden" name="to" id="toCode">
                                <div class="search-results" id="toResults"></div>
                            </div>
                            <div class="mt-1">
                                <span class="badge text-bg-light me-1 quick-airport-to" data-code="NRT">NRT</span>
                                <span class="badge text-bg-light me-1 quick-airport-to" data-code="KIX">KIX</span>
                                <span class="badge text-bg-light me-1 quick-airport-to" data-code="ICN">ICN</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-4" id="swapBtn" title="交換出發/目的地">
                        <i class="bi bi-arrow-left-right"></i> 交換
                    </button>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6" id="depart-date-group">
                    <div class="section-title">日期</div>
                    <label class="form-label">出發日期</label>
                    <input type="date" class="form-control" name="date" id="departDate" required>
                    <div class="date-warning" id="departDateWarning"></div>
                </div>
                <div class="col-md-6" id="return-date-group" style="display:none;">
                    <div class="section-title" style="visibility:hidden">日期</div>
                    <label class="form-label">回程日期</label>
                    <input type="date" class="form-control" name="return_date" id="returnDate">
                    <div class="date-warning" id="returnDateWarning"></div>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-8">
                    <div class="section-title">行程與幣別</div>
                    <div class="mb-2">
                        <div class="btn-group" role="group" aria-label="Trip type">
                            <input type="radio" class="btn-check" name="trip_type" id="oneway" value="oneway" checked>
                            <label class="btn btn-outline-primary" for="oneway">單程</label>
                            <input type="radio" class="btn-check" name="trip_type" id="round" value="round">
                            <label class="btn btn-outline-primary" for="round">來回</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">幣別</label>
                        <select class="form-select" name="currency" required>
                            <option value="USD">美元 (USD)</option>
                            <option value="TWD" selected>新台幣 (TWD)</option>
                            <option value="JPY">日圓 (JPY)</option>
                            <option value="CNY">人民幣 (CNY)</option>
                            <option value="EUR">歐元 (EUR)</option>
                            <option value="HKD">港幣 (HKD)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="section-title">乘客與艙等</div>
                    <button class="btn btn-outline-primary w-100" type="button" id="openPaxBtn">
                        <i class="bi bi-people"></i> 1 成人 · 經濟艙
                    </button>
                    <input type="hidden" name="adults" id="adults" value="1">
                    <input type="hidden" name="children" id="children" value="0">
                    <input type="hidden" name="infants" id="infants" value="0">
                    <input type="hidden" name="cabin_class" id="cabin_class" value="ECONOMY">
                    <div class="popover-card mt-2" id="paxPopover" style="display:none;">
                        <div class="popover-row">
                            <div>成人</div>
                            <div class="counter"><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="adults" data-delta="-1">-</button><span id="adults_val">1</span><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="adults" data-delta="1">+</button></div>
                        </div>
                        <div class="popover-row">
                            <div>兒童</div>
                            <div class="counter"><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="children" data-delta="-1">-</button><span id="children_val">0</span><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="children" data-delta="1">+</button></div>
                        </div>
                        <div class="popover-row">
                            <div>嬰兒</div>
                            <div class="counter"><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="infants" data-delta="-1">-</button><span id="infants_val">0</span><button type="button" class="btn btn-sm btn-outline-secondary" data-counter="infants" data-delta="1">+</button></div>
                        </div>
                        <div class="popover-row">
                            <div>艙等</div>
                            <select class="form-select form-select-sm w-auto" id="cabinSelect">
                                <option value="ECONOMY">經濟艙</option>
                                <option value="PREMIUM_ECONOMY">豪華經濟艙</option>
                                <option value="BUSINESS">商務艙</option>
                                <option value="FIRST">頭等艙</option>
                            </select>
                        </div>
                        <div class="text-end"><button type="button" class="btn btn-sm btn-primary" id="applyPax">套用</button></div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <details>
                    <summary class="form-label">進階選項</summary>
                    <div class="row g-2 mt-2">
                        <div class="col-md-3">
                            <label class="form-label">只看直飛</label>
                            <select class="form-select" name="non_stop">
                                <option value="">不限</option>
                                <option value="1">是</option>
                                <option value="0">否</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">最高價格 (USD)</label>
                            <input type="number" class="form-control" name="max_price" min="0" step="1" placeholder="如 500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">限定航空公司 (逗號分隔)</label>
                            <input type="text" class="form-control" name="included_airlines" placeholder="如 CI,BR,JL">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">排除航空公司 (逗號分隔)</label>
                            <input type="text" class="form-control" name="excluded_airlines" placeholder="如 LCC">
                        </div>
                    </div>
                </details>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span class="btn-text"><i class="bi bi-search"></i> 開始比價</span>
                <span class="spinner-border spinner-border-sm ms-2" id="loadingSpinner" style="display:none;" role="status" aria-hidden="true"></span>
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 常用機場清單（示例，可自行擴充）
    const airportGroups = {
        '台灣': [
            { code: 'TPE', name: '台北桃園國際機場' },
            { code: 'TSA', name: '台北松山機場' },
            { code: 'KHH', name: '高雄小港機場' },
            { code: 'RMQ', name: '台中清泉崗機場' }
        ],
        '日本': [
            { code: 'HND', name: '東京羽田機場' },
            { code: 'NRT', name: '東京成田機場' },
            { code: 'KIX', name: '大阪關西國際機場' },
            { code: 'CTS', name: '札幌新千歲機場' },
            { code: 'FUK', name: '福岡國際機場' },
            { code: 'NGO', name: '名古屋中部機場' }
        ],
        '亞洲': [
            { code: 'ICN', name: '首爾仁川國際機場' },
            { code: 'HKG', name: '香港國際機場' },
            { code: 'SIN', name: '新加坡樟宜機場' },
            { code: 'BKK', name: '曼谷素萬那普機場' },
            { code: 'MNL', name: '馬尼拉國際機場' }
        ]
    };

    function updateSearchResults(input, resultsDiv, hiddenField) {
        const value = input.value.trim().toLowerCase();
        resultsDiv.style.display = value ? 'block' : 'none';
        resultsDiv.innerHTML = '';
        if (!value) return;

        for (const [group, airports] of Object.entries(airportGroups)) {
            const matches = airports.filter(a => a.name.toLowerCase().includes(value) || a.code.toLowerCase().includes(value));
            if (!matches.length) continue;
            const title = document.createElement('div');
            title.className = 'region-title';
            title.textContent = group;
            resultsDiv.appendChild(title);
            matches.forEach(airport => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.textContent = `${airport.name} (${airport.code})`;
                div.onclick = () => {
                    input.value = airport.name;
                    hiddenField.value = airport.code;
                    resultsDiv.style.display = 'none';
                };
                resultsDiv.appendChild(div);
            });
        }
    }

    ['from', 'to'].forEach(type => {
        const input = document.getElementById(`${type}Search`);
        const results = document.getElementById(`${type}Results`);
        const hiddenField = document.getElementById(type === 'from' ? 'fromCode' : 'toCode');
        input.addEventListener('input', () => updateSearchResults(input, results, hiddenField));
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !results.contains(e.target)) results.style.display = 'none';
        });
    });

    // 交換出發/目的地
    document.getElementById('swapBtn').addEventListener('click', () => {
        const fromCode = document.getElementById('fromCode');
        const toCode = document.getElementById('toCode');
        const fromSearch = document.getElementById('fromSearch');
        const toSearch = document.getElementById('toSearch');
        [fromCode.value, toCode.value] = [toCode.value, fromCode.value];
        [fromSearch.value, toSearch.value] = [toSearch.value, fromSearch.value];
    });

    // 日期限制與切換
    const departDateGroup = document.getElementById('depart-date-group');
    const returnDateGroup = document.getElementById('return-date-group');
    const departInput = document.getElementById('departDate');
    const returnInput = document.getElementById('returnDate');

    document.querySelectorAll('input[type="date"]').forEach(el => {
        const today = new Date(); today.setHours(0,0,0,0);
        el.min = today.toISOString().split('T')[0];
        const max = new Date(); max.setMonth(max.getMonth() + 11);
        el.max = max.toISOString().split('T')[0];
        el.addEventListener('change', validateDates);
    });

    document.querySelectorAll('input[name="trip_type"]').forEach(r => r.addEventListener('change', toggleDateInputs));

    function toggleDateInputs() {
        const isRound = document.getElementById('round').checked;
        returnDateGroup.style.display = isRound ? '' : 'none';
        returnInput.required = isRound;
        returnInput.disabled = !isRound;
    }

    function validateDates() {
        const departWarning = document.getElementById('departDateWarning');
        const returnWarning = document.getElementById('returnDateWarning');
        const today = new Date(); today.setHours(0,0,0,0);
        const depart = departInput.value ? new Date(departInput.value) : null;
        const ret = returnInput.value ? new Date(returnInput.value) : null;
        const max = new Date(); max.setMonth(max.getMonth() + 11);

        if (depart && (depart < today || depart > max)) {
            departWarning.textContent = depart < today ? '出發日期不能早於今天' : '出發日期不能超過11個月';
            departWarning.style.display = 'block';
        } else { departWarning.style.display = 'none'; }

        if (ret) {
            if (ret < depart) { returnWarning.textContent = '回程日期不能早於出發日期'; returnWarning.style.display = 'block'; }
            else if (ret > max) { returnWarning.textContent = '回程日期不能超過11個月'; returnWarning.style.display = 'block'; }
            else { returnWarning.style.display = 'none'; }
        } else { returnWarning.style.display = 'none'; }
    }

    // 自動猜測出發地（可失敗忽略）
    axios.get('https://ipapi.co/json/').then(res => {
        const country = res.data.country || '';
        const city = (res.data.city || '');
        let guess = 'TPE';
        if (country === 'TW') {
            if (city.includes('高雄')) guess = 'KHH';
            else if (city.includes('台中')) guess = 'RMQ';
            else guess = 'TPE';
        } else if (country === 'JP') {
            if (city.includes('大阪')) guess = 'KIX';
            else if (city.includes('福岡')) guess = 'FUK';
            else if (city.includes('名古屋')) guess = 'NGO';
            else guess = 'HND';
        } else if (country === 'KR') guess = 'ICN';
        else if (country === 'HK') guess = 'HKG';
        else if (country === 'SG') guess = 'SIN';
        else if (country === 'TH') guess = 'BKK';

        const fromField = document.getElementById('fromCode');
        const airport = Object.values(airportGroups).flat().find(a => a.code === guess);
        fromField.value = guess;
        if (airport) document.getElementById('fromSearch').value = airport.name;
    }).catch(() => {});

    function onSubmitSearch(e) {
        const from = document.getElementById('fromCode').value.trim();
        const to = document.getElementById('toCode').value.trim();
        const isRound = document.getElementById('round').checked;
        if (!from || !to) { alert('請選擇出發地與目的地'); return false; }
        if (from === to) { alert('出發地與目的地不能相同'); return false; }
        if (!departInput.value) { alert('請選擇出發日期'); return false; }
        if (isRound && !returnInput.value) { alert('請選擇回程日期'); return false; }
        validateDates();
        const btn = document.getElementById('submitBtn');
        const spinner = document.getElementById('loadingSpinner');
        btn.disabled = true; spinner.style.display = '';
        return true;
    }

    // 初始化
    toggleDateInputs();

    // 熱門航點快速選擇
    document.querySelectorAll('.quick-airport').forEach(el => {
        el.addEventListener('click', () => {
            const code = el.dataset.code;
            const field = document.getElementById('fromCode');
            field.value = code;
            const airport = Object.values(airportGroups).flat().find(a => a.code === code);
            if (airport) document.getElementById('fromSearch').value = airport.name;
        });
    });
    document.querySelectorAll('.quick-airport-to').forEach(el => {
        el.addEventListener('click', () => {
            const code = el.dataset.code;
            const field = document.getElementById('toCode');
            field.value = code;
            const airport = Object.values(airportGroups).flat().find(a => a.code === code);
            if (airport) document.getElementById('toSearch').value = airport.name;
        });
    });

    // 乘客與艙等彈出層
    const paxBtn = document.getElementById('openPaxBtn');
    const paxCard = document.getElementById('paxPopover');
    const paxInputs = { adults: document.getElementById('adults'), children: document.getElementById('children'), infants: document.getElementById('infants') };
    const paxVals = { adults: document.getElementById('adults_val'), children: document.getElementById('children_val'), infants: document.getElementById('infants_val') };
    const cabinHidden = document.getElementById('cabin_class');
    const cabinSelect = document.getElementById('cabinSelect');
    function refreshPaxBtn() {
        paxBtn.innerHTML = `<i class="bi bi-people"></i> ${paxInputs.adults.value} 成人${Number(paxInputs.children.value)>0?` · ${paxInputs.children.value} 兒童`:''}${Number(paxInputs.infants.value)>0?` · ${paxInputs.infants.value} 嬰兒`:''} · ${cabinHidden.value==='ECONOMY'?'經濟艙':(cabinHidden.value==='PREMIUM_ECONOMY'?'豪華經濟艙':(cabinHidden.value==='BUSINESS'?'商務艙':'頭等艙'))}`;
    }
    paxBtn.addEventListener('click', () => { paxCard.style.display = paxCard.style.display==='none'?'block':'none'; });
    document.addEventListener('click', (e) => { if (!paxCard.contains(e.target) && !paxBtn.contains(e.target)) paxCard.style.display='none'; });
    document.querySelectorAll('[data-counter]')
        .forEach(btn=>btn.addEventListener('click',()=>{ const key=btn.dataset.counter; const d=Number(btn.dataset.delta); let v=Number(paxInputs[key].value)+d; if(key==='adults') v=Math.max(1,v); else v=Math.max(0,v); paxInputs[key].value=v; paxVals[key].textContent=v; refreshPaxBtn(); }));
    document.getElementById('applyPax').addEventListener('click', ()=>{ cabinHidden.value=cabinSelect.value; refreshPaxBtn(); paxCard.style.display='none'; });
    refreshPaxBtn();

    // 已移除月曆價格趨勢功能
    </script>
</body>
</html>
