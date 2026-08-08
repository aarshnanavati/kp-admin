@extends('layouts.app')

@section('title', 'Coupons')
@section('current_page', 'coupons')
@section('page_title', 'Coupons & Discounts')
@section('page_subtitle', 'Manage discount promo codes and discount models.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="couponsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Coupons &amp; Discounts</h2>
      <p class="kp_kitchen_admin_panel_section_text">Create promotional and customer discount codes.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addCouponButton">+ Add Coupon</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Code</th>
            <th class="kp_kitchen_admin_panel_table_heading">Type</th>
            <th class="kp_kitchen_admin_panel_table_heading">Discount Value</th>
            <th class="kp_kitchen_admin_panel_table_heading">Expiry Date</th>
            <th class="kp_kitchen_admin_panel_table_heading">Status</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="couponsTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
