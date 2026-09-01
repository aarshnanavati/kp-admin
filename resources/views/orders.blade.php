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
      <form id="orderFilterForm" method="GET" action="{{ route('orders') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: nowrap; margin: 0;">
        <input type="hidden" name="show_previous" value="{{ $showPrevious ? 1 : 0 }}">
        
        <div style="position: relative; display: flex; align-items: center; margin-bottom: 0;">
          <span style="position: absolute; left: 10px; color: var(--text-secondary); opacity: 0.7; pointer-events: none; display: flex; align-items: center; justify-content: center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </span>
          <input type="text" name="search" value="{{ request('search') }}" class="kp_kitchen_admin_panel_form_input" placeholder="Search orders..." style="padding: 6px 12px 6px 32px; font-size: 0.85rem; min-width: 200px; max-height: 38px; margin-bottom: 0;">
        </div>

        @if ($showPrevious)
          <input type="date" class="kp_kitchen_admin_panel_form_input" name="start_date" value="{{ request('start_date') }}" onchange="this.form.submit()" title="Start Date" style="max-width: 140px; padding: 6px 10px; margin-bottom: 0;">
          <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
          <input type="date" class="kp_kitchen_admin_panel_form_input" name="end_date" value="{{ request('end_date') }}" onchange="this.form.submit()" title="End Date" style="max-width: 140px; padding: 6px 10px; margin-bottom: 0;">
        @endif

        <select class="kp_kitchen_admin_panel_form_select" name="area" onchange="this.form.submit()" style="min-width: 130px; padding: 6px 10px; margin-bottom: 0;">
          <option value="all" {{ request('area', 'all') == 'all' ? 'selected' : '' }}>All areas</option>
          @foreach ($uniqueAreas as $area)
            <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}</option>
          @endforeach
        </select>


        @if ($showPrevious)
          <a href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 0])) }}" class="kp_kitchen_admin_panel_secondary_button" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; white-space: nowrap; height: 38px; box-sizing: border-box; line-height: 20px; margin-bottom: 0;">
            📅 Show Today Only
          </a>
        @else
          <a href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 1])) }}" class="kp_kitchen_admin_panel_primary_button" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; white-space: nowrap; height: 38px; box-sizing: border-box; line-height: 20px; margin-bottom: 0;">
            🕒 Click here to see previous orders
          </a>
        @endif
      </form>
    </div>
  </div>

  <div id="ordersListSection">
    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_table_wrap">
        <table class="kp_kitchen_admin_panel_table">
          <thead class="kp_kitchen_admin_panel_table_head">
            <tr class="kp_kitchen_admin_panel_table_row">
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 40px; text-align: center; vertical-align: middle;">
                <input type="checkbox" id="selectAllOrdersCheckbox" style="transform: scale(1.2); cursor: pointer;">
              </th>
              <th class="kp_kitchen_admin_panel_table_heading">Order ID</th>
              <th class="kp_kitchen_admin_panel_table_heading">Customer</th>
              <th class="kp_kitchen_admin_panel_table_heading">Tiffin</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="text-align: center;">Qty</th>
              <th class="kp_kitchen_admin_panel_table_heading">Area</th>
              <th class="kp_kitchen_admin_panel_table_heading">Driver</th>
              <th class="kp_kitchen_admin_panel_table_heading">Amount</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody class="kp_kitchen_admin_panel_table_body" id="ordersTableBody">
          @forelse ($orders as $order)
            @php
              $matchingDrivers = $drivers->filter(function($d) use ($order) {
                  $zips = array_map('trim', explode(',', strtolower($d->assigned_zip)));
                  return $d->status === 'Active' && in_array(trim(strtolower($order->area)), $zips);
              });
              $selectedDriverValid = $matchingDrivers->contains('name', $order->driver);
              $noDriver = $matchingDrivers->isEmpty();
            @endphp
            <tr class="kp_kitchen_admin_panel_table_row" data-order-id="{{ $order->id }}">
              <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center; vertical-align: middle;">
                <input type="checkbox" class="order-batch-checkbox" 
                       data-order-id="{{ $order->id }}"
                       {{ $order->driver !== 'Unassigned' && $selectedDriverValid ? 'checked' : '' }}
                       {{ $noDriver ? 'disabled' : '' }}
                       style="transform: scale(1.2); cursor: pointer;">
              </td>
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
              <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">
                <strong>{{ $order->quantity ?? 1 }}</strong>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $order->area }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell" style="width:250px;">
                <select name="driver_id" class="kp_kitchen_admin_panel_inline_select order-driver-select {{ $noDriver ? 'kp_kitchen_admin_panel_inline_select_warning' : '' }}" {{ $noDriver ? 'disabled' : '' }}>
                  <option value="" data-driver-name="Unassigned" {{ !$selectedDriverValid ? 'selected' : '' }}>
                    {{ $noDriver ? "No active driver for postcode " . $order->area : "Unassigned" }}
                  </option>
                  @foreach ($matchingDrivers as $driver)
                    <option value="{{ $driver->id }}" data-driver-name="{{ $driver->name }}" {{ $driver->name === $order->driver ? 'selected' : '' }}>
                      {{ $driver->name }} — Postcode {{ $driver->assigned_zip }}
                    </option>
                  @endforeach
                </select>
                <span class="kp_kitchen_admin_panel_assignment_hint">{{ $noDriver ? 'Assign this postcode area to a driver first.' : 'Postcode-matched drivers only' }}</span>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>${{ number_format($order->amount, 2) }}</strong></td>

              <td class="kp_kitchen_admin_panel_table_cell" style="text-align: right;">
                <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-order-details-btn"
                  style="background: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498DB; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                  title="Order Details"
                  data-id="{{ $order->id }}">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                  </svg>
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_empty_state">No orders found matching filters.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</div>

<!-- Order Details Grid Section (Hidden by Default) -->
<div id="orderDetailsGridSection" style="display: none;">
  <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div>
      <button id="backToOrdersListBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px; background: transparent; border: 1px solid var(--panel-border); color: var(--text-primary); cursor: pointer; border-radius: 6px;">
        ← Back to Orders
      </button>
    </div>
  </div>
  
  <div id="orderDetailsGridContent"></div>
</div>
</section>
@endsection
