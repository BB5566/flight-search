document.addEventListener('DOMContentLoaded', () => {

    // --- 1. 元素參考 --- 
    const get = (id) => document.getElementById(id);
    const airlineFilter = get('airlineFilter');
    const sortMode = get('sortMode');
    const stopsFilter = get('stopsFilter');
    const outboundList = get('outboundList');
    const returnList = get('returnList');
    const moreOutboundBtn = get('moreOutbound');
    const moreReturnBtn = get('moreReturn');

    // --- 2. 核心篩選與排序函式 ---
    /**
     * 根據使用者在篩選器中的選擇，過濾和排序航班卡片。
     */
    function filterAndSortFlights() {
        const airline = airlineFilter?.value || '';
        const sort = sortMode?.value || 'price-asc';
        const stops = stopsFilter?.value || '';
        const isFiltering = airline !== '' || stops !== '';

        // 分別處理去程和回程的航班列表
        const listConfigs = [
            { container: outboundList, moreBtn: moreOutboundBtn },
            { container: returnList, moreBtn: moreReturnBtn }
        ].filter(cfg => cfg.container);

        listConfigs.forEach(({ container, moreBtn }) => {
            const flights = Array.from(container.getElementsByClassName('flight-card'));
            let visibleCount = 0;

            // 步驟 1: 篩選 (根據航空公司和轉機次數)
            flights.forEach(flight => {
                const fAirline = flight.dataset.airline;
                const fStops = parseInt(flight.dataset.stops || '0', 10);
                let show = true;

                if (airline && fAirline !== airline) {
                    show = false;
                }
                if (stops !== '' && fStops > parseInt(stops, 10)) {
                    show = false;
                }

                if (show) {
                    // 若處於主動篩選狀態，確保移除 d-none 讓符合條件的航班皆可見
                    if (isFiltering) {
                        flight.classList.remove('d-none');
                    }

                    // 如果未篩選且該卡片仍屬於分頁隱藏項目，保持隱藏
                    if (!isFiltering && flight.classList.contains('d-none')) {
                        flight.style.display = 'none';
                    } else {
                        flight.style.display = 'block';
                        visibleCount++;
                    }
                } else {
                    flight.style.display = 'none';
                }
            });

            // 步驟 2: 排序所有航班以保持 DOM 順序穩定
            const keyOf = (el) => {
                if (sort.startsWith('price')) return parseFloat(el.dataset.price || '0');
                if (sort.startsWith('duration')) return parseInt(el.dataset.duration || '0', 10);
                return 0;
            };

            flights.sort((a, b) => {
                const aKey = keyOf(a);
                const bKey = keyOf(b);
                const isDesc = sort.endsWith('desc');
                return isDesc ? (bKey - aKey) : (aKey - bKey);
            });

            // 步驟 3: 將排序後的元素重新附加到 DOM 中
            flights.forEach(el => container.appendChild(el));

            // 步驟 4: 處理查無篩選結果提示
            let noMatchEl = container.querySelector('.no-filter-match');
            if (visibleCount === 0 && flights.length > 0) {
                if (!noMatchEl) {
                    noMatchEl = document.createElement('div');
                    noMatchEl.className = 'no-filter-match';
                    noMatchEl.innerHTML = '<i class="bi bi-funnel me-2" aria-hidden="true"></i>查無符合篩選條件的航班，請嘗試放寬篩選條件。';
                    container.appendChild(noMatchEl);
                }
                noMatchEl.style.display = 'block';
            } else if (noMatchEl) {
                noMatchEl.style.display = 'none';
            }

            // 步驟 5: 控制「顯示更多」按鈕顯示狀態
            if (moreBtn) {
                if (isFiltering) {
                    moreBtn.style.display = 'none';
                } else {
                    const hasHidden = container.querySelectorAll('.more-item.d-none').length > 0;
                    moreBtn.style.display = hasHidden ? 'inline-block' : 'none';
                }
            }
        });
    }

    // --- 3. "顯示更多" 按鈕功能 ---
    /**
     * 設定 "顯示更多" 按鈕的事件監聽器。
     * @param {HTMLElement} button - "顯示更多" 按鈕元素。
     * @param {HTMLElement} list - 對應的航班列表容器。
     */
    function setupShowMore(button, list) {
        if (!button || !list) return;

        button.addEventListener('click', () => {
            // 顯示接下來的 10 個隱藏項目
            const hiddenItems = list.querySelectorAll('.more-item.d-none');
            hiddenItems.forEach((el, i) => {
                if (i < 10) {
                    el.classList.remove('d-none');
                    el.style.display = 'block';
                }
            });
            // 如果沒有更多隱藏項目，則隱藏按鈕
            if (list.querySelectorAll('.more-item.d-none').length === 0) {
                button.style.display = 'none';
            }
        });
    }

    setupShowMore(moreOutboundBtn, outboundList);
    setupShowMore(moreReturnBtn, returnList);

    // --- 4. 篩選器事件監聽器 ---
    // 當任何篩選器變更時，重新執行過濾和排序
    airlineFilter?.addEventListener('change', filterAndSortFlights);
    sortMode?.addEventListener('change', filterAndSortFlights);
    stopsFilter?.addEventListener('change', filterAndSortFlights);
});