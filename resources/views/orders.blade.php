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
    <div class="kp_kitchen_admin_panel_filter_group">
      <form id="orderFilterForm" method="GET" action="{{ route('orders') }}" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <input type="date" class="kp_kitchen_admin_panel_form_input" name="start_date" value="{{ request('start_date') }}" onchange="this.form.submit()" title="Start Date" style="max-width: 140px; padding: 6px 10px;">
        <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
        <input type="date" class="kp_kitchen_admin_panel_form_input" name="end_date" value="{{ request('end_date') }}" onchange="this.form.submit()" title="End Date" style="max-width: 140px; padding: 6px 10px;">
        
        <select class="kp_kitchen_admin_panel_form_select" name="area" onchange="this.form.submit()" style="max-width: 130px; padding: 6px 10px;">
          <option value="all" {{ request('area', 'all') == 'all' ? 'selected' : '' }}>All areas</option>
          @foreach ($uniqueAreas as $area)
            <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}</option>
          @endforeach
        </select>

        <select class="kp_kitchen_admin_panel_form_select" name="status" onchange="this.form.submit()" style="max-width: 130px; padding: 6px 10px;">
          <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All statuses</option>
          @foreach (['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'] as $st)
            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
          @endforeach
        </select>
      </form>
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
        <tbody class="kp_kitchen_admin_panel_table_body" id="ordersTableBody">
          @forelse ($orders as $order)
            @php
              $matchingDrivers = $drivers->filter(function($d) use ($order) {
                  return $d->status === 'Active' && trim(strtolower($d->assigned_zip)) === trim(strtolower($order->area));
              });
              $selectedDriverValid = $matchingDrivers->contains('name', $order->driver);
              $noDriver = $matchingDrivers->isEmpty();
            @endphp
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong class="kp_kitchen_admin_panel_table_primary">{{ $order->id }}</strong>
                <span class="kp_kitchen_admin_panel_table_secondary">{{ $order->date }}</span>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong>{{ $order->customer }}</strong>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong>{{ $order->tiffin }}</strong>
                <div class="inline-badge-list">
                  @php
                    $addons = json_decode($order->add_ons, true);
                  @endphp
                  @if (is_array($addons))
                    @foreach ($addons as $addon)
                      <span class="kp_kitchen_admin_panel_tiffin_item_chip">{{ $addon['name'] }} (x{{ $addon['qty'] }})</span>
                    @endforeach
                  @endif
                </div>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $order->area }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell" style="width:250px;">
                <form method="POST" action="{{ route('orders.update-status') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $order->id }}">
                  <input type="hidden" name="status" value="{{ $order->status }}">
                  <select name="driver_id" class="kp_kitchen_admin_panel_inline_select {{ $noDriver ? 'kp_kitchen_admin_panel_inline_select_warning' : '' }}" onchange="this.form.submit()" {{ $noDriver ? 'disabled' : '' }}>
                    <option value="" {{ !$selectedDriverValid ? 'selected' : '' }}>
                      {{ $noDriver ? "No active driver for postcode " . $order->area : "Unassigned" }}
                    </option>
                    @foreach ($matchingDrivers as $driver)
                      <option value="{{ $driver->id }}" {{ $driver->name === $order->driver ? 'selected' : '' }}>
                        {{ $driver->name }} — Postcode {{ $driver->assigned_zip }}
                      </option>
                    @endforeach
                  </select>
                </form>
                <span class="kp_kitchen_admin_panel_assignment_hint">{{ $noDriver ? 'Assign this postcode area to a driver first.' : 'Postcode-matched drivers only' }}</span>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>${{ number_format($order->amount, 2) }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <form method="POST" action="{{ route('orders.update-status') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $order->id }}">
                  <input type="hidden" name="driver_id" value="{{ $order->driver_id }}">
                  <select name="status" class="kp_kitchen_admin_panel_inline_select" onchange="this.form.submit()">
                    @foreach (['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'] as $status)
                      <option value="{{ $status }}" {{ $status === $order->status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                  </select>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_empty_state">No orders found matching filters.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
