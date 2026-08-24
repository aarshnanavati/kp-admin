@extends('layouts.app')

@section('title', 'Business Overview')
@section('current_page', 'dashboard')
@section('page_title', 'Business Overview')
@section('page_subtitle', 'Real-time analytics and management for Australia operations.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="dashboardPage">
  <!-- Key Metrics Stats Grid -->
  <div class="kp_kitchen_admin_panel_stats_grid">
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-primary-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF6B6B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Total Orders</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statOrders">{{ $ordersCount }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">Today's bookings</span>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-success-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ECC71" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><line x1="16" y1="8" x2="20" y2="8"/><line x1="16" y1="12" x2="23" y2="12"/><line x1="1" y1="16" x2="23" y2="16"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Active Drivers</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="statDrivers">{{ $driversCount }}</strong>
        <span class="kp_kitchen_admin_panel_stat_hint">Assigned today</span>
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
        <span class="kp_kitchen_admin_panel_stat_hint">Collected today</span>
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
          <h3 class="kp_kitchen_admin_panel_card_title">Top Ordered Postcodes</h3>
          <p class="kp_kitchen_admin_panel_card_subtitle">Most popular delivery areas in the past 7 days</p>
        </div>
      </div>
      <div class="kp_kitchen_admin_panel_chart_container">
        <canvas id="itemsChartCanvas"></canvas>
      </div>
    </article>
  </div>


</section>
@endsection
