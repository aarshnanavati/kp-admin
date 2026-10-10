@extends('layouts.app')

@section('title', 'Dashboard')
@section('current_page', 'dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Business Overview • Adelaide, Australia')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="dashboardPage">
  <div class="dash-main-wrap">

    <!-- Top Header Bar with Date Filter -->
    <div class="dash-top-header">
      <div class="dash-header-title-box">
        <h1>Dashboard</h1>
        <div class="dash-header-subtitle">
          <span>Business Overview</span>
          <span class="dash-dot">•</span>
          <span>Adelaide, Australia</span>
        </div>
      </div>

      <!-- Dynamic Date Selector Filter -->
      <div class="dash-date-picker-wrap">
        <button type="button" class="dash-date-btn" id="dashDateToggleBtn" onclick="toggleDateDropdown()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
          <span id="currentDateLabel">{{ $formattedSelectedDate }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>

        <!-- Date Dropdown Popover -->
        <div class="dash-date-dropdown" id="dashDateDropdown">
          <div class="dash-dropdown-header">Filter by Date</div>
          <div class="dash-date-input-row">
            <input type="date" id="dashNativeDateInput" class="dash-date-native-input" value="{{ $selectedDate }}">
            <button type="button" class="dash-date-apply-btn" onclick="applyCustomDate()">Apply</button>
          </div>

          <div class="dash-quick-dates-label">Quick Select:</div>
          <div class="dash-quick-chips">
            <button type="button" class="dash-chip-btn {{ $selectedDate === now()->toDateString() ? 'active' : '' }}" onclick="selectDateChip('{{ now()->toDateString() }}')">
              Today
            </button>
            <button type="button" class="dash-chip-btn {{ $selectedDate === now()->subDay()->toDateString() ? 'active' : '' }}" onclick="selectDateChip('{{ now()->subDay()->toDateString() }}')">
              Yesterday
            </button>
            @foreach($recentOrderDates as $rDate)
              @if($rDate['date'] !== now()->toDateString() && $rDate['date'] !== now()->subDay()->toDateString())
                <button type="button" class="dash-chip-btn {{ $selectedDate === $rDate['date'] ? 'active' : '' }}" onclick="selectDateChip('{{ $rDate['date'] }}')">
                  {{ $rDate['label'] }}
                </button>
              @endif
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <!-- ROW 1: Key Metrics Stats Grid (5 Cards) -->
    <div class="dash-kpi-grid">
      <!-- 1. Today's Revenue -->
      <article class="dash-kpi-card">
        <div class="dash-kpi-top">
          <div class="dash-kpi-icon-box dash-kpi-icon-red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <div class="dash-kpi-label-wrap">
            <span class="dash-kpi-label">Today's Revenue</span>
          </div>
        </div>
        <div class="dash-kpi-value-row">
          <span class="dash-kpi-value">A$ {{ number_format($todayRevenue, 2) }}</span>
          @if($revenueChangePercent >= 0)
            <span class="dash-kpi-pill dash-kpi-pill-up">▲ +{{ $revenueChangePercent }}%</span>
          @else
            <span class="dash-kpi-pill dash-kpi-pill-down">▼ {{ $revenueChangePercent }}%</span>
          @endif
        </div>
        <span class="dash-kpi-hint">vs yesterday (A$ {{ number_format($yesterdayRevenue, 2) }})</span>
      </article>

      <!-- 2. Total Orders (Today) -->
      <article class="dash-kpi-card">
        <div class="dash-kpi-top">
          <div class="dash-kpi-icon-box dash-kpi-icon-red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
          </div>
          <div class="dash-kpi-label-wrap">
            <span class="dash-kpi-label">Total Orders (Today)</span>
          </div>
        </div>
        <div class="dash-kpi-value-row">
          <span class="dash-kpi-value">{{ number_format($totalOrdersCount) }}</span>
          @if($ordersChangePercent >= 0)
            <span class="dash-kpi-pill dash-kpi-pill-up">▲ +{{ $ordersChangePercent }}%</span>
          @else
            <span class="dash-kpi-pill dash-kpi-pill-down">▼ {{ $ordersChangePercent }}%</span>
          @endif
        </div>
        <span class="dash-kpi-hint">{{ $deliveryOrdersCount }} Delivery &nbsp;|&nbsp; {{ $pickupOrdersCount }} Pickup</span>
      </article>

      <!-- 3. Active Customers -->
      <article class="dash-kpi-card">
        <div class="dash-kpi-top">
          <div class="dash-kpi-icon-box dash-kpi-icon-green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <div class="dash-kpi-label-wrap">
            <span class="dash-kpi-label">Active Customers</span>
          </div>
        </div>
        <div class="dash-kpi-value-row">
          <span class="dash-kpi-value">{{ number_format($displayCustomersCount) }}</span>
          <span class="dash-kpi-pill dash-kpi-pill-up">▲ +5%</span>
        </div>
        <span class="dash-kpi-hint">{{ $customersSubtitle }}</span>
      </article>

      <!-- 4. Active Tiffin Plans -->
      <article class="dash-kpi-card">
        <div class="dash-kpi-top">
          <div class="dash-kpi-icon-box dash-kpi-icon-amber">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
              <polyline points="2 17 12 22 22 17"></polyline>
              <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
          </div>
          <div class="dash-kpi-label-wrap">
            <span class="dash-kpi-label">Active Tiffin Plans</span>
          </div>
        </div>
        <div class="dash-kpi-value-row">
          <span class="dash-kpi-value">{{ number_format($activeTiffinsCount) }}</span>
          <span class="dash-kpi-pill dash-kpi-pill-up">▲ +2</span>
        </div>
        <span class="dash-kpi-hint">Running this week</span>
      </article>

      <!-- 5. Average Order Value -->
      <article class="dash-kpi-card">
        <div class="dash-kpi-top">
          <div class="dash-kpi-icon-box dash-kpi-icon-red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="20" x2="18" y2="10"></line>
              <line x1="12" y1="20" x2="12" y2="4"></line>
              <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
          </div>
          <div class="dash-kpi-label-wrap">
            <span class="dash-kpi-label">Average Order Value</span>
          </div>
        </div>
        <div class="dash-kpi-value-row">
          <span class="dash-kpi-value">A$ {{ number_format($aov, 2) }}</span>
          @if($aovChangePercent >= 0)
            <span class="dash-kpi-pill dash-kpi-pill-up">▲ +{{ $aovChangePercent }}%</span>
          @else
            <span class="dash-kpi-pill dash-kpi-pill-down">▼ {{ $aovChangePercent }}%</span>
          @endif
        </div>
        <span class="dash-kpi-hint">vs last week (A$ {{ number_format($priorAov, 2) }})</span>
      </article>
    </div>

    <!-- ROW 2: Order Status (Today) | Today's Menu | Quick Actions -->
    <div class="dash-row-grid-row2">
      <!-- 1. Order Status (Today) -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Order Status (Today)</h2>
          <a href="{{ route('orders') }}" class="dash-card-link">View All Orders &rarr;</a>
        </div>
        <div class="dash-status-grid">
          <!-- Pending -->
          <div class="dash-status-box dash-status-box-pending">
            <div class="dash-status-icon-round">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="dash-status-num">{{ $statusCounts['Pending'] }}</div>
            <div class="dash-status-name">Pending</div>
            <div class="dash-status-desc">Need confirmation</div>
          </div>

          <!-- Preparing -->
          <div class="dash-status-box dash-status-box-preparing">
            <div class="dash-status-icon-round">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"></path>
                <line x1="6" y1="17" x2="18" y2="17"></line>
              </svg>
            </div>
            <div class="dash-status-num">{{ $statusCounts['Preparing'] }}</div>
            <div class="dash-status-name">Preparing</div>
            <div class="dash-status-desc">In kitchen</div>
          </div>

          <!-- Ready -->
          <div class="dash-status-box dash-status-box-ready">
            <div class="dash-status-icon-round">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
              </svg>
            </div>
            <div class="dash-status-num">{{ $statusCounts['Ready'] }}</div>
            <div class="dash-status-name">Ready</div>
            <div class="dash-status-desc">Awaiting dispatch</div>
          </div>

          <!-- Out for Delivery -->
          <div class="dash-status-box dash-status-box-out">
            <div class="dash-status-icon-round">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="3" width="15" height="13"></rect>
                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                <circle cx="18.5" cy="18.5" r="2.5"></circle>
              </svg>
            </div>
            <div class="dash-status-num">{{ $statusCounts['Out for Delivery'] }}</div>
            <div class="dash-status-name">Out for Delivery</div>
            <div class="dash-status-desc">{{ $pickupsReadyCount }} Pickups Ready</div>
          </div>
        </div>
      </article>

      <!-- 2. Today's Menu -->
      <article class="dash-card">
        <div class="dash-card-header">
          <div>
            <h2 class="dash-card-title">Today's Menu</h2>
            <div class="dash-menu-header-meta">
              <span class="dash-menu-date-tag">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                {{ $formattedSelectedDate }}
              </span>
              <span class="dash-menu-status-pill">&#9679; Published</span>
            </div>
          </div>
          <a href="{{ route('tiffins') }}" class="dash-card-link">Manage Menu &rarr;</a>
        </div>

        <div class="dash-menu-list">
          @forelse(array_slice($todayMenuList, 0, 3) as $menuItem)
            <div class="dash-menu-item-row">
              <div class="dash-menu-item-left">
                @if($menuItem['image'])
                  <img src="{{ $menuItem['image'] }}" alt="{{ $menuItem['name'] }}" class="dash-menu-img">
                @else
                  <div class="dash-menu-img-placeholder">🍲</div>
                @endif
                <div class="dash-menu-details">
                  <div class="dash-menu-name" title="{{ $menuItem['name'] }}">{{ $menuItem['name'] }}</div>
                  <div class="dash-menu-desc" title="{{ $menuItem['description'] }}">{{ $menuItem['description'] }}</div>
                </div>
              </div>
              <div class="dash-menu-item-right">
                <span class="dash-menu-price">{{ $menuItem['price_formatted'] }}</span>
                <span class="dash-menu-stock-pill">{{ $menuItem['stock_left'] }} left</span>
              </div>
            </div>
          @empty
            <div style="text-align: center; padding: 24px 12px; color: #94A3B8; font-size: 0.85rem;">
              No active menu plans found.
              <div style="margin-top: 8px;">
                <a href="{{ route('tiffins') }}" class="dash-card-link">+ Create Tiffin Plan</a>
              </div>
            </div>
          @endforelse
        </div>
      </article>

      <!-- 3. Quick Actions -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Quick Actions</h2>
        </div>
        <div class="dash-actions-list">
          <a href="{{ route('tiffins') }}" class="dash-action-btn">
            <span class="dash-action-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
            </span>
            <span>Add Today's Menu</span>
          </a>

          <a href="{{ route('tiffins') }}" class="dash-action-btn">
            <span class="dash-action-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
            </span>
            <span>Create / Edit Tiffin Plan</span>
          </a>

          <a href="{{ url('/orders?status=Pending') }}" class="dash-action-btn">
            <span class="dash-action-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            </span>
            <span>View Pending Orders</span>
          </a>

          <a href="{{ route('drivers') }}" class="dash-action-btn">
            <span class="dash-action-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </span>
            <span>Manage Delivery Slots</span>
          </a>

          <a href="{{ route('notifications') }}" class="dash-action-btn">
            <span class="dash-action-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            </span>
            <span>Send Promotion</span>
          </a>
        </div>
      </article>
    </div>

    <!-- ROW 3: Revenue & Orders Trend | Orders by Fulfilment Type | Popular Menu Items (Today) -->
    <div class="dash-row-grid-row3">
      <!-- 1. Revenue & Orders Trend -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Revenue &amp; Orders Trend</h2>
          <select class="dash-select-period" onchange="alert('Displaying 7-day trend leading to {{ $formattedSelectedDate }}')">
            <option value="7">Last 7 Days</option>
          </select>
        </div>

        <div class="dash-trend-controls">
          <div class="dash-toggle-pills" id="trendTogglePills">
            <button type="button" class="dash-pill-btn active" onclick="switchTrendMetric('revenue', this)">Revenue</button>
            <button type="button" class="dash-pill-btn" onclick="switchTrendMetric('orders', this)">Orders</button>
            <button type="button" class="dash-pill-btn" onclick="switchTrendMetric('aov', this)">Average Order</button>
            <button type="button" class="dash-pill-btn" onclick="switchTrendMetric('profit', this)">Profit</button>
          </div>
        </div>

        <div class="dash-chart-canvas-box">
          <canvas id="revenueTrendChartCanvas"></canvas>
        </div>
      </article>

      <!-- 2. Orders by Fulfilment Type -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Orders by Fulfilment Type</h2>
        </div>

        <div class="dash-donut-canvas-box">
          <canvas id="fulfilmentDonutCanvas"></canvas>
          <div class="dash-donut-center-overlay">
            <div class="dash-donut-center-num">{{ $totalOrdersCount }}</div>
            <div class="dash-donut-center-label">Orders</div>
          </div>
        </div>

        <div class="dash-donut-legend">
          <div class="dash-legend-item">
            <span class="dash-legend-dot" style="background: #FF6B6B;"></span>
            <span>Delivery: <strong>{{ $deliveryOrdersCount }} ({{ $deliveryPercent }}%)</strong></span>
          </div>
          <div class="dash-legend-item">
            <span class="dash-legend-dot" style="background: #F59E0B;"></span>
            <span>Pickup: <strong>{{ $pickupOrdersCount }} ({{ $pickupPercent }}%)</strong></span>
          </div>
        </div>
      </article>

      <!-- 3. Popular Menu Items (Today) -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Popular Menu Items (Today)</h2>
          <a href="{{ route('reports') }}" class="dash-card-link">View All &rarr;</a>
        </div>

        <div class="dash-popular-list">
          @forelse($popularList as $pItem)
            <div class="dash-popular-row">
              <span class="dash-popular-rank">{{ $pItem['rank'] }}</span>
              @if($pItem['image'])
                <img src="{{ $pItem['image'] }}" alt="{{ $pItem['name'] }}" class="dash-popular-thumb">
              @else
                <div class="dash-popular-thumb" style="background: rgba(255,107,107,0.15); display: flex; align-items: center; justify-content: center; font-size: 1rem;">🍱</div>
              @endif
              <span class="dash-popular-name" title="{{ $pItem['name'] }}">{{ $pItem['name'] }}</span>
              <div class="dash-popular-bar-wrap">
                <div class="dash-popular-bar-fill" style="width: {{ $pItem['percent'] }}%;"></div>
              </div>
              <span class="dash-popular-orders">{{ $pItem['count'] }} {{ Str::plural('order', $pItem['count']) }}</span>
            </div>
          @empty
            <div style="text-align: center; padding: 24px; color: #94A3B8; font-size: 0.85rem;">
              No item orders recorded for this period yet.
            </div>
          @endforelse
        </div>
      </article>
    </div>

    <!-- ROW 4: Orders Timeline (Today) | Delivery Areas (Top Postcodes) | Upcoming Pickups & Deliveries -->
    <div class="dash-row-grid-row4">
      <!-- 1. Orders Timeline (Today) -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Orders Timeline (Today)</h2>
          <a href="{{ route('orders') }}" class="dash-card-link">View All &rarr;</a>
        </div>

        <div class="dash-table-wrap">
          <table class="dash-timeline-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Type</th>
                <th>Status</th>
                <th>Time</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($timelineOrders as $ord)
                <tr>
                  <td class="dash-timeline-id">#{{ $ord->order_number ?: $ord->id }}</td>
                  <td class="dash-timeline-cust">{{ $ord->customer }}</td>
                  <td style="color: #94A3B8; max-width: 130px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $ord->tiffin ?: 'Standard Tiffin' }}</td>
                  <td>
                    @if($ord->order_type === 'pickup')
                      <span class="dash-type-badge dash-type-pickup">Pickup</span>
                    @else
                      <span class="dash-type-badge dash-type-delivery">Delivery</span>
                    @endif
                  </td>
                  <td>
                    @php
                      $stClass = match($ord->status) {
                        'Preparing' => 'dash-status-pill-preparing',
                        'Ready' => 'dash-status-pill-ready',
                        'Out for Delivery' => 'dash-status-pill-out',
                        'Delivered' => 'dash-status-pill-delivered',
                        default => 'dash-status-pill-pending'
                      };
                    @endphp
                    <span class="dash-status-pill {{ $stClass }}">{{ $ord->status }}</span>
                  </td>
                  <td style="color: #94A3B8; font-size: 0.78rem;">
                    {{ $ord->created_at ? $ord->created_at->format('h:i A') : '12:00 PM' }}
                  </td>
                  <td style="text-align: right;">
                    <a href="{{ route('orders') }}" class="dash-table-dots">&bull;&bull;&bull;</a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" style="text-align: center; padding: 24px; color: #94A3B8;">
                    No orders scheduled for this date.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </article>

      <!-- 2. Delivery Areas (Top Postcodes) -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Delivery Areas (Top Postcodes)</h2>
          <span style="font-size: 0.78rem; color: #94A3B8; font-weight: 500;">Last 7 Days</span>
        </div>

        <div class="dash-postcodes-list">
          @forelse($topAreasList as $area)
            <div class="dash-postcode-row">
              <span class="dash-postcode-label">{{ $area['postcode'] }}</span>
              <div class="dash-postcode-bar-wrap">
                <div class="dash-postcode-bar-fill" style="width: {{ $area['percent'] }}%;"></div>
              </div>
              <span class="dash-postcode-val">{{ $area['count'] }}</span>
            </div>
          @empty
            <div style="text-align: center; padding: 24px; color: #94A3B8; font-size: 0.85rem;">
              No regional delivery data found.
            </div>
          @endforelse
        </div>
      </article>

      <!-- 3. Upcoming Pickups & Deliveries -->
      <article class="dash-card">
        <div class="dash-card-header">
          <h2 class="dash-card-title">Upcoming Pickups &amp; Deliveries</h2>
          <a href="{{ route('orders') }}" class="dash-card-link">View All &rarr;</a>
        </div>

        <div class="dash-upcoming-list">
          @forelse($upcomingOrders as $upOrder)
            <div class="dash-upcoming-row">
              <div class="dash-upcoming-left">
                <span class="dash-upcoming-icon">
                  @if($upOrder->order_type === 'pickup')
                    🛍️
                  @else
                    🚚
                  @endif
                </span>
                <span class="dash-upcoming-time">
                  {{ $upOrder->created_at ? $upOrder->created_at->format('h:i A') : '12:30 PM' }}
                </span>
                <span class="dash-upcoming-name">{{ $upOrder->customer }}</span>
              </div>
              <div>
                @if($upOrder->order_type === 'pickup')
                  <span class="dash-type-badge dash-type-pickup">Pickup</span>
                @else
                  <span class="dash-type-badge dash-type-delivery">Delivery</span>
                @endif
              </div>
            </div>
          @empty
            <div style="text-align: center; padding: 24px; color: #94A3B8; font-size: 0.85rem;">
              No pending deliveries or pickups for this date.
            </div>
          @endforelse
        </div>
      </article>
    </div>

  </div>
</section>

<!-- Dashboard Charts & Dynamic Interactions Script -->
<script>
(function() {
  // Chart datasets passed dynamically from PHP
  const trendLabels = @json($trendLabels);
  const trendMetrics = {
    revenue: {
      data: @json($trendRevenue),
      label: 'Revenue ($)',
      prefix: 'A$ ',
      color: '#FF6B6B',
      bg: 'rgba(255, 107, 107, 0.2)'
    },
    orders: {
      data: @json($trendOrders),
      label: 'Total Orders',
      prefix: '',
      color: '#0EA5E9',
      bg: 'rgba(14, 165, 233, 0.2)'
    },
    aov: {
      data: @json($trendAov),
      label: 'Average Order Value',
      prefix: 'A$ ',
      color: '#10B981',
      bg: 'rgba(16, 185, 129, 0.2)'
    },
    profit: {
      data: @json($trendProfit),
      label: 'Estimated Gross Profit',
      prefix: 'A$ ',
      color: '#8B5CF6',
      bg: 'rgba(139, 92, 246, 0.2)'
    }
  };

  let trendChartInstance = null;
  let donutChartInstance = null;
  let currentMetricKey = 'revenue';

  function initTrendChart() {
    const canvas = document.getElementById('revenueTrendChartCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const metric = trendMetrics[currentMetricKey];

    // Create vertical gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, metric.bg);
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

    if (trendChartInstance) {
      trendChartInstance.destroy();
    }

    trendChartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels: trendLabels,
        datasets: [{
          label: metric.label,
          data: metric.data,
          borderColor: metric.color,
          backgroundColor: gradient,
          fill: true,
          tension: 0.42,
          borderWidth: 2.5,
          pointBackgroundColor: metric.color,
          pointBorderColor: '#141A29',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#1E293B',
            titleColor: '#F8FAFC',
            bodyColor: '#F8FAFC',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            padding: 10,
            callbacks: {
              label: function(context) {
                const val = context.parsed.y;
                return metric.label + ': ' + (metric.prefix ? metric.prefix : '') + Number(val).toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            grid: { color: 'rgba(255, 255, 255, 0.05)', borderColor: 'transparent' },
            ticks: { color: '#94A3B8', font: { size: 11 } }
          },
          y: {
            grid: { color: 'rgba(255, 255, 255, 0.05)', borderColor: 'transparent' },
            ticks: {
              color: '#94A3B8',
              font: { size: 11 },
              callback: function(val) {
                return (metric.prefix ? metric.prefix : '') + val;
              }
            }
          }
        }
      }
    });
  }

  window.switchTrendMetric = function(metricKey, btnElement) {
    if (!trendMetrics[metricKey]) return;
    currentMetricKey = metricKey;

    document.querySelectorAll('#trendTogglePills .dash-pill-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    if (btnElement) {
      btnElement.classList.add('active');
    }

    initTrendChart();
  };

  function initDonutChart() {
    const canvas = document.getElementById('fulfilmentDonutCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const deliveryCount = {{ $deliveryOrdersCount }};
    const pickupCount = {{ $pickupOrdersCount }};
    const total = {{ $totalOrdersCount }};

    const dataValues = total > 0 ? [deliveryCount, pickupCount] : [1, 1];
    const bgColors = total > 0 ? ['#FF6B6B', '#F59E0B'] : ['rgba(255,255,255,0.1)', 'rgba(255,255,255,0.05)'];

    if (donutChartInstance) {
      donutChartInstance.destroy();
    }

    donutChartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Delivery', 'Pickup'],
        datasets: [{
          data: dataValues,
          backgroundColor: bgColors,
          borderWidth: 0,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '76%',
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: total > 0,
            callbacks: {
              label: function(context) {
                const count = context.raw;
                const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                return context.label + ': ' + count + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

  // Date Filter Dropdown Interactions
  window.toggleDateDropdown = function() {
    const dropdown = document.getElementById('dashDateDropdown');
    if (dropdown) {
      dropdown.classList.toggle('show');
    }
  };

  window.selectDateChip = function(dateStr) {
    window.location.href = "{{ route('dashboard') }}?date=" + dateStr;
  };

  window.applyCustomDate = function() {
    const input = document.getElementById('dashNativeDateInput');
    if (input && input.value) {
      window.location.href = "{{ route('dashboard') }}?date=" + input.value;
    }
  };

  // Close dropdown on click outside
  document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.dash-date-picker-wrap');
    const dropdown = document.getElementById('dashDateDropdown');
    if (wrap && dropdown && !wrap.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });

  // Initialize on load
  document.addEventListener('DOMContentLoaded', function() {
    initTrendChart();
    initDonutChart();
  });
})();
</script>
@endsection
