document.addEventListener('DOMContentLoaded', () => {

    // --- 1. 元素參考 --- 
    // 為了效能，我們一次性獲取所有需要的 DOM 元素並將它們儲存在常數中。
    const get = (id) => document.getElementById(id);
    const fromCode = get('fromCode');
    const toCode = get('toCode');
    const swapBtn = get('swapBtn');
    const departDate = get('departDate');
    const returnDate = get('returnDate');
    const returnDateContainer = get('return-date-container');
    const tripTypeToggle = get('tripTypeToggle');
    const searchForm = get('searchForm');
    const openPaxBtn = get('openPaxBtn');
    const paxPopover = get('paxPopover');

    // --- 2. 機場下拉選單填充 --- 
    /**
     * 從 JSON 檔案中獲取機場資料並填充下拉選單。
     * @param {object} airportData - 按地區分組的機場資料。
     */
    const populateAirportDropdowns = (airportData) => {
        const selects = [fromCode, toCode];
        selects.forEach(select => {
            select.innerHTML = ''; // 清除現有選項
            // 為每個地區 (例如, '台灣', '日本') 建立一個 <optgroup>
            for (const [group, airports] of Object.entries(airportData)) {
                const optgroup = document.createElement('optgroup');
                optgroup.label = group;
                airports.forEach(airport => {
                    const option = document.createElement('option');
                    option.value = airport.code;
                    option.textContent = `${airport.name} (${airport.code})`;
                    optgroup.appendChild(option);
                });
                select.appendChild(optgroup);
            }
        });
        // 設定預設選項以提供更好的使用者體驗。
        fromCode.value = 'TPE';
        toCode.value = 'NRT';
    };

    // 非同步獲取機場列表。
    fetch('data/airports.json')
        .then(response => response.json())
        .then(populateAirportDropdowns)
        .catch(error => console.error('讀取機場資料時發生錯誤:', error));

    // --- 3. 事件監聽器 ---

    // 交換出發地和目的地機場。
    swapBtn.addEventListener('click', () => {
        const fromValue = fromCode.value;
        fromCode.value = toCode.value;
        toCode.value = fromValue;
    });

    /**
     * 處理單程/來回切換開關的邏輯。
     * 顯示或隱藏回程日期欄位。
     */
    const handleTripTypeChange = () => {
        if (tripTypeToggle.checked) {
            // 如果是來回，顯示回程日期並將其設為必填。
            returnDateContainer.classList.remove('hidden');
            returnDate.required = true;
        } else {
            // 如果是單程，隱藏它，移除必填屬性，並清除其值。
            returnDateContainer.classList.add('hidden');
            returnDate.required = false;
            returnDate.value = '';
        }
    };
    tripTypeToggle.addEventListener('change', handleTripTypeChange);
    handleTripTypeChange(); // 頁面載入時呼叫一次以設定初始狀態。

    // --- 4. 乘客與艙等彈出視窗邏輯 ---
    const paxInputs = { adults: get('adults'), children: get('children'), infants: get('infants') };
    const paxVals = { adults: get('adults_val'), children: get('children_val'), infants: get('infants_val') };
    const cabinSelect = get('cabinSelect');
    const cabinHidden = get('cabin_class');

    const updateCounterButtons = () => {
        const adults = Number(paxInputs.adults.value);
        const children = Number(paxInputs.children.value);
        const infants = Number(paxInputs.infants.value);

        document.querySelectorAll('[data-counter]').forEach(btn => {
            const key = btn.dataset.counter;
            const delta = Number(btn.dataset.delta);
            if (key === 'adults') {
                btn.disabled = (delta === -1 && adults <= 1) || (delta === 1 && adults >= 9);
            } else if (key === 'children') {
                btn.disabled = (delta === -1 && children <= 0) || (delta === 1 && children >= 9);
            } else if (key === 'infants') {
                btn.disabled = (delta === -1 && infants <= 0) || (delta === 1 && (infants >= adults || infants >= 9));
            }
        });
    };

    /**
     * 根據選擇更新主要的乘客按鈕文字。
     */
    const refreshPaxBtn = () => {
        const adults = paxInputs.adults.value;
        const children = paxInputs.children.value;
        const infants = paxInputs.infants.value;
        const cabin = cabinSelect.options[cabinSelect.selectedIndex].text;
        let totalTravelers = Number(adults) + Number(children);
        
        let buttonText = `<i class="bi bi-people" aria-hidden="true"></i> ${totalTravelers} 位旅客, ${cabin}`;
        // 如果有嬰兒，選擇性地加上嬰兒數量。
        if (Number(infants) > 0) {
             buttonText += ` (+${infants} 嬰兒)`;
        }
        openPaxBtn.innerHTML = buttonText;
        updateCounterButtons();
    };

    const togglePaxPopover = (open) => {
        const isOpen = open !== undefined ? open : paxPopover.style.display !== 'block';
        paxPopover.style.display = isOpen ? 'block' : 'none';
        openPaxBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    // 顯示/隱藏彈出視窗。
    openPaxBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePaxPopover();
    });
    
    // 處理乘客數量的 + 和 - 按鈕。
    document.querySelectorAll('[data-counter]').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.counter;
            const delta = Number(btn.dataset.delta);
            let value = Number(paxInputs[key].value) + delta;
            
            if (key === 'adults') {
                value = Math.min(9, Math.max(1, value));
                // 嬰兒不能多於成人
                if (Number(paxInputs.infants.value) > value) {
                    paxInputs.infants.value = value;
                    paxVals.infants.textContent = value;
                }
            } else if (key === 'children') {
                value = Math.min(9, Math.max(0, value));
            } else if (key === 'infants') {
                const maxInfants = Math.min(9, Number(paxInputs.adults.value));
                value = Math.min(maxInfants, Math.max(0, value));
            }
            
            paxInputs[key].value = value;
            paxVals[key].textContent = value;
            refreshPaxBtn();
        });
    });
    
    // 當艙等選擇變更時，更新隱藏的艙等輸入欄位。
    cabinSelect.addEventListener('change', () => {
        cabinHidden.value = cabinSelect.value;
        refreshPaxBtn();
    });

    // --- 5. 全域點擊與鍵盤 Esc 關閉彈出視窗 ---
    document.addEventListener('click', (e) => {
        if (!paxPopover.contains(e.target) && e.target !== openPaxBtn) {
            togglePaxPopover(false);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && paxPopover.style.display === 'block') {
            togglePaxPopover(false);
            openPaxBtn.focus();
        }
    });

    // --- 6. 表單驗證與提交 ---
    searchForm.addEventListener('submit', (e) => {
        // 對於出發地/目的地的自訂驗證。
        if (fromCode.value === toCode.value) {
            alert('出發地與目的地不能相同');
            e.preventDefault();
            return;
        }

        // 使用內建的 HTML5 驗證來處理必填欄位，如日期。
        if (!searchForm.checkValidity()) {
            e.preventDefault();
            searchForm.reportValidity();
            return;
        }

        // 如果驗證通過，在按鈕上顯示載入狀態。
        const submitBtn = get('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 正在搜尋...';
    });

    // --- 7. 初始設定 ---
    // 使用本地時間而非 UTC 計算今日日期，避免時區偏差
    const formatLocalDate = (d) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const todayStr = formatLocalDate(new Date());
    departDate.min = todayStr;
    returnDate.min = todayStr;

    // 當出發日期變更時，自動聯動回程日期的最小值
    departDate.addEventListener('change', () => {
        if (departDate.value) {
            returnDate.min = departDate.value;
            if (returnDate.value && returnDate.value < departDate.value) {
                returnDate.value = departDate.value;
            }
        } else {
            returnDate.min = todayStr;
        }
    });

    updateCounterButtons();
});