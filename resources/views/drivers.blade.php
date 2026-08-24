@extends('layouts.app')

@section('title', 'Drivers & Areas')
@section('current_page', 'drivers')
@section('page_title', 'Drivers & Areas')
@section('page_subtitle', 'Manage drivers and delivery area assignments.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="driversPage">
  <!-- Driver List Section -->
  <div id="driverListSection">
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
          <p class="kp_kitchen_admin_panel_driver_meta">🚙 Rego No: <strong>{{ $driver->vehicle_reg_no ?? 'N/A' }}</strong></p>
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

          <div class="kp_kitchen_admin_panel_card_actions" style="display: flex; gap: 8px; justify-content: flex-start; margin-top: 14px; border-top: 1px solid var(--panel-border); padding-top: 14px;">
            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-driver-btn"
              style="background: rgba(241, 196, 15, 0.1); border: 1px solid rgba(241, 196, 15, 0.2); color: #F1C40F; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
              title="Edit Driver"
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
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
              </svg>
            </button>
            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-driver-details-btn"
              style="background: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.2); color: #3498DB; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
              title="Driver Details"
              data-id="{{ $driver->id }}">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path d="M3 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H3zm10 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10z"/>
                <path d="M10 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM8 10a3 3 0 0 0-6 0v1h6v-1z"/>
              </svg>
            </button>
            <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-driver-history-btn"
              style="background: rgba(230, 126, 34, 0.1); border: 1px solid rgba(230, 126, 34, 0.2); color: #E67E22; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
              title="Delivery History"
              data-id="{{ $driver->id }}">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5v-7zm1.294 7.456A1.999 1.999 0 0 1 4.732 11h5.536a2.01 2.01 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456zM12 10a2 2 0 0 1 1.732-1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12v4zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
              </svg>
            </button>
            <form method="POST" action="{{ route('drivers.delete', $driver->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this driver?');">
              @csrf
              <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete"
                style="background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.2); color: #E74C3C; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                title="Delete Driver">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                  <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                </svg>
              </button>
            </form>
          </div>
        </article>
      @empty
        <div class="kp_kitchen_admin_panel_empty_state" style="grid-column: 1 / -1;">No drivers found. Click "+ Add Driver" to register one.</div>
      @endforelse
    </div>
  </div>

  <!-- Driver Edit Inline Section (Hidden by Default) -->
  <div id="driverEditSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToDriverListBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Drivers
        </button>
      </div>
    </div>
    <div class="kp_kitchen_admin_panel_card" style="padding: 24px;">
      <h3 style="margin: 0 0 20px 0; color: var(--primary-color); font-size: 1.15rem; font-weight: 600;" id="editDriverTitle">Edit Driver Profile</h3>
      <form id="inlineDriverEditForm" method="POST" action="{{ route('drivers.save') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="id" id="editDriverId">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Full Name</span>
            <input type="text" name="name" id="editDriverName" class="kp_kitchen_admin_panel_form_input" required>
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Phone Number</span>
            <input type="text" name="phone" id="editDriverPhone" class="kp_kitchen_admin_panel_form_input" required>
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Email Address</span>
            <input type="email" name="email" id="editDriverEmail" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Vehicle Registration Number</span>
            <input type="text" name="vehicle_reg_no" id="editDriverVehicle" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Assigned Postcode</span>
            <input type="text" name="assigned_zip" id="editDriverPostcode" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">License Number</span>
            <input type="text" name="license_no" id="editDriverLicense" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">License Expiry Date</span>
            <input type="date" name="license_expiry" id="editDriverLicenseExpiry" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Address</span>
            <input type="text" name="address" id="editDriverAddress" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Status</span>
            <select name="status" id="editDriverStatus" class="kp_kitchen_admin_panel_form_select">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Change Password (leave blank to keep current)</span>
            <input type="password" name="password" id="editDriverPassword" class="kp_kitchen_admin_panel_form_input" placeholder="Enter new password">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">License Photo (Front)</span>
            <input type="file" name="license_copy_front" class="kp_kitchen_admin_panel_form_input">
          </label>

          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">License Photo (Back)</span>
            <input type="file" name="license_copy_back" class="kp_kitchen_admin_panel_form_input">
          </label>

        </div>

        <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--panel-border); padding-top: 20px;">
          <button type="button" id="cancelDriverEditBtn" class="kp_kitchen_admin_panel_secondary_button">Cancel</button>
          <button type="submit" class="kp_kitchen_admin_panel_primary_button">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Driver Details Inline Section (Hidden by Default) -->
  <div id="driverDetailsSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToDriverListFromDetailsBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Drivers
        </button>
      </div>
    </div>
    <div id="driverDetailsContent"></div>
  </div>

  <!-- Driver Delivery History Section (Hidden by Default) -->
  <div id="driverHistorySection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToDriverListFromHistoryBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Drivers
        </button>
      </div>
    </div>
    <div id="driverHistoryContent"></div>
  </div>
</section>
@endsection
