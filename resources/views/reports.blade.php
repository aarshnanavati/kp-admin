@extends('layouts.app')

@section('title', 'Reports')
@section('current_page', 'reports')
@section('page_title', 'Business Reports')
@section('page_subtitle', 'Export sales, driver logs, and customer metrics.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="reportsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Export Reports</h2>
      <p class="kp_kitchen_admin_panel_section_text">Generate spreadsheets (CSV) for kitchen operations and audits.</p>
    </div>
  </div>

  <div class="kp_kitchen_admin_panel_stats_grid kp_kitchen_admin_panel_stats_grid_three">
    <article class="kp_kitchen_admin_panel_stat_card hover-card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-primary-soft">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF6B6B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      </div>
      <div class="kp_kitchen_admin_panel_stat_content" style="flex:1;">
        <span class="kp_kitchen_admin_panel_stat_label">Sales &amp; Orders Log</span>
        <p class="kp_kitchen_admin_panel_card_subtitle" style="margin: 0.25rem 0 1rem 0;">Download detailed list of orders, plans, and add-ons.</p>
        <a href="{{ url('/api/reports/export?type=sales') }}" class="kp_kitchen_admin_panel_primary_button" style="text-decoration:none; display:inline-block; text-align:center;">Export CSV</a>
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
@endsection
