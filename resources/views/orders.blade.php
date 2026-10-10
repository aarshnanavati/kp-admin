@extends('layouts.app')

@section('title', 'Customer Orders')

@section('current_page', 'orders')

@section('page_title', 'Customer Orders')

@section('page_subtitle', '')

@section('content')

<section
    class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active"
    id="ordersPage"
>

    {{-- =========================================================
         ORDERS TOOLBAR
    ========================================================== --}}
    <div class="kp_kitchen_admin_panel_orders_toolbar">

        {{-- Top Row: Heading, Delivery/Pickup Tabs, and Action Buttons --}}
        <div class="kp_kitchen_admin_panel_orders_top_row" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div class="kp_kitchen_admin_panel_orders_heading" style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <h2 class="kp_kitchen_admin_panel_orders_title" style="margin: 0;">
                    {{ $showPrevious ? 'Previous Customer Orders' : 'Customer Orders' }}
                </h2>

                {{-- Delivery / Pickup Tabs Switcher --}}
                <div class="kp_kitchen_admin_panel_order_type_switcher" style="display: inline-flex; background: rgba(255, 255, 255, 0.04); padding: 4px; border-radius: 10px; border: 1px solid var(--panel-border); gap: 4px;">
                    <a
                        href="{{ route('orders', array_merge(request()->query(), ['order_type' => 'delivery'])) }}"
                        class="kp_kitchen_admin_panel_order_type_tab {{ ($orderType ?? 'delivery') === 'delivery' ? 'kp_kitchen_admin_panel_order_type_tab_active' : '' }}"
                        style="display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: all 0.2s; {{ ($orderType ?? 'delivery') === 'delivery' ? 'background: #3498DB; color: #fff; box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);' : 'color: var(--text-secondary); background: transparent;' }}"
                    >
                        <span>🚚 To Be Delivered Orders</span>
                        <span style="background: {{ ($orderType ?? 'delivery') === 'delivery' ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.08)' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem;">{{ $deliveryCount ?? 0 }}</span>
                    </a>

                    <a
                        href="{{ route('orders', array_merge(request()->query(), ['order_type' => 'pickup'])) }}"
                        class="kp_kitchen_admin_panel_order_type_tab {{ ($orderType ?? 'delivery') === 'pickup' ? 'kp_kitchen_admin_panel_order_type_tab_active' : '' }}"
                        style="display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: all 0.2s; {{ ($orderType ?? 'delivery') === 'pickup' ? 'background: #E67E22; color: #fff; box-shadow: 0 2px 8px rgba(230, 126, 34, 0.3);' : 'color: var(--text-secondary); background: transparent;' }}"
                    >
                        <span>🛍️ Pickup Orders</span>
                        <span style="background: {{ ($orderType ?? 'delivery') === 'pickup' ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.08)' }}; padding: 2px 8px; border-radius: 12px; font-size: 0.78rem;">{{ $pickupCount ?? 0 }}</span>
                    </a>
                </div>
            </div>

            <div class="kp_kitchen_admin_panel_orders_top_actions">
                {{-- Previous / Today Button --}}
                @if ($showPrevious)
                    <a
                        href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 0])) }}"
                        class="kp_kitchen_admin_panel_secondary_button kp_kitchen_admin_panel_order_bar_btn"
                    >
                        <span></span>
                        <span>Show Today Only</span>
                    </a>
                @else
                    <a
                        href="{{ route('orders', array_merge(request()->query(), ['show_previous' => 1])) }}"
                        class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_order_bar_btn"
                    >
                        <span></span>
                        <span>Previous Orders</span>
                    </a>
                @endif

                {{-- Dispatch Button --}}
                <button
                    type="button"
                    id="dispatchOrdersBtn"
                    class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_order_bar_btn kp_kitchen_admin_panel_dispatch_btn"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        viewBox="0 0 24 24"
                    >
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    <span>Orders Ready for Dispatch</span>
                </button>
            </div>
        </div>

        {{-- Filters Row --}}
        <div class="kp_kitchen_admin_panel_orders_filters_row">
            <form
                id="orderFilterForm"
                method="GET"
                action="{{ route('orders') }}"
                class="kp_kitchen_admin_panel_orders_filters"
            >
                <input
                    type="hidden"
                    name="show_previous"
                    value="{{ $showPrevious ? 1 : 0 }}"
                >
                <input
                    type="hidden"
                    name="order_type"
                    value="{{ $orderType ?? 'delivery' }}"
                >

                {{-- Search --}}
                <div class="kp_kitchen_admin_panel_filter_search_wrap">
                    <span class="kp_kitchen_admin_panel_filter_search_icon">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            viewBox="0 0 24 24"
                        >
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="kp_kitchen_admin_panel_form_input kp_kitchen_admin_panel_orders_search"
                        placeholder="Search orders..."
                    >
                </div>

                {{-- Previous Order Date Filters --}}
                @if ($showPrevious)
                    <div class="kp_kitchen_admin_panel_date_range_wrap">
                        <input
                            type="date"
                            class="kp_kitchen_admin_panel_form_input kp_kitchen_admin_panel_orders_date"
                            name="start_date"
                            value="{{ request('start_date') }}"
                            onchange="this.form.submit()"
                            title="Start Date"
                        >
                        <span class="kp_kitchen_admin_panel_orders_date_separator">to</span>
                        <input
                            type="date"
                            class="kp_kitchen_admin_panel_form_input kp_kitchen_admin_panel_orders_date"
                            name="end_date"
                            value="{{ request('end_date') }}"
                            onchange="this.form.submit()"
                            title="End Date"
                        >
                    </div>
                @endif

                {{-- Area --}}
                <select
                    class="kp_kitchen_admin_panel_form_select kp_kitchen_admin_panel_filter_area"
                    name="area"
                    onchange="this.form.submit()"
                >
                    <option
                        value="all"
                        {{ request('area', 'all') == 'all' ? 'selected' : '' }}
                    >
                        All areas
                    </option>
                    @foreach ($uniqueAreas as $area)
                        <option
                            value="{{ $area }}"
                            {{ request('area') == $area ? 'selected' : '' }}
                        >
                            {{ $area }}
                        </option>
                    @endforeach
                </select>

                {{-- Driver Filter --}}
                <select
                    class="kp_kitchen_admin_panel_form_select kp_kitchen_admin_panel_filter_driver"
                    name="driver"
                    onchange="this.form.submit()"
                >
                    <option
                        value="all"
                        {{ request('driver', 'all') == 'all' ? 'selected' : '' }}
                    >
                        All drivers
                    </option>
                    <option
                        value="unassigned"
                        {{ request('driver') == 'unassigned' ? 'selected' : '' }}
                    >
                        Unassigned only
                    </option>
                    @foreach ($drivers as $d)
                        <option
                            value="{{ $d->id }}"
                            {{
                                (string) request('driver') === (string) $d->id
                                || request('driver') === $d->name
                                ? 'selected'
                                : ''
                            }}
                        >
                            {{ $d->name }}
                        </option>
                    @endforeach
                </select>

                {{-- Status Filter --}}
                <select
                    class="kp_kitchen_admin_panel_form_select kp_kitchen_admin_panel_filter_status"
                    name="status"
                    onchange="this.form.submit()"
                >
                    <option
                        value="all"
                        {{ request('status', 'all') == 'all' ? 'selected' : '' }}
                    >
                        All statuses
                    </option>
                    <option
                        value="Pending"
                        {{ request('status') == 'Pending' ? 'selected' : '' }}
                    >
                        Pending
                    </option>
                    <option
                        value="Out for Delivery"
                        {{ request('status') == 'Out for Delivery' ? 'selected' : '' }}
                    >
                        Out for Delivery
                    </option>
                    <option
                        value="Delivered"
                        {{ request('status') == 'Delivered' ? 'selected' : '' }}
                    >
                        Delivered
                    </option>
                    <option
                        value="Cancelled"
                        {{ request('status') == 'Cancelled' ? 'selected' : '' }}
                    >
                        Cancelled
                    </option>
                </select>

                {{-- Bulk Driver Assignment --}}
                <select
                    id="bulkDriverSelect"
                    class="kp_kitchen_admin_panel_form_select kp_kitchen_admin_panel_bulk_driver_select"
                >
                    <option value="" disabled selected>
                         Assign Driver...
                    </option>
                    <option
                        value="Unassigned"
                        data-driver-name="Unassigned"
                    >
                        Unassigned (Clear)
                    </option>
                    @foreach ($drivers->where('status', 'Active') as $d)
                        <option
                            value="{{ $d->name }}"
                            data-driver-name="{{ $d->name }}"
                        >
                            Assign: {{ $d->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

    </div>


    {{-- =========================================================
         ORDERS LIST
    ========================================================== --}}
    <div id="ordersListSection">

        <article class="kp_kitchen_admin_panel_card">

            <div class="kp_kitchen_admin_panel_table_wrap">

                <table class="kp_kitchen_admin_panel_table">

                    <thead class="kp_kitchen_admin_panel_table_head">

                        <tr class="kp_kitchen_admin_panel_table_row">

                            <th class="kp_kitchen_admin_panel_table_heading col-order-checkbox">
                                <input
                                    type="checkbox"
                                    id="selectAllOrdersCheckbox"
                                    class="kp_kitchen_admin_panel_order_checkbox"
                                >
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-id">
                                Order ID
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-customer">
                                Customer
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-tiffin">
                                Tiffin
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-qty">
                                Qty
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-area">
                                Area
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-driver">
                                Driver
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-status">
                                Status
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-amount">
                                Amount
                            </th>

                            <th class="kp_kitchen_admin_panel_table_heading col-order-actions">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        class="kp_kitchen_admin_panel_table_body"
                        id="ordersTableBody"
                    >

                        @forelse ($orders as $order)

                            @php

                                $activeDrivers = $drivers->where('status', 'Active');

                                $isAssigned = (
                                    $order->driver &&
                                    $order->driver !== 'Unassigned'
                                );

                            @endphp


                            <tr
                                class="kp_kitchen_admin_panel_table_row"
                                data-order-id="{{ $order->id }}"
                            >

                                {{-- Checkbox --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-checkbox">

                                    <input
                                        type="checkbox"
                                        class="order-batch-checkbox kp_kitchen_admin_panel_order_checkbox"
                                        data-order-id="{{ $order->id }}"
                                        {{ $isAssigned ? 'checked' : '' }}
                                    >

                                </td>


                                {{-- Order ID --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-id">

                                    <strong class="kp_kitchen_admin_panel_table_primary">
                                        {{ $order->id }}
                                    </strong>

                                    <span class="kp_kitchen_admin_panel_table_secondary">
                                        {{ $order->date }}
                                    </span>

                                    @if (($order->order_type ?? 'delivery') === 'pickup')
                                        <div style="margin-top: 4px;">
                                            <span
                                                class="kp_kitchen_admin_panel_tiffin_item_chip kp_kitchen_admin_panel_chip_orange"
                                                style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; padding: 2px 7px; font-weight: 600;"
                                            >
                                                🛍️ Pickup
                                            </span>
                                        </div>
                                    @endif

                                </td>


                                {{-- Customer --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-customer">

                                    <strong>
                                        {{ $order->customer }}
                                    </strong>

                                </td>


                                {{-- Tiffin --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-tiffin">

                                    <strong>
                                        {{ $order->tiffin }}
                                    </strong>


                                    @php

                                        $selections = is_array($order->selections)
                                            ? $order->selections
                                            : (
                                                $order->selections
                                                    ? json_decode($order->selections, true)
                                                    : []
                                            );

                                        $customItems = $selections['custom_items'] ?? [];

                                        $choices = $selections['choices'] ?? [];

                                        $summary = $selections['summary'] ?? '';

                                        $addons = is_array($order->add_ons)
                                            ? $order->add_ons
                                            : (
                                                $order->add_ons
                                                    ? json_decode($order->add_ons, true)
                                                    : []
                                            );

                                    @endphp


                                    {{-- Custom Items --}}
                                    @if (!empty($customItems))

                                        <div class="inline-badge-list">

                                            @foreach ($customItems as $cItem)
                                                @php
                                                    $cName = is_array($cItem) ? ($cItem['name'] ?? '') : (string)$cItem;
                                                    $cQty = is_array($cItem) ? (int)($cItem['qty'] ?? $cItem['quantity'] ?? $cItem['count'] ?? 1) : 1;
                                                    if ($cQty <= 1 && preg_match('/\(x?(\d+)(?:\s*pcs?)?\)/i', $cName, $mQty)) {
                                                        $cQty = (int)$mQty[1];
                                                    }
                                                @endphp
                                                <span
                                                    class="kp_kitchen_admin_panel_tiffin_item_chip kp_kitchen_admin_panel_chip_green"
                                                >
                                                    {{ $cName }}
                                                    @if($cQty > 1 && !str_contains($cName, '('))
                                                        (x{{ $cQty }})
                                                    @endif
                                                </span>

                                            @endforeach

                                        </div>

                                    {{-- Choices --}}
                                    @elseif (!empty($choices))

                                        <div class="inline-badge-list">

                                            @foreach ($choices as $choice)

                                                <span
                                                    class="kp_kitchen_admin_panel_tiffin_item_chip kp_kitchen_admin_panel_chip_blue"
                                                >
                                                    {{ $choice['component'] ?? '' }}:
                                                    {{ $choice['chosen'] ?? '' }}
                                                </span>

                                            @endforeach

                                        </div>

                                    {{-- Summary --}}
                                    @elseif (!empty($summary))

                                        <div class="kp_kitchen_admin_panel_order_summary">
                                            {{ $summary }}
                                        </div>

                                    @endif


                                    {{-- Addons --}}
                                    @if (is_array($addons) && !empty($addons))

                                        <div class="inline-badge-list">

                                            @foreach ($addons as $addon)

                                                <span class="kp_kitchen_admin_panel_tiffin_item_chip">

                                                    {{ $addon['name'] ?? '' }}

                                                    (x{{ $addon['qty'] ?? 1 }})

                                                </span>

                                            @endforeach

                                        </div>

                                    @endif

                                </td>


                                {{-- Quantity --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-qty">

                                    <strong>
                                        {{ $order->quantity ?? 1 }}
                                    </strong>

                                </td>


                                {{-- Area --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-area">

                                    <strong>
                                        {{ $order->area }}
                                    </strong>

                                </td>


                                {{-- Driver --}}
                                <td class="kp_kitchen_admin_panel_table_cell kp_kitchen_admin_panel_driver_cell col-order-driver">

                                    <select
                                        name="driver_id"
                                        class="kp_kitchen_admin_panel_inline_select order-driver-select"
                                    >

                                        <option
                                            value=""
                                            data-driver-name="Unassigned"
                                            {{ !$isAssigned ? 'selected' : '' }}
                                        >
                                            Unassigned
                                        </option>

                                        @foreach ($activeDrivers as $driver)

                                            <option
                                                value="{{ $driver->id }}"
                                                data-driver-name="{{ $driver->name }}"
                                                {{ $driver->name === $order->driver ? 'selected' : '' }}
                                            >
                                                {{ $driver->name }}
                                            </option>

                                        @endforeach

                                    </select>


                                    <span class="kp_kitchen_admin_panel_assignment_hint">

                                        @if ($order->driver && $order->driver !== 'Unassigned')

                                            <span class="kp_kitchen_admin_panel_assigned_text">
                                                ✓ Assigned: {{ $order->driver }}
                                            </span>

                                        @else

                                            Select any available driver

                                        @endif

                                    </span>

                                </td>


                                {{-- Status --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-status">

                                    @php

                                        $orderStatus = $order->status ?? 'Pending';

                                        $statusSlug = strtolower(
                                            str_replace(' ', '_', $orderStatus)
                                        );

                                    @endphp

                                    <span
                                        class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ $statusSlug }} order-status-badge"
                                    >
                                        {{ $orderStatus }}
                                    </span>

                                </td>


                                {{-- Amount --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-amount">

                                    <strong>
                                        ${{ number_format($order->amount, 2) }}
                                    </strong>
                                    @if((float)($order->delivery_fee ?? 0) > 0)
                                        <div style="font-size: 0.72rem; color: #e67e22; font-weight: 500; margin-top: 2px;">
                                            (incl. ${{ number_format($order->delivery_fee, 2) }} delivery)
                                        </div>
                                    @endif

                                </td>


                                {{-- Actions --}}
                                <td class="kp_kitchen_admin_panel_table_cell col-order-actions">

                                    <button
                                        type="button"
                                        class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-order-details-btn"
                                        title="Order Details"
                                        data-id="{{ $order->id }}"
                                    >

                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            width="14"
                                            height="14"
                                            fill="currentColor"
                                            viewBox="0 0 16 16"
                                        >

                                            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>

                                            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>

                                        </svg>

                                    </button>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="10"
                                    class="kp_kitchen_admin_panel_table_cell"
                                >

                                    <div class="kp_kitchen_admin_panel_empty_state">
                                        No orders found matching filters.
                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </article>

    </div>


    {{-- =========================================================
         ORDER DETAILS
    ========================================================== --}}
    <div
        id="orderDetailsGridSection"
        class="kp_kitchen_admin_panel_order_details_section"
        style="display: none;"
    >

        <div class="kp_kitchen_admin_panel_order_details_toolbar">

            <button
                type="button"
                id="backToOrdersListBtn"
                class="kp_kitchen_admin_panel_secondary_button kp_kitchen_admin_panel_back_orders_btn"
            >
                ← Back to Orders
            </button>

        </div>

        <div id="orderDetailsGridContent"></div>

    </div>


    {{-- =========================================================
         DRIVER ASSIGNMENT CONFIRMATION MODAL
    ========================================================== --}}
    <div
        id="driverAssignModal"
        class="kp_kitchen_admin_panel_modal"
    >

        <div
            class="kp_kitchen_admin_panel_modal_dialog kp_kitchen_admin_panel_orders_modal_dialog"
        >

            <div class="kp_kitchen_admin_panel_modal_header">

                <h3
                    id="driverAssignModalTitle"
                    class="kp_kitchen_admin_panel_modal_title"
                >
                    Driver Assignment Confirmation
                </h3>

                <button
                    type="button"
                    id="driverAssignModalClose"
                    class="kp_kitchen_admin_panel_modal_close"
                >
                    ×
                </button>

            </div>


            <div class="kp_kitchen_admin_panel_modal_content_body">

                <p
                    id="driverAssignModalPrompt"
                    class="kp_kitchen_admin_panel_modal_prompt"
                >
                    Do you want to continue with the selected drivers?
                </p>

                <p
                    id="driverAssignModalSubtext"
                    class="kp_kitchen_admin_panel_modal_subtext"
                >
                    Please review the driver assignment details below before proceeding:
                </p>


                <div
                    id="driverAssignModalTableWrap"
                    class="kp_kitchen_admin_panel_orders_modal_table_wrap"
                >

                    <table
                        class="kp_kitchen_admin_panel_table kp_kitchen_admin_panel_modal_table"
                    >

                        <thead class="kp_kitchen_admin_panel_table_head">

                            <tr class="kp_kitchen_admin_panel_table_row">

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Order ID
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Customer
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Area
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Assigned Driver
                                </th>

                            </tr>

                        </thead>

                        <tbody
                            id="driverAssignModalList"
                            class="kp_kitchen_admin_panel_table_body"
                        >
                            {{-- Dynamic order rows inserted via JS --}}
                        </tbody>

                    </table>

                </div>


                <div class="kp_kitchen_admin_panel_modal_actions">

                    <button
                        type="button"
                        id="driverAssignCancelBtn"
                        class="kp_kitchen_admin_panel_secondary_button"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        id="driverAssignConfirmBtn"
                        class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_modal_primary_btn"
                    >
                        Assign Drivers
                    </button>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         READY FOR DISPATCH CONFIRMATION MODAL
    ========================================================== --}}
    <div
        id="dispatchModal"
        class="kp_kitchen_admin_panel_modal"
    >

        <div
            class="kp_kitchen_admin_panel_modal_dialog kp_kitchen_admin_panel_orders_dispatch_dialog"
        >

            <div class="kp_kitchen_admin_panel_modal_header">

                <h3
                    id="dispatchModalTitle"
                    class="kp_kitchen_admin_panel_modal_title"
                >
                    🚀 Ready for Dispatch Confirmation
                </h3>

                <button
                    type="button"
                    id="dispatchModalClose"
                    class="kp_kitchen_admin_panel_modal_close"
                >
                    ×
                </button>

            </div>


            <div class="kp_kitchen_admin_panel_modal_content_body">

                <p
                    id="dispatchModalPrompt"
                    class="kp_kitchen_admin_panel_modal_prompt"
                >
                    Are you ready to dispatch the assigned orders now?
                </p>

                <p
                    id="dispatchModalSubtext"
                    class="kp_kitchen_admin_panel_modal_subtext"
                >
                    Clicking confirm will mark the orders as
                    <strong>Out for Delivery</strong>
                    and send instant pickup &amp; delivery notifications to all assigned drivers.
                </p>


                <div
                    id="dispatchModalTableWrap"
                    class="kp_kitchen_admin_panel_orders_modal_table_wrap"
                >

                    <table
                        class="kp_kitchen_admin_panel_table kp_kitchen_admin_panel_modal_table"
                    >

                        <thead class="kp_kitchen_admin_panel_table_head">

                            <tr class="kp_kitchen_admin_panel_table_row">

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Order ID
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Customer
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Area
                                </th>

                                <th class="kp_kitchen_admin_panel_table_heading">
                                    Assigned Driver
                                </th>

                            </tr>

                        </thead>

                        <tbody
                            id="dispatchModalList"
                            class="kp_kitchen_admin_panel_table_body"
                        >
                            {{-- Dynamic rows inserted via JS --}}
                        </tbody>

                    </table>

                </div>


                <div class="kp_kitchen_admin_panel_modal_actions">

                    <button
                        type="button"
                        id="dispatchCancelBtn"
                        class="kp_kitchen_admin_panel_secondary_button"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        id="dispatchConfirmBtn"
                        class="kp_kitchen_admin_panel_primary_button kp_kitchen_admin_panel_dispatch_confirm_btn"
                    >
                        {{-- 🚀 --}}
                         Dispatch &amp; Notify Drivers
                    </button>

                </div>

            </div>

        </div>

    </div>

</section>

@endsection
