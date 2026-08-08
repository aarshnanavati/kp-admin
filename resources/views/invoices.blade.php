@extends('layouts.app')

@section('title', 'Invoices')
@section('current_page', 'invoices')
@section('page_title', 'Invoice Management')
@section('page_subtitle', 'Review customer order billing and payment collection status.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="invoicesPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Invoice Management</h2>
      <p class="kp_kitchen_admin_panel_section_text">Review customer billing records.</p>
    </div>
    <div class="kp_kitchen_admin_panel_filter_group" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <input type="date" class="kp_kitchen_admin_panel_form_input" id="invoiceStartDateFilter" title="Start Date" style="max-width: 140px; padding: 6px 10px;">
      <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
      <input type="date" class="kp_kitchen_admin_panel_form_input" id="invoiceEndDateFilter" title="End Date" style="max-width: 140px; padding: 6px 10px;">
      <select class="kp_kitchen_admin_panel_form_select" id="invoiceStatusFilter" style="max-width: 130px; padding: 6px 10px;">
        <option value="all">All statuses</option>
        <option value="Paid">Paid</option>
        <option value="Unpaid">Unpaid</option>
        <option value="Pending">Pending</option>
      </select>
    </div>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Invoice ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
            <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
            <th class="kp_kitchen_admin_panel_table_heading">Due Date</th>
            <th class="kp_kitchen_admin_panel_table_heading">Status</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="invoicesTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
