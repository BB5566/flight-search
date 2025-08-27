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

        // 分別處理去程和回程的航班列表
        const flightLists = [outboundList, returnList].filter(Boolean);

        flightLists.forEach(listContainer => {
            const flights = Array.from(listContainer.getElementsByClassName('flight-card'));

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

                flight.style.display = show ? 'block' : 'none';
            });

            // 步驟 2: 排序 (僅排序可見的航班)
            const visibleFlights = flights.filter(f => f.style.display !== 'none');

            const keyOf = (el) => {
                if (sort.startsWith('price')) return parseFloat(el.dataset.price || '0');
                if (sort.startsWith('duration')) return parseInt(el.dataset.duration || '0', 10);
                return 0;
            };

            visibleFlights.sort((a, b) => {
                const aKey = keyOf(a);
                const bKey = keyOf(b);
                const isDesc = sort.endsWith('desc');
                return isDesc ? (bKey - aKey) : (aKey - bKey);
            });

            // 步驟 3: 將排序後的元素重新附加到 DOM 中
            visibleFlights.forEach(el => listContainer.appendChild(el));
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
                if (i < 10) el.classList.remove('d-none');
            });
            // 如果沒有更多隱藏項目，則移除按鈕
            if (list.querySelectorAll('.more-item.d-none').length === 0) {
                button.remove();
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