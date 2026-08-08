@extends('layouts.app')

@section('title', 'Customer Orders')
@section('current_page', 'orders')
@section('page_title', 'Customer Orders')
@section('page_subtitle', 'Manage orders received from your mobile app.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="ordersPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Customer Orders</h2>
      <p class="kp_kitchen_admin_panel_section_text">Orders received from your mobile app.</p>
    </div>
    <div class="kp_kitchen_admin_panel_filter_group" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <input type="date" class="kp_kitchen_admin_panel_form_input" id="orderStartDateFilter" title="Start Date" style="max-width: 140px; padding: 6px 10px;">
      <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
      <input type="date" class="kp_kitchen_admin_panel_form_input" id="orderEndDateFilter" title="End Date" style="max-width: 140px; padding: 6px 10px;">
      <select class="kp_kitchen_admin_panel_form_select" id="orderAreaFilter" style="max-width: 130px; padding: 6px 10px;">
        <option value="all">All areas</option>
      </select>
      <select class="kp_kitchen_admin_panel_form_select" id="orderStatusFilter" style="max-width: 130px; padding: 6px 10px;">
        <option value="all">All statuses</option>
        <option value="Pending">Pending</option>
        <option value="Confirmed">Confirmed</option>
        <option value="Preparing">Preparing</option>
        <option value="Out for Delivery">Out for Delivery</option>
        <option value="Delivered">Delivered</option>
        <option value="Cancelled">Cancelled</option>
      </select>
    </div>
  </div>
  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
            <th class="kp_kitchen_admin_panel_table_heading">Tiffin</th>
            <th class="kp_kitchen_admin_panel_table_heading">Area</th>
            <th class="kp_kitchen_admin_panel_table_heading">Driver</th>
            <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
            <th class="kp_kitchen_admin_panel_table_heading">Status</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="ordersTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
