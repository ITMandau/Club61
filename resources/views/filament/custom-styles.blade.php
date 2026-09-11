<style>
    /* Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap');

    :root {
        --font-serif: 'Cinzel', serif;
        --font-sans: 'Plus Jakarta Sans', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
    }

    /* Global Body & Layout Background: Calacatta White Gold Marble */
    body, 
    .fi-layout,
    .fi-main,
    .fi-simple-layout {
        background-color: #F8F5EE !important;
        background-image: url('{{ asset("images/white-gold-marble.jpg") }}') !important;
        background-size: cover !important;
        background-position: center !important;
        background-attachment: fixed !important;
        background-repeat: no-repeat !important;
        font-family: var(--font-sans) !important;
        color: #1F170D !important;
    }

    /* Sidebar: Frosted White Glass with Polished Gold Right Border */
    .fi-sidebar {
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(20px) !important;
        -webkit-backdrop-filter: blur(20px) !important;
        border-right: 1.5px solid #D4AF37 !important;
        box-shadow: 4px 0 25px rgba(180, 130, 20, 0.08) !important;
    }

    .fi-sidebar-header {
        border-bottom: 1.5px solid rgba(212, 175, 55, 0.35) !important;
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }

    .fi-logo {
        font-family: var(--font-serif) !important;
        font-weight: 900 !important;
        letter-spacing: 0.08em !important;
        color: #1F170D !important;
        font-size: 0.95rem !important;
    }

    /* Sidebar Navigation Items */
    .fi-sidebar-item-label {
        font-size: 0.8125rem !important;
        font-weight: 600 !important;
        color: #4A3A22 !important;
    }

    .fi-sidebar-item-button:hover {
        background: rgba(250, 242, 222, 0.7) !important;
        color: #1F170D !important;
        border-radius: 12px !important;
    }

    /* Active Sidebar Item: Polished Gold Pill */
    .fi-sidebar-item-active .fi-sidebar-item-button {
        background: linear-gradient(135deg, #FAF2DE 0%, #F5E5BE 100%) !important;
        border: 1.5px solid #D9BE84 !important;
        box-shadow: 0 4px 12px rgba(180, 130, 20, 0.15) !important;
        border-radius: 14px !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-label {
        color: #5C410F !important;
        font-weight: 800 !important;
    }

    .fi-sidebar-item-active .fi-sidebar-item-icon {
        color: #8C6418 !important;
    }

    .fi-sidebar-group-label {
        font-size: 0.6875rem !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.15em !important;
        color: #8C754E !important;
        margin-top: 0.75rem !important;
    }

    /* Topbar: Frosted Glass */
    .fi-topbar {
        background: rgba(255, 255, 255, 0.9) !important;
        backdrop-filter: blur(16px) !important;
        -webkit-backdrop-filter: blur(16px) !important;
        border-bottom: 1.5px solid rgba(212, 175, 55, 0.4) !important;
    }

    /* Hide default filament page header so custom banner takes its place seamlessly */
    .fi-page-header {
        display: none !important;
    }

    /* ========================================================
       CUSTOM DASHBOARD SCOPED CSS (Bulletproof against Tailwind uncompiled rules)
       ======================================================== */
    .adm-wrap {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding-bottom: 3rem;
        font-family: var(--font-sans);
    }

    .adm-banner {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.96) 0%, rgba(253, 249, 240, 0.94) 100%);
        border: 1.5px solid #D4AF37;
        border-radius: 20px;
        box-shadow: 0 12px 30px -10px rgba(160, 120, 30, 0.15);
        backdrop-filter: blur(16px);
        padding: 1.5rem 1.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .adm-banner-title {
        font-family: var(--font-serif);
        font-weight: 800;
        font-size: 1.5rem;
        color: #1F170D;
        line-height: 1.2;
    }

    .adm-banner-sub {
        font-size: 0.8125rem;
        color: #7A643E;
        margin-top: 0.35rem;
        font-weight: 500;
    }

    .adm-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .adm-pill-gold {
        background: #FAF2DE;
        border: 1px solid #D9BE84;
        color: #7A5818;
    }

    .adm-pill-green {
        background: #EAF7EC;
        border: 1px solid #85D497;
        color: #1E7E34;
    }

    /* 4 Metrics Grid */
    .adm-metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
    }

    @media (max-width: 1024px) {
        .adm-metrics-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .adm-metrics-grid {
            grid-template-columns: 1fr;
        }
    }

    .adm-metric-card {
        background: rgba(255, 255, 255, 0.94);
        border: 1.5px solid #DFC387;
        border-radius: 16px;
        padding: 1.25rem;
        box-shadow: 0 8px 24px -8px rgba(160, 120, 30, 0.12);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease;
    }

    .adm-metric-card:hover {
        transform: translateY(-2px);
    }

    .adm-metric-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: #7A5818;
    }

    .adm-metric-val {
        font-family: var(--font-serif);
        font-size: 1.625rem;
        font-weight: 900;
        color: #1F170D;
        margin-top: 0.35rem;
        line-height: 1.1;
    }

    .adm-metric-foot {
        font-size: 0.6875rem;
        color: #8C7A58;
        margin-top: 0.75rem;
        padding-top: 0.5rem;
        border-top: 1px solid rgba(223, 195, 135, 0.4);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Chart & Table Cards */
    .adm-card {
        background: rgba(255, 255, 255, 0.95);
        border: 1.5px solid #D4AF37;
        border-radius: 20px;
        box-shadow: 0 12px 30px -10px rgba(160, 120, 30, 0.14);
        padding: 1.5rem;
    }

    .adm-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(223, 195, 135, 0.5);
    }

    .adm-card-title {
        font-family: var(--font-serif);
        font-weight: 800;
        font-size: 1.125rem;
        color: #1F170D;
    }

    .adm-card-sub {
        font-size: 0.75rem;
        color: #7A643E;
        font-weight: 500;
        margin-top: 0.15rem;
    }

    /* Tabs Filter */
    .adm-tabs {
        display: inline-flex;
        align-items: center;
        background: #FAF5E8;
        border: 1px solid #DFC387;
        border-radius: 12px;
        padding: 3px;
        gap: 3px;
    }

    .adm-tab-btn {
        padding: 0.35rem 0.75rem;
        font-size: 0.6875rem;
        font-weight: 700;
        border-radius: 9px;
        color: #6B5738;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .adm-tab-btn.active {
        background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
        color: #281A05;
        font-weight: 900;
        border: 1px solid #FBF0CE;
        box-shadow: 0 2px 8px rgba(184, 134, 11, 0.3);
    }

    /* Table */
    .adm-table-wrap {
        overflow-x: auto;
        border-radius: 14px;
        border: 1px solid rgba(223, 195, 135, 0.6);
        margin-top: 1rem;
    }

    .adm-table {
        width: 100%;
        text-align: left;
        font-size: 0.75rem;
        border-collapse: collapse;
    }

    .adm-table th {
        background: linear-gradient(90deg, #FBF6EB 0%, #EEDBB0 100%);
        color: #5C410F;
        text-transform: uppercase;
        font-size: 0.625rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        padding: 0.75rem 1rem;
        border-bottom: 1.5px solid #DFC387;
    }

    .adm-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(223, 195, 135, 0.35);
        color: #241A0B;
        vertical-align: middle;
        background: rgba(255, 255, 255, 0.85);
    }

    .adm-table tr:hover td {
        background: rgba(253, 248, 235, 0.95);
    }

    /* Strict SVG Constraints */
    .adm-svg-icon {
        width: 15px !important;
        height: 15px !important;
        max-width: 15px !important;
        max-height: 15px !important;
        flex-shrink: 0 !important;
        display: inline-block !important;
    }

    .adm-search-input {
        background: #FAF5E8;
        border: 1.5px solid #DFC387;
        border-radius: 10px;
        padding: 0.45rem 0.85rem 0.45rem 2.2rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #1C150B;
        outline: none;
        width: 100%;
        max-width: 280px;
    }

    .adm-btn-sec {
        background: rgba(255, 255, 255, 0.95);
        border: 1.5px solid #DFC387;
        border-radius: 10px;
        padding: 0.45rem 0.85rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #5C410F;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .adm-btn-sec:hover {
        background: #FAF2DE;
    }
</style>
