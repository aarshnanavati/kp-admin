@extends('layouts.app')

@section('title', 'Payment History')
@section('current_page', 'payments')
@section('page_title', 'Payment History')
@section('page_subtitle', 'Review customer payment transactions.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="paymentsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Payment History</h2>
      <p class="kp_kitchen_admin_panel_section_text">Review customer payment transactions and weekly billing history.</p>
    </div>
  </div>

  <div class="kp_kitchen_admin_panel_stats_grid kp_kitchen_admin_panel_stats_grid_three">
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon">✓</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Successful</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentSuccessful">{{ $successfulCount }}</strong>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon">!</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Failed</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentFailed">{{ $failedCount }}</strong>
      </div>
    </article>
    <article class="kp_kitchen_admin_panel_stat_card">
      <div class="kp_kitchen_admin_panel_stat_icon bg-info-soft" style="color: var(--info-color); font-size: 1.5rem;">$</div>
      <div class="kp_kitchen_admin_panel_stat_content">
        <span class="kp_kitchen_admin_panel_stat_label">Amount Received</span>
        <strong class="kp_kitchen_admin_panel_stat_value" id="paymentTotal">${{ number_format($totalAmount, 2) }}</strong>
      </div>
    </article>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_card_header">
      <div>
        <h3 class="kp_kitchen_admin_panel_card_title">Transaction Ledger</h3>
        <p class="kp_kitchen_admin_panel_card_subtitle">Payments processed for weekly customer balances</p>
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
        <tbody class="kp_kitchen_admin_panel_table_body" id="paymentsTableBody">
          @forelse ($payments as $payment)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong class="kp_kitchen_admin_panel_table_primary">{{ $payment->id }}</strong>
                @if ($payment->order_id)
                  <span class="kp_kitchen_admin_panel_table_secondary" style="font-size: 0.75rem; opacity: 0.75; display: block; margin-top: 4px;">
                    Order: <strong>{{ $payment->order_id }}</strong>
                  </span>
                @endif
                @if ($payment->payment_intent_id)
                  <span class="kp_kitchen_admin_panel_table_secondary" style="font-size: 0.70rem; opacity: 0.6; display: block; font-family: monospace;">
                    Intent: {{ $payment->payment_intent_id }}
                  </span>
                @endif
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong>{{ $payment->customer }}</strong>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $payment->plan }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>${{ number_format($payment->amount, 2) }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $payment->date }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $payment->status)) }}">{{ $payment->status }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_empty_state">No payment deductions processed yet.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
