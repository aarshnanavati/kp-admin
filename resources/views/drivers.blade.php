@extends('layouts.app')

@section('title', 'Drivers & Areas')
@section('current_page', 'drivers')
@section('page_title', 'Drivers & Areas')
@section('page_subtitle', 'Manage drivers and delivery area assignments.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="driversPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Drivers &amp; Area Assignment</h2>
      <p class="kp_kitchen_admin_panel_section_text">Add drivers and assign delivery areas.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addDriverButton">+ Add Driver</button>
  </div>

  <div class="kp_kitchen_admin_panel_cards_grid" id="driversGrid">
    @forelse ($drivers as $driver)
      <article class="kp_kitchen_admin_panel_driver_card">
        <div class="kp_kitchen_admin_panel_driver_top">
          <div class="kp_kitchen_admin_panel_driver_avatar">{{ strtoupper(substr($driver->name, 0, 1)) }}</div>
          <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $driver->status)) }}">{{ $driver->status }}</span>
        </div>
        <h3 class="kp_kitchen_admin_panel_driver_name">{{ $driver->name }}</h3>
        <p class="kp_kitchen_admin_panel_driver_meta">📞 {{ $driver->phone }}</p>
        <p class="kp_kitchen_admin_panel_driver_meta">✉️ {{ $driver->email ?? 'No email' }}</p>
        <p class="kp_kitchen_admin_panel_driver_meta">📍 {{ $driver->address ?? 'No address' }}</p>
        <p class="kp_kitchen_admin_panel_driver_meta">🚙 Reg: <strong>{{ $driver->vehicle_reg_no ?? 'N/A' }}</strong></p>
        <p class="kp_kitchen_admin_panel_driver_meta">📮 Postcode: <strong>{{ $driver->assigned_zip ?? 'N/A' }}</strong></p>
        <p class="kp_kitchen_admin_panel_driver_meta">🛡️ License: <strong>{{ $driver->license_no ?? 'N/A' }}</strong> (Exp: {{ $driver->license_expiry ?? 'N/A' }})</p>
        <p class="kp_kitchen_admin_panel_driver_meta">📦 Active shipments: <strong>{{ $activeDeliveriesMap[$driver->id] ?? 0 }}</strong></p>
        
        <div class="kp_kitchen_admin_panel_doc_upload_grid">
          <div class="kp_kitchen_admin_panel_doc_preview_card">
            <span>License Front</span>
            @if ($driver->license_copy_front)
              <div class="kp_kitchen_admin_panel_doc_image_wrap"><img src="{{ asset($driver->license_copy_front) }}" alt="Front"></div>
            @else
              <div class="kp_kitchen_admin_panel_doc_image_wrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                  <line x1="16" y1="13" x2="8" y2="13"/>
                  <line x1="16" y1="17" x2="8" y2="17"/>
                  <polyline points="10 9 9 9 8 9"/>
                </svg>
              </div>
            @endif
          </div>
          <div class="kp_kitchen_admin_panel_doc_preview_card">
            <span>License Back</span>
            @if ($driver->license_copy_back)
              <div class="kp_kitchen_admin_panel_doc_image_wrap"><img src="{{ asset($driver->license_copy_back) }}" alt="Back"></div>
            @else
              <div class="kp_kitchen_admin_panel_doc_image_wrap">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                  <line x1="16" y1="13" x2="8" y2="13"/>
                  <line x1="16" y1="17" x2="8" y2="17"/>
                  <polyline points="10 9 9 9 8 9"/>
                </svg>
              </div>
            @endif
          </div>
        </div>

        <div class="kp_kitchen_admin_panel_card_actions">
          <button class="kp_kitchen_admin_panel_small_button view-driver-details-btn"
            style="background-color: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498DB;"
            data-name="{{ $driver->name }}"
            data-phone="{{ $driver->phone }}"
            data-email="{{ $driver->email }}"
            data-address="{{ $driver->address }}"
            data-license_no="{{ $driver->license_no }}"
            data-license_expiry="{{ $driver->license_expiry }}"
            data-vehicle_reg_no="{{ $driver->vehicle_reg_no }}"
            data-assigned_zip="{{ $driver->assigned_zip }}"
            data-status="{{ $driver->status }}"
            data-license_copy_front="{{ $driver->license_copy_front }}"
            data-license_copy_back="{{ $driver->license_copy_back }}"
            data-active_shipments="{{ $activeDeliveriesMap[$driver->id] ?? 0 }}"
            data-total_orders="{{ $driver->orders->count() }}"
            data-orders="{{ json_encode($driver->orders()->orderBy('date', 'desc')->take(5)->get()) }}">
            Driver Details
          </button>
          <button class="kp_kitchen_admin_panel_small_button edit-driver-btn"
            data-id="{{ $driver->id }}"
            data-name="{{ $driver->name }}"
            data-phone="{{ $driver->phone }}"
            data-email="{{ $driver->email }}"
            data-address="{{ $driver->address }}"
            data-license_no="{{ $driver->license_no }}"
            data-license_expiry="{{ $driver->license_expiry }}"
            data-vehicle_reg_no="{{ $driver->vehicle_reg_no }}"
            data-assigned_zip="{{ $driver->assigned_zip }}"
            data-status="{{ $driver->status }}"
            data-license_copy_front="{{ $driver->license_copy_front }}"
            data-license_copy_back="{{ $driver->license_copy_back }}">
            Edit
          </button>
          <form method="POST" action="{{ route('drivers.delete', $driver->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this driver?');">
            @csrf
            <button type="submit" class="kp_kitchen_admin_panel_danger_button">Delete</button>
          </form>
        </div>
      </article>
    @empty
      <div class="kp_kitchen_admin_panel_empty_state" style="grid-column: 1 / -1;">No drivers found. Click "+ Add Driver" to register one.</div>
    @endforelse
  </div>
</section>
@endsection
