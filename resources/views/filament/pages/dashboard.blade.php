<div class="adm-wrap">
    <!-- 1. Top Welcome Greeting Banner -->
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Club 61 Padel Court &bull; Live Operations</span>
            </div>
            <div class="adm-banner-title">
                Selamat datang di Club 61 Padel Court Dashboard
            </div>
            <div class="adm-banner-sub">
                Pantau kinerja lapangan dan kelola operasional bisnis Club 61 Medan dengan mudah.
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="background: rgba(255, 255, 255, 0.85); border: 1.5px solid #DFC387; border-radius: 14px; padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: #10B981;"></div>
                <div>
                    <div style="font-size: 0.625rem; font-weight: 800; text-transform: uppercase; color: #8C6418;">Venue Status</div>
                    <div style="font-size: 0.75rem; font-weight: 800; color: #1F170D;">4 Lapangan Beroperasi Penuh</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. 4 Metric Overview Cards Grid -->
    <div class="adm-metrics-grid">
        
        <!-- Card 1: Total Revenue -->
        <div class="adm-metric-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="adm-metric-label">Total Revenue</div>
                    <div class="adm-metric-val">Rp 50.272.597</div>
                </div>
                <span class="adm-pill adm-pill-green">↗ +32.6%</span>
            </div>
            <div class="adm-metric-foot">
                <span>Trending up this month</span>
                <span style="color: #A68F63;">Last 6 months</span>
            </div>
        </div>

        <!-- Card 2: Total Sales -->
        <div class="adm-metric-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="adm-metric-label">Total Sales</div>
                    <div class="adm-metric-val">132 Bookings</div>
                </div>
                <span class="adm-pill adm-pill-gold">↗ +19.6%</span>
            </div>
            <div class="adm-metric-foot">
                <span>Steady performance increase</span>
                <span style="color: #A68F63;">Weekly targets</span>
            </div>
        </div>

        <!-- Card 3: New Customers -->
        <div class="adm-metric-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="adm-metric-label">New Customers</div>
                    <div class="adm-metric-val">48 Member</div>
                </div>
                <span class="adm-pill adm-pill-gold">↗ +26.3%</span>
            </div>
            <div class="adm-metric-foot">
                <span>Acquisition on target</span>
                <span style="color: #A68F63;">This period</span>
            </div>
        </div>

        <!-- Card 4: Active Accounts -->
        <div class="adm-metric-card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div class="adm-metric-label">Active Accounts</div>
                    <div class="adm-metric-val" style="color: #B8860B;">4 Lapangan</div>
                </div>
                <span class="adm-pill adm-pill-green">100% Okupansi</span>
            </div>
            <div class="adm-metric-foot">
                <span>Strong user retention</span>
                <span style="color: #A68F63;">Peak hours</span>
            </div>
        </div>

    </div>

    <!-- 3. Daily Transactions Chart (Smooth Wave with Rich Gold Gradient Fill) -->
    <div class="adm-card">
        
        <!-- Chart Header & Filters -->
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">Daily Transactions</div>
                <div class="adm-card-sub">
                    <strong style="color: #1F170D;">132 transactions</strong> &bull; <span style="color: #8C6418; font-weight: 700;">Rp 50.272.597 total revenue</span>
                </div>
            </div>

            <!-- Time Filter Tabs (Matching Illustration) -->
            <div class="adm-tabs">
                <button type="button" class="adm-tab-btn">Last 3 months</button>
                <button type="button" class="adm-tab-btn">Last 30 days</button>
                <button type="button" class="adm-tab-btn active">Last 7 days</button>
            </div>
        </div>

        <!-- SVG Smooth Bezier Wave Chart -->
        <div style="margin-top: 1.5rem; position: relative; width: 100%; height: 230px;">
            <svg style="width: 100%; height: 100%; overflow: visible;" viewBox="0 0 1000 220" preserveAspectRatio="none">
                <defs>
                    <!-- Gold Shimmer Area Gradient -->
                    <linearGradient id="goldAreaGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#D4AF37" stop-opacity="0.35" />
                        <stop offset="70%" stop-color="#EED59B" stop-opacity="0.10" />
                        <stop offset="100%" stop-color="#FAF2DE" stop-opacity="0.0" />
                    </linearGradient>

                    <!-- Stroke Linear Gradient -->
                    <linearGradient id="goldLineGradient" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#B8860B" />
                        <stop offset="50%" stop-color="#D4AF37" />
                        <stop offset="100%" stop-color="#8C6418" />
                    </linearGradient>
                </defs>

                <!-- Horizontal Reference Grid Lines -->
                <line x1="40" y1="20" x2="980" y2="20" stroke="#EEDBB0" stroke-width="1" stroke-dasharray="4 4" />
                <line x1="40" y1="65" x2="980" y2="65" stroke="#EEDBB0" stroke-width="1" stroke-dasharray="4 4" />
                <line x1="40" y1="110" x2="980" y2="110" stroke="#EEDBB0" stroke-width="1" stroke-dasharray="4 4" />
                <line x1="40" y1="155" x2="980" y2="155" stroke="#EEDBB0" stroke-width="1" stroke-dasharray="4 4" />

                <!-- Y-Axis Labels -->
                <text x="15" y="24" font-size="10" fill="#9E8555" font-family="monospace">25</text>
                <text x="15" y="69" font-size="10" fill="#9E8555" font-family="monospace">20</text>
                <text x="15" y="114" font-size="10" fill="#9E8555" font-family="monospace">15</text>
                <text x="15" y="159" font-size="10" fill="#9E8555" font-family="monospace">10</text>
                <text x="22" y="195" font-size="10" fill="#9E8555" font-family="monospace">0</text>

                <!-- Area Gradient Fill -->
                <path d="M 60,120 
                         C 180,115 240,155 340,145 
                         C 440,135 520,145 600,140 
                         C 680,135 760,90 840,85 
                         C 920,80 960,100 980,105 
                         L 980, 195 
                         L 60, 195 Z" 
                      fill="url(#goldAreaGradient)" />

                <!-- Smooth Wave Curve Stroke -->
                <path d="M 60,120 
                         C 180,115 240,155 340,145 
                         C 440,135 520,145 600,140 
                         C 680,135 760,90 840,85 
                         C 920,80 960,100 980,105" 
                      fill="none" 
                      stroke="url(#goldLineGradient)" 
                      stroke-width="3" 
                      stroke-linecap="round" />

                <!-- Data Marker Dots -->
                <circle cx="60" cy="120" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
                <circle cx="200" cy="125" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
                <circle cx="340" cy="145" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
                <circle cx="480" cy="138" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
                <circle cx="620" cy="135" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
                <circle cx="760" cy="95" r="4" fill="#FFFFFF" stroke="#D4AF37" stroke-width="2.5" />
                <circle cx="840" cy="85" r="5" fill="#D4AF37" stroke="#FFFFFF" stroke-width="2.5" />
                <circle cx="980" cy="105" r="4" fill="#FFFFFF" stroke="#B8860B" stroke-width="2.5" />
            </svg>

            <!-- X-Axis Dates Labels (Matching Illustration) -->
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 2rem 0; font-size: 0.6875rem; font-family: var(--font-mono); font-weight: 700; color: #8C754E;">
                <span>Mei 12</span>
                <span>Mei 14</span>
                <span>Mei 15</span>
                <span>Mei 16</span>
                <span>Mei 17</span>
                <span>Mei 18</span>
                <span>Mei 19</span>
            </div>
        </div>
    </div>

    <!-- 4. Live Bookings Table Card (Matching Illustration) -->
    <div class="adm-card">
        
        <!-- Table Search & Filter Bar -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
            <div style="position: relative; width: 100%; max-width: 280px;">
                <div style="position: absolute; top: 0; bottom: 0; left: 0.75rem; display: flex; align-items: center; pointer-events: none; color: #8C6418;">
                    <svg class="adm-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" 
                       placeholder="Search booking ID, customer..." 
                       class="adm-search-input" />
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="adm-btn-sec">
                    <svg class="adm-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>
                <button type="button" class="adm-btn-sec">
                    <svg class="adm-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    <span>View</span>
                </button>
            </div>
        </div>

        <!-- Table Responsive Container -->
        <div class="adm-table-wrap">
            <table class="adm-table">
                <!-- Table Header -->
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Courts / Coaches</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Total Price</th>
                        <th style="text-align: right;">Payment</th>
                    </tr>
                </thead>

                <!-- Table Body with Rows from Illustration -->
                <tbody>
                    
                    <!-- Row 1 -->
                    <tr>
                        <td style="font-family: var(--font-mono); font-weight: 700; color: #8C6418;">
                            cmp65uyuj004qs442ajacobz0
                        </td>
                        <td>
                            <div style="font-weight: 800; color: #1F170D;">PXDL</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">+6281232420034</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">Lapangan 6</div>
                            <div style="font-size: 0.625rem; color: #8C6418;">19:00 - 20:00, 20:00 - 21:00</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">19 May 2026</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">19:00 - 21:00</div>
                        </td>
                        <td>
                            <span class="adm-pill adm-pill-gold">Upcoming</span>
                            <div style="font-size: 0.5625rem; color: #A68F63; margin-top: 2px;">Mulai 2 jam yang lalu</div>
                        </td>
                        <td style="font-family: var(--font-mono); font-weight: 800; color: #1F170D;">
                            Rp 600.000
                        </td>
                        <td style="text-align: right;">
                            <span class="adm-pill adm-pill-green">Paid</span>
                        </td>
                    </tr>

                    <!-- Row 2 -->
                    <tr>
                        <td style="font-family: var(--font-mono); font-weight: 700; color: #8C6418;">
                            cmp09th2002ks442851zfc3
                        </td>
                        <td>
                            <div style="font-weight: 800; color: #1F170D;">PXDL</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">+6281232420034</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">Lapangan 5</div>
                            <div style="font-size: 0.625rem; color: #8C6418;">19:00 - 20:00, 20:00 - 21:00</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">19 May 2026</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">19:00 - 21:00</div>
                        </td>
                        <td>
                            <span class="adm-pill adm-pill-gold">Upcoming</span>
                            <div style="font-size: 0.5625rem; color: #A68F63; margin-top: 2px;">Mulai 2 jam yang lalu</div>
                        </td>
                        <td style="font-family: var(--font-mono); font-weight: 800; color: #1F170D;">
                            Rp 600.000
                        </td>
                        <td style="text-align: right;">
                            <span class="adm-pill adm-pill-green">Paid</span>
                        </td>
                    </tr>

                    <!-- Row 3 -->
                    <tr>
                        <td style="font-family: var(--font-mono); font-weight: 700; color: #8C6418;">
                            cmp70sumz0272s431wexvd5p
                        </td>
                        <td>
                            <div style="font-weight: 800; color: #1F170D;">PXDL</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">+6281232420034</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">Lapangan 2 &bull; Lapangan 3</div>
                            <div style="font-size: 0.625rem; color: #8C6418;">Double Booking Arena</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">19 May 2026</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">19:00 - 21:00</div>
                        </td>
                        <td>
                            <span class="adm-pill adm-pill-gold">Upcoming</span>
                            <div style="font-size: 0.5625rem; color: #A68F63; margin-top: 2px;">Mulai 2 jam yang lalu</div>
                        </td>
                        <td style="font-family: var(--font-mono); font-weight: 800; color: #1F170D;">
                            Rp 1.200.000
                        </td>
                        <td style="text-align: right;">
                            <span class="adm-pill adm-pill-green">Paid</span>
                        </td>
                    </tr>

                    <!-- Row 4 -->
                    <tr>
                        <td style="font-family: var(--font-mono); font-weight: 700; color: #8C6418;">
                            cmcctyajm02clds44dafwr3euq3
                        </td>
                        <td>
                            <div style="font-weight: 800; color: #1F170D;">JIM BROWN / ORCC</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">+628998854160</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">Lapangan 1 (Panoramic Pro)</div>
                            <div style="font-size: 0.625rem; color: #8C6418;">19:00 - 20:00, 20:00 - 21:00</div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #1F170D;">19 May 2026</div>
                            <div style="font-size: 0.625rem; color: #8C7A58;">19:00 - 21:00</div>
                        </td>
                        <td>
                            <span class="adm-pill adm-pill-gold">Upcoming</span>
                            <div style="font-size: 0.5625rem; color: #A68F63; margin-top: 2px;">Mulai 2 jam yang lalu</div>
                        </td>
                        <td style="font-family: var(--font-mono); font-weight: 800; color: #1F170D;">
                            Rp 0 <span style="font-size: 0.625rem; font-weight: 500; color: #8C7A58;">(VIP Benefit)</span>
                        </td>
                        <td style="text-align: right;">
                            <span class="adm-pill adm-pill-green">Paid</span>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
</div>
