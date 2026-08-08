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
