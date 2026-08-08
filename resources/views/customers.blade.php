@extends('layouts.app')

@section('title', 'Customers')
@section('current_page', 'customers')
@section('page_title', 'Customer Management')
@section('page_subtitle', 'Review customer accounts, postcodes, and ordering histories.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="customersPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Customers</h2>
      <p class="kp_kitchen_admin_panel_section_text">Registered app customers in Australia.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addCustomerButton">+ Add Customer</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Customer ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Name</th>
            <th class="kp_kitchen_admin_panel_table_heading">Phone</th>
            <th class="kp_kitchen_admin_panel_table_heading">Email</th>
            <th class="kp_kitchen_admin_panel_table_heading">Default Postcode</th>
            <th class="kp_kitchen_admin_panel_table_heading">Default Address</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="customersTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
