@extends('layouts.app')

@section('title', 'Payment Deduction')
@section('current_page', 'payments')
@section('page_title', 'Payment Deduction')
@section('page_subtitle', 'Review and process automatic payment deductions.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="paymentsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Automatic Payment Deduction</h2>
      <p class="kp_kitchen_admin_panel_section_text">Configure and review recurring customer deductions.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="runDeductionButton">Run Deduction</button>
  </div>
  <div class="kp_kitchen_admin_panel_stats_grid kp_kitchen_admin_panel_stats_grid_three">
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon">✓</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Successful</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentSuccessful">0</strong>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon">!</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Failed</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentFailed">0</strong>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-info-soft" style="color: var(--info-color); font-size: 1.5rem;">$</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Amount Received</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentTotal">$0</strong>
      </div>
    </article>
  </div>
  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_card_header">
      <div>
        <h3 class="kp_kitchen_admin_panel_card_title">Deduction History</h3>
        <p class="kp_kitchen_admin_panel_card_subtitle">Recurring payments processed for customers</p>
      </div>
    </div>
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Transaction</th>
            <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
            <th class="kp_kitchen_admin_panel_table_heading">Plan</th>
            <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
            <th class="kp_kitchen_admin_panel_table_heading">Date</th>
            <th class="kp_kitchen_admin_panel_table_heading">Status</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="paymentsTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
