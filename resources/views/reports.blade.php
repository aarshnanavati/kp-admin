@extends('layouts.app')

@section('title', 'Kitchen Prep & Business Reports')
@section('current_page', 'reports')
@section('page_title', 'Kitchen Prep & Reports')
@section('page_subtitle', 'Daily & weekly item preparation estimates for kitchen operations and business exports.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="reportsPage">
  <!-- Top Action Bar -->
  <div class="kp_kitchen_admin_panel_section_toolbar prep-top-toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title" style="margin-bottom: 0.25rem;">Kitchen Preparation &amp; Food Item Estimates</h2>
      <p class="kp_kitchen_admin_panel_section_text" style="margin: 0;">
        Showing food items to prepare for: <strong id="activeRangeLabel" class="prep-highlight-text">{{ $kitchenPrep['filters']['date_range_label'] }}</strong>
      </p>
    </div>
    <div class="prep-top-actions">
      <button type="button" onclick="window.printKitchenPrepSheet()" class="kp_kitchen_admin_panel_secondary_button prep-btn-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        Print Kitchen Prep Sheet
      </button>
      <a id="exportPrepCsvBtn" href="{{ url('/api/reports/export?type=kitchen_prep&filter_type=' . $kitchenPrep['filters']['filter_type'] . '&selected_date=' . $kitchenPrep['filters']['selected_date'] . '&selected_month=' . $kitchenPrep['filters']['selected_month'] . '&selected_week=' . $kitchenPrep['filters']['selected_week'] . '&category=' . ($kitchenPrep['filters']['category'] ?? 'all') . '&search=' . urlencode($kitchenPrep['filters']['search'] ?? '')) }}" class="kp_kitchen_admin_panel_secondary_button prep-btn-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        Export Prep CSV
      </a>
      <a id="exportPrepPdfBtn" href="{{ route('reports.export-pdf', ['type' => 'kitchen_prep', 'filter_type' => $kitchenPrep['filters']['filter_type'], 'selected_date' => $kitchenPrep['filters']['selected_date'], 'selected_month' => $kitchenPrep['filters']['selected_month'], 'selected_week' => $kitchenPrep['filters']['selected_week'], 'category' => $kitchenPrep['filters']['category'] ?? 'all', 'search' => $kitchenPrep['filters']['search'] ?? '']) }}" target="_blank" class="kp_kitchen_admin_panel_primary_button prep-btn-pdf" style="background: linear-gradient(135deg, #e11d48, #be123c); border-color: #be123c; color: #fff;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        Download Prep PDF
      </a>
    </div>
  </div>

  <!-- Filter Controls Card -->
  <div class="kp_kitchen_admin_panel_card prep-filter-card">
    <form id="kitchenPrepFilterForm" method="GET" action="{{ route('reports') }}">
      <!-- Quick Filter Pills -->
      <div class="prep-pills-row">
        <span class="prep-pills-label">Quick Filters:</span>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'today' ? 'prep-pill-active' : '' }}" onclick="setFilterType('today')">Today</button>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'yesterday' ? 'prep-pill-active' : '' }}" onclick="setFilterType('yesterday')">Yesterday</button>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'this_week' ? 'prep-pill-active' : '' }}" onclick="setFilterType('this_week')">This Week</button>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'last_week' ? 'prep-pill-active' : '' }}" onclick="setFilterType('last_week')">Last Week</button>
        <button type="button" class="prep-pill-btn {{ in_array($kitchenPrep['filters']['filter_type'], ['month', 'this_month']) ? 'prep-pill-active' : '' }}" onclick="setFilterType('month')">Month &amp; Weeks</button>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'single_date' ? 'prep-pill-active' : '' }}" onclick="setFilterType('single_date')">Specific Date</button>
        <button type="button" class="prep-pill-btn {{ $kitchenPrep['filters']['filter_type'] === 'custom' ? 'prep-pill-active' : '' }}" onclick="setFilterType('custom')">Custom Range</button>
        <input type="hidden" name="filter_type" id="filterTypeInput" value="{{ $kitchenPrep['filters']['filter_type'] }}">
      </div>

      <!-- Extended Filter Parameters Row -->
      <div class="prep-filter-inputs-box">
        
        <!-- Month Selector -->
        <div id="monthFilterContainer" class="prep-control-group">
          <label class="prep-control-label" for="selectedMonthInput">Select Month</label>
          <select name="selected_month" id="selectedMonthInput" class="kp_kitchen_admin_panel_form_select prep-select" onchange="submitPrepFilter()">
            @foreach($kitchenPrep['available_months'] as $m)
              <option value="{{ $m['value'] }}" {{ $m['value'] === $kitchenPrep['filters']['selected_month'] ? 'selected' : '' }}>{{ $m['label'] }}</option>
            @endforeach
          </select>
        </div>

        <!-- Week of Month Selector (1..5) -->
        <div id="weekFilterContainer" class="prep-control-group">
          <label class="prep-control-label" for="selectedWeekInput">Week Selection (4-5 Weeks)</label>
          <select name="selected_week" id="selectedWeekInput" class="kp_kitchen_admin_panel_form_select prep-select" onchange="submitPrepFilter()">
            <option value="all" {{ $kitchenPrep['filters']['selected_week'] === 'all' ? 'selected' : '' }}>All Weeks (Full Month Breakdown)</option>
            @foreach($kitchenPrep['month_weeks'] as $w)
              <option value="{{ $w['week_number'] }}" {{ (string)$w['week_number'] === (string)$kitchenPrep['filters']['selected_week'] ? 'selected' : '' }}>
                {{ $w['label'] }} ({{ $w['date_range_label'] }})
              </option>
            @endforeach
          </select>
        </div>

        <!-- Single Date Picker -->
        <div id="singleDateContainer" class="prep-control-group" style="{{ $kitchenPrep['filters']['filter_type'] === 'single_date' ? '' : 'display:none;' }}">
          <label class="prep-control-label" for="selectedDateInput">Specific Date</label>
          <input type="date" name="selected_date" id="selectedDateInput" value="{{ $kitchenPrep['filters']['selected_date'] }}" class="kp_kitchen_admin_panel_form_input prep-input" onchange="submitPrepFilter()">
        </div>

        <!-- Custom Range Start Date -->
        <div id="customStartContainer" class="prep-control-group" style="{{ $kitchenPrep['filters']['filter_type'] === 'custom' ? '' : 'display:none;' }}">
          <label class="prep-control-label" for="startDateInput">Start Date</label>
          <input type="date" name="start_date" id="startDateInput" value="{{ $kitchenPrep['filters']['start_date'] }}" class="kp_kitchen_admin_panel_form_input prep-input">
        </div>

        <!-- Custom Range End Date -->
        <div id="customEndContainer" class="prep-control-group" style="{{ $kitchenPrep['filters']['filter_type'] === 'custom' ? '' : 'display:none;' }}">
          <label class="prep-control-label" for="endDateInput">End Date</label>
          <input type="date" name="end_date" id="endDateInput" value="{{ $kitchenPrep['filters']['end_date'] }}" class="kp_kitchen_admin_panel_form_input prep-input">
        </div>

        <!-- Filter Action Buttons -->
        <div class="prep-action-buttons">
          <button type="submit" class="kp_kitchen_admin_panel_primary_button prep-submit-btn">Apply</button>
          <a href="{{ route('reports', ['filter_type' => 'today']) }}" class="kp_kitchen_admin_panel_secondary_button prep-reset-btn">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <!-- Summary KPI Cards Grid (Dynamic from Categories) -->
  <div class="kp_kitchen_admin_panel_stats_grid prep-kpi-grid">
    @foreach($kitchenPrep['category_cards'] as $catCard)
      <article class="kp_kitchen_admin_panel_stat_card hover-card prep-kpi-card" style="border-left: 4px solid {{ $catCard['colors']['border'] }} !important;">
        <div class="prep-kpi-inner">
          <div>
            <span class="prep-kpi-label">{{ $catCard['icon'] }} {{ $catCard['name'] }}</span>
            <h3 class="prep-kpi-number" style="color: {{ $catCard['colors']['text'] }};">
              {{ number_format($catCard['total_qty']) }}
            </h3>
            <span class="prep-kpi-hint">{{ $catCard['hint'] }} ({{ $catCard['unit'] }})</span>
          </div>
          <div class="prep-kpi-icon-box" style="background: {{ $catCard['colors']['bg'] }}; color: {{ $catCard['colors']['text'] }}; font-size: 1.4rem; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px;">
            <span>{{ $catCard['icon'] }}</span>
          </div>
        </div>
      </article>
    @endforeach

    <!-- Total Tiffins / Orders Card -->
    <article class="kp_kitchen_admin_panel_stat_card hover-card prep-kpi-card prep-kpi-tiffins" style="border-left: 4px solid #8B5CF6 !important;">
      <div class="prep-kpi-inner">
        <div>
          <span class="prep-kpi-label">🍱 Tiffins &amp; Orders</span>
          <h3 id="kpiTiffinsCount" class="prep-kpi-number prep-tiffins-color" style="color: #7C3AED;">
            {{ number_format($kitchenPrep['summary']['total_tiffins']) }}
          </h3>
          <span class="prep-kpi-hint">Across {{ $kitchenPrep['summary']['total_orders'] }} orders</span>
        </div>
        <div class="prep-kpi-icon-box prep-tiffins-icon-bg" style="background: rgba(139, 92, 246, 0.12); color: #7C3AED; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 10px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
      </div>
    </article>
  </div>

  <!-- Month Week-by-Week Comparison Section (Visible when Month view is active) -->
  @if(in_array($kitchenPrep['filters']['filter_type'], ['month', 'this_month']))
  <div class="kp_kitchen_admin_panel_card prep-week-history-card">
    <div class="prep-week-history-header">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div class="prep-history-badge-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </div>
        <div>
          <h3 class="prep-week-history-title">
            {{ \Carbon\Carbon::parse($kitchenPrep['filters']['selected_month'] . '-01')->format('F Y') }} &mdash; 4 to 5 Weeks Order History Breakdown
          </h3>
          <p class="prep-week-history-subtitle">
            Compare order quantities across each category for every week of the month. Click any week card to isolate its prep sheet.
          </p>
        </div>
      </div>
      <div>
        @if($kitchenPrep['filters']['selected_week'] !== 'all')
          <span class="prep-active-week-badge">
            ✓ Filtered: Week {{ $kitchenPrep['filters']['selected_week'] }}
          </span>
        @else
          <span class="prep-active-allweeks-badge">
            Showing All {{ count($kitchenPrep['month_weeks']) }} Weeks Combined
          </span>
        @endif
      </div>
    </div>

    <div class="prep-weeks-grid">
      @foreach($kitchenPrep['month_weeks'] as $w)
        @php
          $isCardActive = ((string)$w['week_number'] === (string)$kitchenPrep['filters']['selected_week']);
        @endphp
        <div class="prep-week-card hover-card {{ $isCardActive ? 'prep-week-card-active' : '' }}" onclick="selectWeek('{{ $w['week_number'] }}')">
          <div class="prep-week-card-top">
            <div class="prep-week-card-title-wrap">
              <span class="prep-week-card-title">{{ $w['label'] }}</span>
              @if($isCardActive)
                <span class="prep-week-card-active-tag">Active</span>
              @endif
            </div>
            <span class="prep-week-orders-pill">{{ $w['total_orders'] }} {{ Str::plural('Order', $w['total_orders']) }}</span>
          </div>

          <div class="prep-week-date-range">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.7;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            {{ $w['date_range_label'] }}
          </div>

          <div class="prep-week-stats-container">
            @foreach($kitchenPrep['category_cards'] as $catCard)
              @php
                $weekCatQty = $w['categories'][$catCard['slug']] ?? 0;
              @endphp
              <div class="prep-week-stat-row">
                <span class="prep-week-stat-label">{{ $catCard['icon'] }} {{ $catCard['name'] }}:</span>
                <strong style="color: {{ $catCard['colors']['text'] }};">{{ number_format($weekCatQty) }}</strong>
              </div>
            @endforeach
            <div class="prep-week-stat-row" style="border-top: 1px dashed var(--panel-border, rgba(0,0,0,0.08)); padding-top: 0.3rem; margin-top: 0.2rem;">
              <span class="prep-week-stat-label">🍱 Tiffins:</span>
              <strong class="prep-week-stat-val-tiffins">{{ number_format($w['total_tiffins']) }}</strong>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  <!-- Kitchen Production List Card -->
  <div class="kp_kitchen_admin_panel_card prep-table-card">
    
    <!-- Table Toolbar: Category Tabs & Search Bar -->
    <div class="prep-table-toolbar">
      
      <!-- Category Filter Tabs (Dynamic) -->
      <div class="prep-cat-tabs-wrap" id="categoryFilterTabs">
        <button type="button" class="prep-cat-tab prep-cat-active" onclick="filterCategory('all', this)">All Food Items ({{ count($kitchenPrep['items']) }})</button>
        @foreach($kitchenPrep['category_cards'] as $catCard)
          <button type="button" class="prep-cat-tab" onclick="filterCategory('{{ $catCard['slug'] }}', this)">
            {{ $catCard['icon'] }} {{ $catCard['name'] }}
          </button>
        @endforeach
      </div>

      <!-- Live Search Box -->
      <div class="prep-search-wrap">
        <input type="text" id="prepSearchInput" class="kp_kitchen_admin_panel_form_input prep-search-input" placeholder="Search food item..." onkeyup="searchPrepTable(this.value)">
        <svg class="prep-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      </div>
    </div>

    <!-- Items Preparation Table -->
    <div class="prep-table-scroll">
      <table class="kp_kitchen_admin_panel_table prep-table" id="kitchenPrepTable">
        <thead>
          <tr class="prep-table-head-row">
            <th class="prep-th">#</th>
            <th class="prep-th">Food Item</th>
            <th class="prep-th">Category</th>
            <th class="prep-th">Total To Prepare</th>
            <th class="prep-th">Source Breakdown</th>
            @if(in_array($kitchenPrep['filters']['filter_type'], ['month', 'this_month']) && $kitchenPrep['filters']['selected_week'] === 'all')
              <th class="prep-th prep-th-center">W1</th>
              <th class="prep-th prep-th-center">W2</th>
              <th class="prep-th prep-th-center">W3</th>
              <th class="prep-th prep-th-center">W4</th>
              <th class="prep-th prep-th-center">W5</th>
            @endif
            <th class="prep-th">Orders</th>
            <th class="prep-th prep-th-center">Prep Status</th>
          </tr>
        </thead>
        <tbody id="kitchenPrepTableBody">
          @forelse($kitchenPrep['items'] as $idx => $item)
            <tr class="prep-item-row" data-category="{{ $item['cat_key'] }}" data-name="{{ strtolower($item['name']) }}">
              <td class="prep-td prep-td-index">{{ $idx + 1 }}</td>
              
              <!-- Item Name -->
              <td class="prep-td">
                <div class="prep-item-title">{{ $item['name'] }}</div>
              </td>

              <!-- Category Badge (Dynamic) -->
              <td class="prep-td">
                <span class="prep-badge" style="background: {{ $item['cat_bg'] }}; color: {{ $item['cat_text'] }}; font-weight: 700;">
                  {{ $item['cat_icon'] }} {{ $item['category'] }}
                </span>
              </td>

              <!-- Total Quantity To Prepare -->
              <td class="prep-td">
                <div class="prep-qty-main" style="color: {{ $item['cat_text'] }};">
                  {{ number_format($item['total_qty']) }} <span class="prep-qty-unit">{{ $item['unit'] }}</span>
                </div>
              </td>

              <!-- Source Breakdown -->
              <td class="prep-td prep-source-breakdown">
                <div>From Tiffins: <strong class="prep-source-val">{{ number_format($item['tiffin_qty']) }}</strong></div>
                @if($item['addon_qty'] > 0)
                  <div class="prep-addon-val">+ Add-ons: <strong>{{ number_format($item['addon_qty']) }}</strong></div>
                @endif
              </td>

              <!-- Week Breakdown Columns (when viewing full month) -->
              @if(in_array($kitchenPrep['filters']['filter_type'], ['month', 'this_month']) && $kitchenPrep['filters']['selected_week'] === 'all')
                <td class="prep-td prep-td-center prep-week-cell">{{ $item['week_breakdown'][1] ?? 0 }}</td>
                <td class="prep-td prep-td-center prep-week-cell">{{ $item['week_breakdown'][2] ?? 0 }}</td>
                <td class="prep-td prep-td-center prep-week-cell">{{ $item['week_breakdown'][3] ?? 0 }}</td>
                <td class="prep-td prep-td-center prep-week-cell">{{ $item['week_breakdown'][4] ?? 0 }}</td>
                <td class="prep-td prep-td-center prep-week-cell">{{ $item['week_breakdown'][5] ?? 0 }}</td>
              @endif

              <!-- Orders Count -->
              <td class="prep-td prep-orders-cell">
                {{ $item['orders_count'] }} {{ Str::plural('order', $item['orders_count']) }}
              </td>

              <!-- Kitchen Checklist Action -->
              <td class="prep-td prep-td-center">
                <label class="prep-checkbox-label">
                  <input type="checkbox" class="prep-checklist-checkbox" onchange="togglePrepDone(this)">
                </label>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="prep-empty-cell">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="prep-empty-icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <p class="prep-empty-title">No orders found for the selected time range.</p>
                <p class="prep-empty-subtitle">Try selecting "This Week" or a different Month/Date range above.</p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Detailed Kitchen Orders Preparation Sheet Card (8 Detailed Columns: ID, Name, Tiffin Plan, Optional Item Choosen, Add-ons, Note, Amount, Address) -->
  <div class="kp_kitchen_admin_panel_card prep-orders-detail-card" id="detailedOrdersSection">
    <div class="prep-table-toolbar">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <div class="prep-history-badge-icon" style="width: 38px; height: 38px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        </div>
        <div>
          <h3 class="prep-week-history-title" style="font-size: 1.05rem;">Detailed Kitchen Orders Breakdown ({{ count($kitchenPrep['orders_list'] ?? []) }} Orders)</h3>
          <p class="prep-week-history-subtitle">Individual order-by-order breakdown with optional item choices, add-ons, notes, and delivery address.</p>
        </div>
      </div>
      <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <div class="prep-search-wrap">
          <input type="text" id="orderSearchInput" class="kp_kitchen_admin_panel_form_input prep-search-input" placeholder="Search order ID, customer, address..." onkeyup="searchOrdersTable(this.value)">
          <svg class="prep-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>
        <a id="exportDetailedOrdersPdfBtn" href="{{ route('reports.export-pdf', ['type' => 'detailed_orders', 'filter_type' => $kitchenPrep['filters']['filter_type'], 'selected_date' => $kitchenPrep['filters']['selected_date'], 'selected_month' => $kitchenPrep['filters']['selected_month'], 'selected_week' => $kitchenPrep['filters']['selected_week'], 'start_date' => $kitchenPrep['filters']['start_date'] ?? '', 'end_date' => $kitchenPrep['filters']['end_date'] ?? '', 'category' => $kitchenPrep['filters']['category'] ?? 'all', 'search' => $kitchenPrep['filters']['search'] ?? '']) }}" target="_blank" class="kp_kitchen_admin_panel_primary_button prep-btn-pdf" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; padding: 0.55rem 0.95rem; font-size: 0.85rem; background: linear-gradient(135deg, #e11d48, #be123c); border-color: #be123c; color: #fff; white-space: nowrap; border-radius: 8px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
          Download Orders PDF
        </a>
      </div>
    </div>

    <div class="prep-table-scroll">
      <table class="kp_kitchen_admin_panel_table prep-table" id="detailedOrdersTable">
        <thead>
          <tr class="prep-table-head-row">
            <th class="prep-th" style="width: 100px;">ID</th>
            <th class="prep-th">Name</th>
            <th class="prep-th">Tiffin Plan</th>
            <th class="prep-th" style="text-align: center; width: 140px;">Fulfillment</th>
            <th class="prep-th">Optional Item Choosen</th>
            <th class="prep-th">Add-ons</th>
            <th class="prep-th">Note</th>
            <th class="prep-th" style="text-align: right; width: 100px;">Amount</th>
            <th class="prep-th">Address</th>
          </tr>
        </thead>
        <tbody id="detailedOrdersTableBody">
          @forelse(($kitchenPrep['orders_list'] ?? []) as $oRow)
            <tr class="prep-order-row" data-search="{{ strtolower($oRow['id'] . ' ' . $oRow['name'] . ' ' . $oRow['tiffin_plan'] . ' ' . ($oRow['order_type_label'] ?? '') . ' ' . ($oRow['order_type'] ?? '') . ' ' . $oRow['optional_item_choosen'] . ' ' . $oRow['add_ons'] . ' ' . $oRow['note'] . ' ' . $oRow['address']) }}">
              <!-- 1. ID -->
              <td class="prep-td">
                <span class="prep-order-id-badge">#{{ $oRow['id'] }}</span>
              </td>

              <!-- 2. Name -->
              <td class="prep-td">
                <div class="prep-item-title" style="font-size: 0.9rem;">{{ $oRow['name'] }}</div>
              </td>

              <!-- 3. Tiffin Plan -->
              <td class="prep-td">
                <span class="prep-plan-pill">{{ $oRow['tiffin_plan'] }}</span>
              </td>

              <!-- 4. Fulfillment: Home Delivery vs Customer Pickup -->
              <td class="prep-td" style="text-align: center;">
                @if(($oRow['order_type'] ?? '') === 'pickup')
                  <span class="prep-fulfillment-pill prep-fulfillment-pickup">🛍️ Customer Pickup</span>
                @else
                  <span class="prep-fulfillment-pill prep-fulfillment-delivery">🚚 Home Delivery</span>
                @endif
              </td>

              <!-- 5. Optional Item Choosen -->
              <td class="prep-td prep-choices-cell">
                @if($oRow['optional_item_choosen'] !== '-')
                  <div class="prep-choices-text">{{ $oRow['optional_item_choosen'] }}</div>
                @else
                  <span class="prep-empty-dash">&mdash;</span>
                @endif
              </td>

              <!-- 6. Add-ons -->
              <td class="prep-td prep-addons-cell">
                @if($oRow['add_ons'] !== '-')
                  <div class="prep-addon-text">{{ $oRow['add_ons'] }}</div>
                @else
                  <span class="prep-empty-dash">&mdash;</span>
                @endif
              </td>

              <!-- 7. Note -->
              <td class="prep-td prep-note-cell">
                @if($oRow['note'] !== '-')
                  <div class="prep-note-text"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 4px; display: inline-block;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>{{ $oRow['note'] }}</div>
                @else
                  <span class="prep-empty-dash">&mdash;</span>
                @endif
              </td>

              <!-- 8. Amount -->
              <td class="prep-td" style="text-align: right; font-weight: 700; color: var(--text-primary, #0F172A);">
                {{ $oRow['amount_formatted'] }}
              </td>

              <!-- 9. Address -->
              <td class="prep-td prep-address-cell">
                @if(($oRow['order_type'] ?? '') === 'pickup')
                  <div class="prep-address-text" style="color: #92400E; font-weight: 600;">[Store / Kitchen Pickup]</div>
                @else
                  <div class="prep-address-text">{{ $oRow['address'] }}</div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="prep-empty-cell">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="prep-empty-icon"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <p class="prep-empty-title">No orders found for the selected time range.</p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- General Operations & Audit CSV Exports Section -->
  <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-top: 2.5rem; margin-bottom: 1rem;">
    <div>
      <h3 class="kp_kitchen_admin_panel_section_title" style="font-size: 1.15rem;">Business Operations &amp; Audit Exports</h3>
      <p class="kp_kitchen_admin_panel_section_text">Download comprehensive raw spreadsheets for sales logs, driver performance, and customer directories.</p>
    </div>
  </div>

  <div class="kp_kitchen_admin_panel_stats_grid kp_kitchen_admin_panel_stats_grid_three" style="margin-bottom: 2rem;">
    <article class="kp_kitchen_admin_panel_stat_card hover-card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-primary-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF6B6B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content" style="flex:1;">
        <span class="kp_kitchen_admin_panel_stat_label">Sales &amp; Orders Log</span>
        <p class="kp_kitchen_admin_panel_card_subtitle" style="margin: 0.25rem 0 1rem 0;">Download detailed list of orders, fulfillment method, plans, and add-ons.</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
          <a href="{{ url('/api/reports/export?type=sales') }}" class="kp_kitchen_admin_panel_secondary_button" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; padding: 0.5rem 0.85rem; font-size: 0.85rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export CSV
          </a>
          <a href="{{ route('reports.export-pdf', ['type' => 'sales']) }}" target="_blank" class="kp_kitchen_admin_panel_primary_button" style="text-decoration:none; display:inline-flex; align-items:center; gap:0.4rem; padding: 0.5rem 0.85rem; font-size: 0.85rem; background: linear-gradient(135deg, #e11d48, #be123c); border-color: #be123c; color: #fff;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            Export PDF
          </a>
        </div>
      </div>
    </article>

    <article class="kp_kitchen_admin_panel_stat_card hover-card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-success-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ECC71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><line x1="16" y1="8" x2="20" y2="8"/><line x1="16" y1="12" x2="23" y2="12"/><line x1="1" y1="16" x2="23" y2="16"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content" style="flex:1;">
        <span class="kp_kitchen_admin_panel_stat_label">Driver Performance</span>
        <p class="kp_kitchen_admin_panel_card_subtitle" style="margin: 0.25rem 0 1rem 0;">Download driver profiles, vehicles, and assigned postcodes.</p>
        <a href="{{ url('/api/reports/export?type=drivers') }}" class="kp_kitchen_admin_panel_primary_button" style="text-decoration:none; display:inline-block; text-align:center;">Export CSV</a>
      </div>
    </article>

    <article class="kp_kitchen_admin_panel_stat_card hover-card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-warning-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F1C40F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content" style="flex:1;">
        <span class="kp_kitchen_admin_panel_stat_label">Customer Directory</span>
        <p class="kp_kitchen_admin_panel_card_subtitle" style="margin: 0.25rem 0 1rem 0;">Download customer contacts, default postcodes, and total spend.</p>
        <a href="{{ url('/api/reports/export?type=customers') }}" class="kp_kitchen_admin_panel_primary_button" style="text-decoration:none; display:inline-block; text-align:center;">Export CSV</a>
      </div>
    </article>
  </div>
</section>

<!-- Scoped Styles for Light & Dark Theme Support -->
<style>
/* Base Theme-Aware Variables & Cards */
.prep-top-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.prep-top-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.prep-highlight-text {
  color: var(--primary-color, #FF6B6B);
}

.prep-filter-card {
  margin-bottom: 1.5rem;
  padding: 1.25rem;
  border-radius: var(--border-radius, 16px);
  background-color: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, rgba(0,0,0,0.05));
  box-shadow: var(--glass-shadow, 0 8px 32px 0 rgba(0,0,0,0.06));
}

.prep-pills-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}
.prep-pills-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-secondary, #64748B);
  margin-right: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.prep-pill-btn {
  background: var(--bg-color, #F8F9FA);
  border: 1px solid var(--panel-border, #E2E8F0);
  color: var(--text-secondary, #475569);
  padding: 0.35rem 0.85rem;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition, all 0.2s ease);
}
.prep-pill-btn:hover {
  border-color: var(--primary-color, #FF6B6B);
  color: var(--text-primary, #1E293B);
}
.prep-pill-active {
  background: var(--primary-color, #FF6B6B) !important;
  color: #ffffff !important;
  border-color: var(--primary-color, #FF6B6B) !important;
  box-shadow: 0 2px 8px rgba(255, 107, 107, 0.35);
}

.prep-filter-inputs-box {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  align-items: flex-end;
  background: var(--bg-color, #F8FAFC);
  padding: 1.1rem;
  border-radius: 10px;
  border: 1px solid var(--panel-border, #E2E8F0);
}
.prep-control-group {
  display: flex;
  flex-direction: column;
}
.prep-control-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary, #475569);
  margin-bottom: 0.4rem;
}
.prep-select,
.prep-input {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border-radius: 8px;
  border: 1px solid var(--panel-border, #CBD5E1);
  background-color: var(--panel-bg, #FFFFFF);
  color: var(--text-primary, #1E293B);
  font-size: 0.85rem;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.prep-select:focus,
.prep-input:focus {
  border-color: var(--primary-color, #FF6B6B);
  box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.15);
}
.prep-action-buttons {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.prep-submit-btn {
  padding: 0.55rem 1.25rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}
.prep-reset-btn {
  padding: 0.55rem 1rem;
  border-radius: 8px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  font-weight: 600;
}
.prep-btn-secondary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--panel-bg, #ffffff);
  border: 1px solid var(--panel-border, #E2E8F0);
  color: var(--text-primary, #1E293B);
  padding: 0.6rem 1.1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.prep-btn-secondary:hover {
  border-color: var(--primary-color, #FF6B6B);
  color: var(--primary-color, #FF6B6B);
}
.prep-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  padding: 0.6rem 1.1rem;
  border-radius: 8px;
  font-weight: 600;
}

/* KPI Cards */
.prep-kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.prep-kpi-card {
  padding: 1.25rem;
  border-radius: var(--border-radius, 16px);
  background-color: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, rgba(0,0,0,0.05));
  box-shadow: var(--glass-shadow, 0 8px 32px 0 rgba(0,0,0,0.04));
}
.prep-kpi-rotis { border-left: 4px solid #FF6B6B !important; }
.prep-kpi-curries { border-left: 4px solid #F59E0B !important; }
.prep-kpi-rice { border-left: 4px solid #10B981 !important; }
.prep-kpi-salads { border-left: 4px solid #0EA5E9 !important; }
.prep-kpi-tiffins { border-left: 4px solid #8B5CF6 !important; }

.prep-kpi-inner {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.prep-kpi-label {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-secondary, #64748B);
  letter-spacing: 0.5px;
}
.prep-kpi-number {
  font-size: 2rem;
  font-weight: 800;
  margin: 0.25rem 0 0 0;
  font-family: var(--font-title, inherit);
}
.prep-rotis-color { color: #FF6B6B; }
.prep-curries-color { color: #F59E0B; }
.prep-rice-color { color: #10B981; }
.prep-salads-color { color: #0EA5E9; }
.prep-tiffins-color { color: #8B5CF6; }

.prep-kpi-hint {
  font-size: 0.8rem;
  color: var(--text-muted, #94A3B8);
  font-weight: 500;
}
.prep-kpi-icon-box {
  padding: 0.75rem;
  border-radius: 10px;
}
.prep-rotis-icon-bg { background: rgba(255, 107, 107, 0.12); color: #FF6B6B; }
.prep-curries-icon-bg { background: rgba(245, 158, 11, 0.12); color: #D97706; }
.prep-rice-icon-bg { background: rgba(16, 185, 129, 0.12); color: #059669; }
.prep-salads-icon-bg { background: rgba(14, 165, 233, 0.12); color: #0284C7; }
.prep-tiffins-icon-bg { background: rgba(139, 92, 246, 0.12); color: #7C3AED; }

/* 4 to 5 Weeks Order History Breakdown Section */
.prep-week-history-card {
  margin-bottom: 1.5rem;
  padding: 1.35rem;
  border-radius: var(--border-radius, 16px);
  background-color: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, rgba(0,0,0,0.05));
  box-shadow: var(--glass-shadow, 0 8px 32px 0 rgba(0,0,0,0.04));
}
.prep-week-history-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.prep-history-badge-icon {
  background: linear-gradient(135deg, rgba(255, 107, 107, 0.15), rgba(255, 142, 83, 0.15));
  color: var(--primary-color, #FF6B6B);
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.prep-week-history-title {
  margin: 0;
  font-size: 1.08rem;
  font-weight: 700;
  color: var(--text-primary, #1E293B);
  letter-spacing: -0.2px;
}
.prep-week-history-subtitle {
  margin: 0.2rem 0 0 0;
  font-size: 0.82rem;
  color: var(--text-secondary, #64748B);
}
.prep-active-week-badge {
  background: rgba(255, 107, 107, 0.15);
  border: 1px solid rgba(255, 107, 107, 0.3);
  color: #FF6B6B;
  padding: 0.35rem 0.85rem;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 700;
}
.prep-active-allweeks-badge {
  background: var(--bg-color, #F1F5F9);
  border: 1px solid var(--panel-border, #E2E8F0);
  color: var(--text-secondary, #475569);
  padding: 0.35rem 0.85rem;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
}

.prep-weeks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}
.prep-week-card {
  background-color: var(--bg-color, #F8FAFC);
  border: 1px solid var(--panel-border, #E2E8F0);
  border-radius: 12px;
  padding: 1.1rem;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  display: flex;
  flex-direction: column;
}
.prep-week-card:hover {
  transform: translateY(-3px);
  border-color: var(--primary-color, #FF6B6B);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
}
.prep-week-card-active {
  background: linear-gradient(180deg, rgba(255, 107, 107, 0.08) 0%, var(--bg-color, #F8FAFC) 100%) !important;
  border-color: var(--primary-color, #FF6B6B) !important;
  box-shadow: 0 4px 14px rgba(255, 107, 107, 0.2) !important;
}

.prep-week-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.35rem;
}
.prep-week-card-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.prep-week-card-title {
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-primary, #1E293B);
}
.prep-week-card-active-tag {
  background: var(--primary-color, #FF6B6B);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.prep-week-orders-pill {
  font-size: 0.75rem;
  background: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, #E2E8F0);
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  color: var(--text-secondary, #475569);
  font-weight: 600;
}
.prep-week-date-range {
  font-size: 0.78rem;
  color: var(--text-muted, #64748B);
  margin-bottom: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.prep-week-stats-container {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  border-top: 1px solid var(--panel-border, rgba(0,0,0,0.06));
  padding-top: 0.75rem;
  margin-top: auto;
}
.prep-week-stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
}
.prep-week-stat-label {
  color: var(--text-secondary, #64748B);
  font-weight: 500;
}
.prep-week-stat-val-rotis {
  color: #FF6B6B;
  font-weight: 700;
}
.prep-week-stat-val-curries {
  color: #D97706;
  font-weight: 700;
}
.prep-week-stat-val-tiffins {
  color: #7C3AED;
  font-weight: 700;
}

/* Production Table Card */
.prep-table-card {
  padding: 1.5rem;
  border-radius: var(--border-radius, 16px);
  background-color: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, rgba(0,0,0,0.05));
  box-shadow: var(--glass-shadow, 0 8px 32px 0 rgba(0,0,0,0.04));
  margin-bottom: 2rem;
}
.prep-table-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid var(--panel-border, #F1F5F9);
  padding-bottom: 1rem;
}
.prep-cat-tabs-wrap {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.prep-cat-tab {
  background: var(--bg-color, #F8FAFC);
  border: 1px solid var(--panel-border, #E2E8F0);
  color: var(--text-secondary, #64748B);
  padding: 0.4rem 0.9rem;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.prep-cat-tab:hover {
  border-color: var(--primary-color, #FF6B6B);
  color: var(--text-primary, #1E293B);
}
.prep-cat-active {
  background: var(--primary-color, #FF6B6B) !important;
  color: #ffffff !important;
  border-color: var(--primary-color, #FF6B6B) !important;
  box-shadow: 0 2px 8px rgba(255, 107, 107, 0.35);
}

.prep-search-wrap {
  position: relative;
  min-width: 240px;
}
.prep-search-input {
  width: 100%;
  padding: 0.5rem 1rem 0.5rem 2.25rem;
  border-radius: 8px;
  border: 1px solid var(--panel-border, #CBD5E1);
  background-color: var(--bg-color, #F8FAFC);
  color: var(--text-primary, #1E293B);
  font-size: 0.88rem;
  outline: none;
}
.prep-search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted, #94A3B8);
}

/* Table */
.prep-table-scroll {
  overflow-x: auto;
}
.prep-table {
  width: 100%;
  text-align: left;
  border-collapse: collapse;
}
.prep-table-head-row {
  background: var(--bg-color, #F8FAFC);
  border-bottom: 2px solid var(--panel-border, #E2E8F0);
}
.prep-th {
  padding: 0.85rem 1rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text-secondary, #475569);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.prep-th-center {
  text-align: center;
}

.prep-item-row {
  border-bottom: 1px solid var(--panel-border, #F1F5F9);
  transition: background 0.15s ease;
}
.prep-item-row:hover {
  background-color: rgba(255, 107, 107, 0.04);
}
.prep-td {
  padding: 0.85rem 1rem;
}
.prep-td-index {
  color: var(--text-muted, #94A3B8);
  font-size: 0.85rem;
}
.prep-item-title {
  font-weight: 700;
  color: var(--text-primary, #1E293B);
  font-size: 0.95rem;
}

.prep-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: 20px;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}
.prep-badge-bread { background: rgba(255, 107, 107, 0.12); color: #FF6B6B; }
.prep-badge-curry { background: rgba(245, 158, 11, 0.12); color: #D97706; }
.prep-badge-rice { background: rgba(16, 185, 129, 0.12); color: #059669; }
.prep-badge-salad { background: rgba(14, 165, 233, 0.12); color: #0284C7; }
.prep-badge-dessert { background: rgba(219, 39, 119, 0.12); color: #DB2777; }
.prep-badge-other { background: var(--bg-color, #F1F5F9); color: var(--text-secondary, #475569); }

.prep-qty-main {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text-primary, #0F172A);
  font-family: var(--font-title, inherit);
}
.prep-qty-rotis { color: #FF6B6B !important; }
.prep-qty-unit {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted, #64748B);
}

.prep-source-breakdown {
  font-size: 0.82rem;
  color: var(--text-secondary, #64748B);
}
.prep-source-val {
  color: var(--text-primary, #334155);
}
.prep-addon-val {
  color: #10B981;
  margin-top: 0.15rem;
}
.prep-week-cell {
  color: var(--text-primary, #334155);
  font-weight: 600;
  font-size: 0.85rem;
}
.prep-orders-cell {
  font-size: 0.85rem;
  color: var(--text-secondary, #64748B);
}
.prep-td-center {
  text-align: center;
}
.prep-checkbox-label {
  display: inline-flex;
  align-items: center;
  cursor: pointer;
}
.prep-checklist-checkbox {
  width: 18px;
  height: 18px;
  accent-color: #10B981;
  cursor: pointer;
}
.prep-item-done {
  background-color: rgba(16, 185, 129, 0.08) !important;
  opacity: 0.75;
}
.prep-item-done .prep-item-title {
  text-decoration: line-through;
  color: var(--text-muted, #64748B) !important;
}

.prep-empty-cell {
  padding: 3.5rem 1rem;
  text-align: center;
}
.prep-empty-icon {
  margin-bottom: 0.75rem;
  opacity: 0.5;
  color: var(--text-muted, #94A3B8);
}
.prep-empty-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary, #64748B);
}
.prep-empty-subtitle {
  margin: 0.25rem 0 0 0;
  font-size: 0.82rem;
  color: var(--text-muted, #94A3B8);
}

/* Detailed Orders Sheet Card & Cells */
.prep-orders-detail-card {
  padding: 1.5rem;
  border-radius: var(--border-radius, 16px);
  background-color: var(--panel-bg, #FFFFFF);
  border: 1px solid var(--panel-border, rgba(0,0,0,0.05));
  box-shadow: var(--glass-shadow, 0 8px 32px 0 rgba(0,0,0,0.04));
  margin-bottom: 2rem;
}
.prep-order-id-badge {
  background: rgba(255, 107, 107, 0.12);
  color: var(--primary-color, #FF6B6B);
  font-weight: 700;
  font-size: 0.8rem;
  padding: 0.25rem 0.55rem;
  border-radius: 6px;
  display: inline-block;
  font-family: monospace;
}
.prep-plan-pill {
  background: var(--bg-color, #F8FAFC);
  border: 1px solid var(--panel-border, #E2E8F0);
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-primary, #1E293B);
  display: inline-block;
}
.prep-fulfillment-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.25rem 0.6rem;
  border-radius: 9999px;
  white-space: nowrap;
}
.prep-fulfillment-delivery {
  background: rgba(14, 165, 233, 0.12);
  color: #0284C7;
  border: 1px solid rgba(14, 165, 233, 0.25);
}
.prep-fulfillment-pickup {
  background: rgba(245, 158, 11, 0.12);
  color: #D97706;
  border: 1px solid rgba(245, 158, 11, 0.25);
}
.prep-choices-text {
  font-size: 0.84rem;
  color: var(--text-primary, #334155);
  line-height: 1.35;
  max-width: 240px;
}
.prep-addon-text {
  font-size: 0.82rem;
  color: #10B981;
  font-weight: 600;
  line-height: 1.35;
  max-width: 200px;
}
.prep-note-text {
  font-size: 0.82rem;
  color: #D97706;
  background: rgba(245, 158, 11, 0.1);
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  display: inline-block;
  max-width: 200px;
  font-weight: 500;
}
.prep-address-text {
  font-size: 0.82rem;
  color: var(--text-secondary, #64748B);
  line-height: 1.35;
  max-width: 260px;
}
.prep-empty-dash {
  color: var(--text-muted, #94A3B8);
}

/* =========================================================================
   DARK THEME EXPLICIT OVERRIDES [data-kp-theme="dark"]
   ========================================================================= */
[data-kp-theme="dark"] .prep-filter-inputs-box {
  background: rgba(11, 15, 25, 0.8);
  border-color: rgba(255, 255, 255, 0.08);
}

[data-kp-theme="dark"] .prep-select,
[data-kp-theme="dark"] .prep-input,
[data-kp-theme="dark"] .prep-search-input {
  background-color: #161C2D !important;
  border-color: rgba(255, 255, 255, 0.12) !important;
  color: #F8FAFC !important;
}

[data-kp-theme="dark"] .prep-select option {
  background-color: #161C2D !important;
  color: #F8FAFC !important;
}

[data-kp-theme="dark"] .prep-pill-btn {
  background: rgba(22, 28, 45, 0.8);
  border-color: rgba(255, 255, 255, 0.08);
  color: #94A3B8;
}
[data-kp-theme="dark"] .prep-pill-btn:hover {
  color: #F8FAFC;
  border-color: #FF6B6B;
}

[data-kp-theme="dark"] .prep-btn-secondary {
  background: rgba(22, 28, 45, 0.8);
  border-color: rgba(255, 255, 255, 0.1);
  color: #E2E8F0;
}

[data-kp-theme="dark"] .prep-week-card {
  background-color: rgba(15, 23, 42, 0.6);
  border-color: rgba(255, 255, 255, 0.08);
}
[data-kp-theme="dark"] .prep-week-card-active {
  background: linear-gradient(180deg, rgba(255, 107, 107, 0.15) 0%, rgba(15, 23, 42, 0.9) 100%) !important;
  border-color: #FF6B6B !important;
}
[data-kp-theme="dark"] .prep-week-orders-pill {
  background: rgba(30, 41, 59, 0.8);
  border-color: rgba(255, 255, 255, 0.08);
  color: #CBD5E1;
}

[data-kp-theme="dark"] .prep-cat-tab {
  background: rgba(22, 28, 45, 0.8);
  border-color: rgba(255, 255, 255, 0.08);
  color: #94A3B8;
}
[data-kp-theme="dark"] .prep-cat-tab:hover {
  color: #F8FAFC;
  border-color: #FF6B6B;
}

[data-kp-theme="dark"] .prep-table-head-row {
  background: rgba(15, 23, 42, 0.8);
  border-bottom-color: rgba(255, 255, 255, 0.1);
}
[data-kp-theme="dark"] .prep-item-row:hover,
[data-kp-theme="dark"] .prep-order-row:hover {
  background-color: rgba(255, 107, 107, 0.08);
}
[data-kp-theme="dark"] .prep-item-done {
  background-color: rgba(16, 185, 129, 0.15) !important;
}
[data-kp-theme="dark"] .prep-plan-pill {
  background: rgba(30, 41, 59, 0.8);
  border-color: rgba(255, 255, 255, 0.1);
  color: #F8FAFC;
}
[data-kp-theme="dark"] .prep-choices-text {
  color: #E2E8F0;
}
[data-kp-theme="dark"] .prep-address-text {
  color: #94A3B8;
}
[data-kp-theme="dark"] .prep-fulfillment-delivery {
  background: rgba(14, 165, 233, 0.2);
  color: #38BDF8;
  border-color: rgba(56, 189, 248, 0.3);
}
[data-kp-theme="dark"] .prep-fulfillment-pickup {
  background: rgba(245, 158, 11, 0.2);
  color: #FBBF24;
  border-color: rgba(251, 191, 36, 0.3);
}

/* Print Sheet Styling */
@media print {
  body * {
    visibility: hidden;
  }
  #reportsPage, #reportsPage * {
    visibility: visible;
  }
  #sidebar, .kp_kitchen_admin_panel_sidebar, .kp_kitchen_admin_panel_topbar, #kitchenPrepFilterForm, .prep-top-toolbar button, #exportPrepCsvBtn, #exportPrepPdfBtn, #exportDetailedOrdersPdfBtn, #categoryFilterTabs, .prep-search-wrap, .kp_kitchen_admin_panel_stats_grid_three, .prep-action-buttons, .prep-pills-row, .prep-checkbox-label {
    display: none !important;
  }
  #reportsPage {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 15px;
    background: #fff !important;
    color: #000 !important;
  }
  .kp_kitchen_admin_panel_stat_card, .kp_kitchen_admin_panel_card {
    box-shadow: none !important;
    border: 1px solid #cbd5e1 !important;
    background: #fff !important;
    color: #000 !important;
    page-break-inside: avoid;
    margin-bottom: 1.25rem !important;
  }
  .prep-table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 8.5pt !important;
  }
  .prep-table th, .prep-table td {
    border: 1px solid #cbd5e1 !important;
    padding: 5px 7px !important;
    color: #000 !important;
  }
  .prep-table-head-row {
    background: #f1f5f9 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .prep-order-id-badge, .prep-plan-pill, .prep-badge, .prep-fulfillment-pill {
    border: 1px solid #cbd5e1 !important;
    background: #f8fafc !important;
    color: #000 !important;
    padding: 2px 4px !important;
    font-size: 7.5pt !important;
  }
}
</style>

<script>
function setFilterType(type) {
  document.getElementById('filterTypeInput').value = type;
  
  // Toggle visibility of specific parameter inputs
  document.getElementById('singleDateContainer').style.display = (type === 'single_date') ? 'flex' : 'none';
  document.getElementById('customStartContainer').style.display = (type === 'custom') ? 'flex' : 'none';
  document.getElementById('customEndContainer').style.display = (type === 'custom') ? 'flex' : 'none';
  
  if (type === 'today' || type === 'yesterday' || type === 'this_week' || type === 'last_week') {
    document.getElementById('kitchenPrepFilterForm').submit();
  } else if (type === 'month') {
    document.getElementById('selectedWeekInput').value = 'all';
    document.getElementById('kitchenPrepFilterForm').submit();
  }
}

function selectWeek(weekNum) {
  document.getElementById('filterTypeInput').value = 'month';
  document.getElementById('selectedWeekInput').value = weekNum;
  document.getElementById('kitchenPrepFilterForm').submit();
}

function submitPrepFilter() {
  document.getElementById('kitchenPrepFilterForm').submit();
}

function filterCategory(cat, btn) {
  document.querySelectorAll('.prep-cat-tab').forEach(b => b.classList.remove('prep-cat-active'));
  btn.classList.add('prep-cat-active');

  const rows = document.querySelectorAll('.prep-item-row');
  rows.forEach(row => {
    if (cat === 'all' || row.getAttribute('data-category') === cat) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function searchPrepTable(query) {
  query = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.prep-item-row');
  rows.forEach(row => {
    const name = row.getAttribute('data-name') || '';
    if (!query || name.includes(query)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function searchOrdersTable(query) {
  query = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.prep-order-row');
  rows.forEach(row => {
    const text = (row.getAttribute('data-search') || '').toLowerCase();
    if (!query || text.includes(query)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });

  const btn = document.getElementById('exportDetailedOrdersPdfBtn');
  if (btn) {
    try {
      const url = new URL(btn.href, window.location.origin);
      if (query) {
        url.searchParams.set('search', query);
      } else {
        url.searchParams.delete('search');
      }
      btn.href = url.pathname + url.search;
    } catch(e) {}
  }
}

function togglePrepDone(checkbox) {
  const tr = checkbox.closest('tr');
  if (checkbox.checked) {
    tr.classList.add('prep-item-done');
  } else {
    tr.classList.remove('prep-item-done');
  }
}

window.printKitchenPrepSheet = function() {
  window.print();
};
</script>
@endsection
