@extends('layouts.app')

@section('title', 'Business Overview')
@section('current_page', 'dashboard')
@section('page_title', 'Business Overview')
@section('page_subtitle', 'Real-time analytics and management for Australia operations.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="dashboardPage">
  <!-- Welcome Banner -->
  <div class="kp_kitchen_admin_panel_welcome_banner">
    <div class="kp_kitchen_admin_panel_welcome_content">
      <span class="kp_kitchen_admin_panel_welcome_tag">Today’s overview</span>
      <h2 class="kp_kitchen_admin_panel_welcome_title">
        Keep your kitchen and deliveries running smoothly.
      </h2>
      <p class="kp_kitchen_admin_panel_welcome_text">
        Review daily orders, dispatch drivers, track analytics, and manage customer tiffin plans.
      </p>
    </div>
    <a class="kp_kitchen_admin_panel_secondary_button" href="{{ route('orders') }}">Dispatch Center</a>
  </div>

  <!-- Key Metrics Stats Grid -->
  <div class="kp_kitchen_admin_panel_stats_grid">
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-primary-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF6B6B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Total Orders</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statOrders">{{ $ordersCount }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">All-time bookings</span>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-success-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ECC71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><line x1="16" y1="8" x2="20" y2="8"/><line x1="16" y1="12" x2="23" y2="12"/><line x1="1" y1="16" x2="23" y2="16"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Active Drivers</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statDrivers">{{ $driversCount }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">Onduty in postcodes</span>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-warning-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#F1C40F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Tiffin Plans</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statTiffins">{{ $tiffinsCount }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">Active menu designs</span>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-info-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3498DB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Revenue</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statRevenue">${{ number_format($totalRevenue, 2) }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">Total AUD collected</span>
      </div>
    </article>
  </div>

  <!-- Business Analytics (Charts) -->
  <div class="kp_kitchen_admin_panel_analytics_grid">
    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_card_header">
        <div>
          <h3 class="kp_kitchen_admin_panel_card_title">Order Volume (Past 7 Days)</h3>
          <p class="kp_kitchen_admin_panel_card_subtitle">Daily order activity trends in Australia</p>
        </div>
      </div>
      <div class="kp_kitchen_admin_panel_chart_container">
        <canvas id="ordersChartCanvas"></canvas>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_card_header">
        <div>
          <h3 class="kp_kitchen_admin_panel_card_title">Top Ordered Items (Past 7 Days)</h3>
          <p class="kp_kitchen_admin_panel_card_subtitle">Most frequently purchased items and plan bundles</p>
        </div>
      </div>
      <div class="kp_kitchen_admin_panel_chart_container">
        <canvas id="itemsChartCanvas"></canvas>
      </div>
    </article>
  </div>

  <!-- Dashboard Secondary Data Row -->
  <div class="kp_kitchen_admin_panel_dashboard_grid">
    <!-- Recent Orders Card -->
    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_card_header">
        <div>
          <h3 class="kp_kitchen_admin_panel_card_title">Recent Orders</h3>
          <p class="kp_kitchen_admin_panel_card_subtitle">Latest orders received from customer portals</p>
        </div>
        <a class="kp_kitchen_admin_panel_text_button" href="{{ route('orders') }}">View all</a>
      </div>
      <div class="kp_kitchen_admin_panel_table_wrap">
        <table class="kp_kitchen_admin_panel_table">
          <thead class="kp_kitchen_admin_panel_table_head">
            <tr class="kp_kitchen_admin_panel_table_row">
              <th class="kp_kitchen_admin_panel_table_heading">Order</th>
              <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
              <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
              <th class="kp_kitchen_admin_panel_table_heading">Status</th>
            </tr>
          </thead>
          <tbody class="kp_kitchen_admin_panel_table_body" id="dashboardOrdersBody">
            @forelse ($recentOrders as $order)
              <tr class="kp_kitchen_admin_panel_table_row">
                <td class="kp_kitchen_admin_panel_table_cell">
                  <strong class="kp_kitchen_admin_panel_table_primary">{{ $order->id }}</strong>
                  <span class="kp_kitchen_admin_panel_table_secondary">{{ $order->date }}</span>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <strong>{{ $order->customer }}</strong>
                </td>
                <td class="kp_kitchen_admin_panel_table_cell"><strong>${{ number_format($order->amount, 2) }}</strong></td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $order->status)) }}">{{ $order->status }}</span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="kp_kitchen_admin_panel_table_cell">
                  <div class="kp_kitchen_admin_panel_empty_state">No orders received yet.</div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </article>

    <!-- Delivery Summary Progress list -->
    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_card_header">
        <div>
          <h3 class="kp_kitchen_admin_panel_card_title">Delivery Summary</h3>
          <p class="kp_kitchen_admin_panel_card_subtitle">Current active order fulfillment progress</p>
        </div>
      </div>
      <div class="kp_kitchen_admin_panel_progress_list" id="deliverySummary">
        @foreach ($deliverySummary as $item)
          <div class="kp_kitchen_admin_panel_progress_item">
            <div class="kp_kitchen_admin_panel_progress_header">
              <span class="kp_kitchen_admin_panel_progress_label">{{ $item['status'] }}</span>
              <strong class="kp_kitchen_admin_panel_progress_value">{{ $item['count'] }}</strong>
            </div>
            <div class="kp_kitchen_admin_panel_progress_track">
              <span class="kp_kitchen_admin_panel_progress_fill" style="width: {{ $item['percent'] }}%"></span>
            </div>
          </div>
        @endforeach
      </div>
    </article>
  </div>
</section>
@endsection
