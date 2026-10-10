
# Flight Search Engine - 機票搜尋引擎

A simple yet powerful flight search engine web application built with PHP and vanilla JavaScript. It connects to the Amadeus API to fetch real-time flight data, featuring a clean, modern, and responsive user interface.

一個使用 PHP 和原生 JavaScript 打造，簡潔而強大的機票搜尋引擎。此應用程式串接 Amadeus API 來獲取即時航班資料，並擁有一個乾淨、現代化且響應式的操作介面。

---

## ✨ Features / 主要功能

- **One-way & Round-trip Search:** Easily switch between one-way and round-trip searches.
- **Passenger Selection:** Select the number of adults, children, and infants.
- **Cabin Class:** Choose from Economy, Premium Economy, Business, or First Class.
- **Currency Selection:** View prices in multiple currencies (TWD, USD, JPY, etc.).
- **Direct Flight Filter:** Option to show only non-stop flights.
- **Modern UI/UX:** A clean, professional, and responsive design that works on all devices.
- **Client-side Filtering & Sorting:** Instantly filter and sort results by price, duration, and airline on the results page.

---

- **單程與來回搜尋：** 可輕鬆切換單程或來回模式。
- **乘客選擇：** 可選擇成人、兒童和嬰兒的數量。
- **艙等選擇：** 可選擇經濟艙、豪華經濟艙、商務艙或頭等艙。
- **幣別選擇：** 可用多種貨幣查看票價 (TWD, USD, JPY 等)。
- **直飛篩選：** 可篩選只顯示直飛航班。
- **現代化介面：** 乾淨、專業且響應式的設計，在各種裝置上都能良好運作。
- **前端結果篩選與排序：** 在結果頁上，可即時按價格、飛行時間和航空公司進行篩選與排序。

---

## 🛠️ Tech Stack / 技術棧

- **Backend:** PHP 7.4+
- **Frontend:** HTML5, CSS3 (Grid & Flexbox), Vanilla JavaScript (ES6+)
- **API:** Amadeus Self-Service Flight Offers Search API

---

## 🚀 Setup and Installation / 安裝與設定

To get this project running on your local machine, follow these steps:

若要在您的本機上運行此專案，請遵循以下步驟：

**1. Clone the Repository / 複製專案**
```bash
git clone <repository-url>
cd <repository-folder>
```

**2. Set Up Environment File / 設定環境變數檔案**

You need to get your own API credentials from the [Amadeus for Developers](https://developers.amadeus.com/) portal.

您需要先從 [Amadeus for Developers](https://developers.amadeus.com/) 網站獲取您自己的 API 金鑰。

- Copy `.env.example` to `.env` in the project root.
- 在專案根目錄將 `.env.example` 複製為 `.env`。

```bash
cp .env.example .env
```

- Update `.env` with your own API keys:
- 將 `.env` 內的預設值改成您自己的 API 金鑰：

```env
AMADEUS_CLIENT_ID="YOUR_AMADEUS_API_KEY"
AMADEUS_CLIENT_SECRET="YOUR_AMADEUS_API_SECRET"
AMADEUS_ENV="test"
```

`.env` is already ignored by `.gitignore`, so your keys will not be committed.
`.env` 已預設加入 `.gitignore`，不會被提交到版本庫。

**3. Run the Application / 運行應用程式**

Use a local server environment like XAMPP, MAMP, or PHP's built-in server to run the project.

使用像 XAMPP、MAMP 或 PHP 內建的伺服器來運行此專案。

For PHP's built-in server, run this command in the project root:
若使用 PHP 內建伺服器，請在專案根目錄下執行此命令：

```bash
php -S localhost:8000
```

Then, open your web browser and navigate to `http://localhost:8000`.
然後，打開您的網頁瀏覽器並訪問 `http://localhost:8000`。

---

## 📂 File Structure / 檔案結構

The project is structured to separate concerns, making it easier to maintain and scale.

專案結構遵循關注點分離原則，使其更易於維護和擴展。

```
d:\FLIGHT-SEARCH
├── css/                # CSS 樣式檔案
│   ├── style.css       # 首頁 (index.php) 的樣式
│   └── results.css     # 結果頁 (result.php) 的樣式
├── data/               # 靜態資料檔案
│   ├── airports.json   # 機場列表 (用於下拉選單)
│   └── airlines.php    # 航空公司名稱對應表
├── js/                 # JavaScript 檔案
│   ├── main.js         # 首頁的腳本 (表單互動)
│   └── results.js      # 結果頁的腳本 (篩選與排序)
├── templates/          # 可重複使用的 PHP 模板
│   └── flight_card.php # 單個航班資訊卡片的模板
├── .env.example        # 環境變數範例檔案
├── .gitignore          # Git 忽略設定
├── amadeus_api.php     # Amadeus API 串接的核心類別
├── env.php             # 讀取 .env 環境變數的輔助腳本
├── index.php           # 首頁 (搜尋表單)
├── result.php          # 搜尋結果頁
├── README.md           # 本檔案
└── utils.php           # 通用的輔助函式 (如格式化日期、貨幣等)
```
