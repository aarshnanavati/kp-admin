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
    <div class="kp_kitchen_admin_panel_filter_group">
      <form id="invoiceFilterForm" method="GET" action="{{ route('invoices') }}" style="display: flex; gap: 8px; align-items: center; flex-wrap: nowrap;">
        <div style="position: relative; display: flex; align-items: center;">
          <input type="text" class="kp_kitchen_admin_panel_form_input" name="search" value="{{ request('search') }}" placeholder="Search invoices..." style="padding-left: 32px; width: 220px; padding-top: 6px; padding-bottom: 6px;" id="invoiceSearchInput">
          <svg style="position: absolute; left: 10px; width: 14px; height: 14px; fill: var(--text-secondary);" viewBox="0 0 24 24">
            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
          </svg>
        </div>
        <input type="date" class="kp_kitchen_admin_panel_form_input" name="start_date" value="{{ request('start_date') }}" onchange="this.form.submit()" title="Start Date" style="max-width: 140px; padding: 6px 10px;">
        <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
        <input type="date" class="kp_kitchen_admin_panel_form_input" name="end_date" value="{{ request('end_date') }}" onchange="this.form.submit()" title="End Date" style="max-width: 140px; padding: 6px 10px;">
        
        <select class="kp_kitchen_admin_panel_form_select" name="status" onchange="this.form.submit()" style="max-width: 130px; padding: 6px 10px;">
          <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All statuses</option>
          <option value="Paid" {{ request('status') == 'Paid' ? 'selected' : '' }}>Paid</option>
          <option value="Unpaid" {{ request('status') == 'Unpaid' ? 'selected' : '' }}>Unpaid</option>
          <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
        </select>
      </form>
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
        <tbody class="kp_kitchen_admin_panel_table_body" id="invoicesTableBody">
          @forelse ($invoices as $invoice)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell"><strong class="kp_kitchen_admin_panel_table_primary">{{ $invoice->id }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $invoice->customer->name ?? 'Unassigned' }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $invoice->order_id }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>${{ number_format($invoice->amount, 2) }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $invoice->due_date }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $invoice->status)) }}">{{ $invoice->status }}</span>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_table_actions">
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-invoice-btn"
                    data-id="{{ $invoice->id }}"
                    data-customer_id="{{ $invoice->customer_id }}"
                    data-amount="{{ $invoice->amount }}"
                    data-due_date="{{ $invoice->due_date }}"
                    data-status="{{ $invoice->status }}"
                    data-collected_photo="{{ $invoice->collected_photo ? asset($invoice->collected_photo) : '' }}">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('invoices.delete', $invoice->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this invoice?');">
                    @csrf
                    <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No invoices found matching filters.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('invoiceSearchInput');
    let timeout = null;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            document.getElementById('invoiceFilterForm').submit();
        }, 300);
    });

    if (searchInput.value) {
        searchInput.focus();
        const val = searchInput.value;
        searchInput.value = '';
        searchInput.value = val;
    }
});
</script>
@endsection
