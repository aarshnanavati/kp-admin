    @extends('layouts.app')

@section('title', 'Customer Orders')
@section('current_page', 'orders')
@section('page_title', 'Customer Orders')
@section('page_subtitle')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="ordersPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Customer Orders</h2>
      {{-- <p class="kp_kitchen_admin_panel_section_text">Orders received from your mobile app.</p> --}}
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
          <input type="text" name="search" value="{{ request('search') }}" class="kp_kitchen_admin_panel_form_input" placeholder="Search orders..." style="padding: 6px 12px 6px 32px; font-size: 0.85rem; min-width: 180px; max-height: 38px; margin-bottom: 0;">
        </div>

        @if ($showPrevious)
          <input type="date" class="kp_kitchen_admin_panel_form_input" name="start_date" value="{{ request('start_date') }}" onchange="this.form.submit()" title="Start Date" style="max-width: 130px; padding: 6px 10px; margin-bottom: 0;">
          <span style="font-size: 0.8rem; color: var(--text-secondary);">to</span>
          <input type="date" class="kp_kitchen_admin_panel_form_input" name="end_date" value="{{ request('end_date') }}" onchange="this.form.submit()" title="End Date" style="max-width: 130px; padding: 6px 10px; margin-bottom: 0;">
        @endif

        <select class="kp_kitchen_admin_panel_form_select" name="area" onchange="this.form.submit()" style="min-width: 120px; padding: 6px 10px; margin-bottom: 0;">
          <option value="all" {{ request('area', 'all') == 'all' ? 'selected' : '' }}>All areas</option>
          @foreach ($uniqueAreas as $area)
            <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}</option>
          @endforeach
        </select>

        <select class="kp_kitchen_admin_panel_form_select" name="driver" onchange="this.form.submit()" style="min-width: 140px; padding: 6px 10px; margin-bottom: 0;">
          <option value="all" {{ request('driver', 'all') == 'all' ? 'selected' : '' }}>Filter: All drivers</option>
          <option value="unassigned" {{ request('driver') == 'unassigned' ? 'selected' : '' }}>Filter: Unassigned only</option>
          @foreach ($drivers as $d)
            <option value="{{ $d->id }}" {{ (string)request('driver') === (string)$d->id || request('driver') === $d->name ? 'selected' : '' }}>
              Filter: {{ $d->name }}
            </option>
          @endforeach
        </select>

        <select id="bulkDriverSelect" class="kp_kitchen_admin_panel_form_select" style="min-width: 165px; padding: 6px 10px; margin-bottom: 0; background: rgba(52, 152, 219, 0.08); border-color: rgba(52, 152, 219, 0.35); color: #2980b9; font-weight: 600; cursor: pointer;">
          <option value="" disabled selected>🚚 Assign Driver...</option>
          <option value="Unassigned" data-driver-name="Unassigned">Unassigned (Clear Driver)</option>
          @foreach ($drivers->where('status', 'Active') as $d)
            <option value="{{ $d->name }}" data-driver-name="{{ $d->name }}">Assign: {{ $d->name }}</option>
          @endforeach
        </select>

        @if ($showPrevious)
          <a href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 0])) }}" class="kp_kitchen_admin_panel_secondary_button" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; white-space: nowrap; height: 38px; box-sizing: border-box; line-height: 20px; margin-bottom: 0;">
            📅 Show Today Only
          </a>
        @else
          <a href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 1])) }}" class="kp_kitchen_admin_panel_primary_button" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.85rem; white-space: nowrap; height: 38px; box-sizing: border-box; line-height: 20px; margin-bottom: 0;">
            🕒 Previous Orders
          </a>
        @endif

        <button type="button" id="dispatchOrdersBtn" class="kp_kitchen_admin_panel_primary_button" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; font-size: 0.85rem; font-weight: 600; white-space: nowrap; height: 38px; box-sizing: border-box; line-height: 20px; background: linear-gradient(135deg, #27ae60, #2ecc71); border-color: #27ae60; box-shadow: 0 4px 12px rgba(46, 204, 113, 0.25); margin-bottom: 0; cursor: pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
          Orders Ready for Dispatch
        </button>
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
              $activeDrivers = $drivers->where('status', 'Active');
              $isAssigned = ($order->driver && $order->driver !== 'Unassigned');
            @endphp
            <tr class="kp_kitchen_admin_panel_table_row" data-order-id="{{ $order->id }}">
              <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center; vertical-align: middle;">
                <input type="checkbox" class="order-batch-checkbox"
                       data-order-id="{{ $order->id }}"
                       {{ $isAssigned ? 'checked' : '' }}
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
                @php
                  $selections = is_array($order->selections) ? $order->selections : json_decode($order->selections, true);
                  $customItems = $selections['custom_items'] ?? [];
                  $choices = $selections['choices'] ?? [];
                  $summary = $selections['summary'] ?? '';
                  $addons = is_array($order->add_ons) ? $order->add_ons : json_decode($order->add_ons, true);
                @endphp

                @if (!empty($customItems))
                  <div class="inline-badge-list" style="margin-top: 4px;">
                    @foreach ($customItems as $cItem)
                      <span class="kp_kitchen_admin_panel_tiffin_item_chip" style="background: rgba(46, 204, 113, 0.15); color: #27ae60; border: 1px solid rgba(46, 204, 113, 0.3);">
                        {{ $cItem['name'] ?? $cItem }}
                      </span>
                    @endforeach
                  </div>
                @elseif (!empty($choices))
                  <div class="inline-badge-list" style="margin-top: 4px;">
                    @foreach ($choices as $choice)
                      <span class="kp_kitchen_admin_panel_tiffin_item_chip" style="background: rgba(52, 152, 219, 0.15); color: #2980b9; border: 1px solid rgba(52, 152, 219, 0.3);">
                        {{ $choice['component'] ?? '' }}: {{ $choice['chosen'] ?? '' }}
                      </span>
                    @endforeach
                  </div>
                @elseif (!empty($summary))
                  <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 3px;">
                    {{ $summary }}
                  </div>
                @endif

                @if (is_array($addons) && !empty($addons))
                  <div class="inline-badge-list" style="margin-top: 4px;">
                    @foreach ($addons as $addon)
                      <span class="kp_kitchen_admin_panel_tiffin_item_chip">{{ $addon['name'] }} (x{{ $addon['qty'] ?? 1 }})</span>
                    @endforeach
                  </div>
                @endif
              </td>
              <td class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">
                <strong>{{ $order->quantity ?? 1 }}</strong>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $order->area }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell" style="width:260px;">
                <select name="driver_id" class="kp_kitchen_admin_panel_inline_select order-driver-select">
                  <option value="" data-driver-name="Unassigned" {{ !$isAssigned ? 'selected' : '' }}>
                    Unassigned
                  </option>
                  @foreach ($activeDrivers as $driver)
                    <option value="{{ $driver->id }}" data-driver-name="{{ $driver->name }}" {{ $driver->name === $order->driver ? 'selected' : '' }}>
                      {{ $driver->name }}
                    </option>
                  @endforeach
                </select>
                <span class="kp_kitchen_admin_panel_assignment_hint">
                  @if ($order->driver && $order->driver !== 'Unassigned')
                    <span style="color: #2ecc71; font-weight: 600;">✓ Assigned: {{ $order->driver }}</span>
                  @else
                    Select any available driver
                  @endif
                </span>
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

<!-- Driver Assignment Confirmation Modal -->
<div id="driverAssignModal" class="kp_kitchen_admin_panel_modal">
  <div class="kp_kitchen_admin_panel_modal_dialog" style="width: 580px; max-width: 95vw;">
    <div class="kp_kitchen_admin_panel_modal_header">
      <h3 id="driverAssignModalTitle" class="kp_kitchen_admin_panel_modal_title">Driver Assignment Confirmation</h3>
      <button type="button" id="driverAssignModalClose" class="kp_kitchen_admin_panel_modal_close">×</button>
    </div>
    <div class="kp_kitchen_admin_panel_modal_content_body">
      <p id="driverAssignModalPrompt" style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
        Do you want to continue with the selected drivers?
      </p>
      <p id="driverAssignModalSubtext" style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px;">
        Please review the driver assignment details below before proceeding:
      </p>
      <div id="driverAssignModalTableWrap" style="max-height: 240px; overflow-y: auto; border: 1px solid var(--panel-border); border-radius: 10px; background: rgba(0, 0, 0, 0.15); margin-bottom: 16px;">
        <table class="kp_kitchen_admin_panel_table" style="font-size: 0.85rem; margin: 0; width: 100%;">
          <thead class="kp_kitchen_admin_panel_table_head">
            <tr class="kp_kitchen_admin_panel_table_row">
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Order ID</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Customer</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Area</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Assigned Driver</th>
            </tr>
          </thead>
          <tbody id="driverAssignModalList" class="kp_kitchen_admin_panel_table_body">
            <!-- Dynamic order rows inserted via JS -->
          </tbody>
        </table>
      </div>
      <div class="kp_kitchen_admin_panel_modal_actions">
        <button type="button" id="driverAssignCancelBtn" class="kp_kitchen_admin_panel_secondary_button">Cancel</button>
        <button type="button" id="driverAssignConfirmBtn" class="kp_kitchen_admin_panel_primary_button" style="display: inline-flex; align-items: center; gap: 8px;">Assign Drivers</button>
      </div>
    </div>
  </div>
</div>

<!-- Ready for Dispatch Confirmation Modal -->
<div id="dispatchModal" class="kp_kitchen_admin_panel_modal">
  <div class="kp_kitchen_admin_panel_modal_dialog" style="width: 600px; max-width: 95vw;">
    <div class="kp_kitchen_admin_panel_modal_header">
      <h3 id="dispatchModalTitle" class="kp_kitchen_admin_panel_modal_title">🚀 Ready for Dispatch Confirmation</h3>
      <button type="button" id="dispatchModalClose" class="kp_kitchen_admin_panel_modal_close">×</button>
    </div>
    <div class="kp_kitchen_admin_panel_modal_content_body">
      <p id="dispatchModalPrompt" style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
        Are you ready to dispatch the assigned orders now?
      </p>
      <p id="dispatchModalSubtext" style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px;">
        Clicking confirm will mark the orders as <strong>Out for Delivery</strong> and send instant pickup &amp; delivery notifications to all assigned drivers.
      </p>
      <div id="dispatchModalTableWrap" style="max-height: 240px; overflow-y: auto; border: 1px solid var(--panel-border); border-radius: 10px; background: rgba(0, 0, 0, 0.15); margin-bottom: 16px;">
        <table class="kp_kitchen_admin_panel_table" style="font-size: 0.85rem; margin: 0; width: 100%;">
          <thead class="kp_kitchen_admin_panel_table_head">
            <tr class="kp_kitchen_admin_panel_table_row">
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Order ID</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Customer</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Area</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="padding: 8px 12px;">Assigned Driver</th>
            </tr>
          </thead>
          <tbody id="dispatchModalList" class="kp_kitchen_admin_panel_table_body">
            <!-- Dynamic rows inserted via JS -->
          </tbody>
        </table>
      </div>
      <div class="kp_kitchen_admin_panel_modal_actions">
        <button type="button" id="dispatchCancelBtn" class="kp_kitchen_admin_panel_secondary_button">Cancel</button>
        <button type="button" id="dispatchConfirmBtn" class="kp_kitchen_admin_panel_primary_button" style="background: linear-gradient(135deg, #27ae60, #2ecc71); border-color: #27ae60; display: inline-flex; align-items: center; gap: 8px;">
          🚀 Dispatch &amp; Notify Drivers
        </button>
      </div>
    </div>
  </div>
</div>
</section>
@endsection
