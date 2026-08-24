@extends('layouts.app')

@section('title', 'Customers')
@section('current_page', 'customers')
@section('page_title', 'Customer Management')
@section('page_subtitle', 'Review customer accounts, postcodes, and ordering histories.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="customersPage">
  <!-- Customer List Section -->
  <div id="customerListSection">
    <div class="kp_kitchen_admin_panel_section_toolbar">
      <div>
        <h2 class="kp_kitchen_admin_panel_section_title">Customers</h2>
        <p class="kp_kitchen_admin_panel_section_text">Registered app customers in Australia.</p>
      </div>
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <form id="customerSearchForm" onsubmit="event.preventDefault();" style="display: flex; gap: 8px; align-items: center; margin: 0;">
          <input type="text" id="customerSearchInput" class="kp_kitchen_admin_panel_form_input" placeholder="Search customers..." style="padding: 6px 12px; font-size: 0.85rem; min-width: 220px; max-height: 38px;">
          <button type="button" id="clearCustomerSearchBtn" class="kp_kitchen_admin_panel_secondary_button" style="display: none; padding: 6px 14px; font-size: 0.85rem; max-height: 38px; color: var(--text-primary); border: 1px solid var(--panel-border); align-items: center; justify-content: center;">Clear</button>
        </form>
        <button class="kp_kitchen_admin_panel_primary_button" id="addCustomerButton" style="max-height: 38px;">+ Add Customer</button>
      </div>
    </div>

    <article class="kp_kitchen_admin_panel_card">
      <div class="kp_kitchen_admin_panel_table_wrap">
        <table class="kp_kitchen_admin_panel_table">
          <thead class="kp_kitchen_admin_panel_table_head">
            <tr class="kp_kitchen_admin_panel_table_row">
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 15%;">Name</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 15%;">Phone</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 20%;">Email</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 12%;">Postcode</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 20%;">Address</th>
              <th class="kp_kitchen_admin_panel_table_heading" style="width: 18%; min-width: 290px;">Actions</th>
            </tr>
          </thead>
          <tbody class="kp_kitchen_admin_panel_table_body" id="customersTableBody">
            @forelse ($customers as $customer)
              <tr class="kp_kitchen_admin_panel_table_row">
                <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $customer->name }}</strong></td>
                <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->phone }}</td>
                <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->email }}</td>
                <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $customer->pincode }}</strong></td>
                <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->address }}</td>
                <td class="kp_kitchen_admin_panel_table_cell">
                  <div class="kp_kitchen_admin_panel_table_actions" style="display: flex; gap: 8px;">
                    <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-customer-btn"
                      style="background: rgba(241, 196, 15, 0.1); border: 1px solid rgba(241, 196, 15, 0.2); color: #F1C40F; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                      title="Edit Customer"
                      data-id="{{ $customer->id }}"
                      data-name="{{ $customer->name }}"
                      data-phone="{{ $customer->phone }}"
                      data-email="{{ $customer->email }}"
                      data-pincode="{{ $customer->pincode }}"
                      data-address="{{ $customer->address }}">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                      </svg>
                    </button>
                    <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-customer-payment-btn"
                      style="background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.2); color: #2ECC71; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                      title="Payment History"
                      data-id="{{ $customer->id }}">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z"/>
                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z"/>
                      </svg>
                    </button>
                    <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-customer-invoices-btn"
                      style="background: rgba(230, 126, 34, 0.1); border: 1px solid rgba(230, 126, 34, 0.2); color: #E67E22; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                      title="Invoices"
                      data-id="{{ $customer->id }}">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1.92.506a.5.5 0 0 1 .434.14L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.708 0L3 14.707l-.646-.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l-.5-1a.5.5 0 0 1 .367-.27zM2 2v12h12V2H2z"/>
                        <path d="M4 4h8v1H4V4zm0 2h8v1H4V6zm0 2h8v1H4V8zm0 2h5v1H4v-1z"/>
                      </svg>
                    </button>
                    <form method="POST" action="{{ route('customers.delete', $customer->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this customer account? All associated orders and invoices will be deleted.');">
                      @csrf
                      <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete"
                        style="background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.2); color: #E74C3C; width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; padding: 0;"
                        title="Delete Customer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                          <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                          <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                        </svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No customers found. Click "+ Add Customer" to create one.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </article>
  </div>

  <!-- Customer Detailed Grid Section (Hidden by Default) -->
  <div id="customerDetailedGridSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToCustomerListBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Customers
        </button>
      </div>
    </div>
    <div id="customerDetailsGridContent"></div>
  </div>

  <!-- Customer Payment Grid Section (Hidden by Default) -->
  <div id="customerPaymentGridSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToCustomerListFromPaymentBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Customers
        </button>
      </div>
    </div>
    <div id="customerPaymentGridContent"></div>
  </div>

  <!-- Customer Invoices Grid Section (Hidden by Default) -->
  <div id="customerInvoicesGridSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToCustomerListFromInvoicesBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Customers
        </button>
      </div>
    </div>
    <div id="customerInvoicesGridContent"></div>
  </div>

  <!-- Customer Edit Grid Section (Hidden by Default) -->
  <div id="customerEditGridSection" style="display: none;">
    <div class="kp_kitchen_admin_panel_section_toolbar" style="margin-bottom: 24px;">
      <div>
        <button id="backToCustomerListFromEditBtn" class="kp_kitchen_admin_panel_secondary_button" style="padding: 6px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px;">
          ← Back to Customers
        </button>
      </div>
    </div>
    <div class="kp_kitchen_admin_panel_card" style="max-width: 800px; margin: auto;">
      <h3 style="margin: 0 0 20px 0; font-family: var(--font-title); font-size: 1.2rem; color: var(--primary-color);">📝 Edit Customer Profile</h3>
      <form method="POST" action="{{ route('customers.save') }}" id="customerEditForm">
        @csrf
        <input type="hidden" name="id" id="editCustomerId">
        
        <div class="kp_kitchen_admin_panel_form_grid">
          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Customer Name</span>
            <input name="name" type="text" id="editCustomerName" class="kp_kitchen_admin_panel_form_input" required>
          </label>
          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Phone Number</span>
            <input name="phone" type="text" id="editCustomerPhone" class="kp_kitchen_admin_panel_form_input" required>
          </label>
        </div>
        
        <div class="kp_kitchen_admin_panel_form_grid">
          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Email Address</span>
            <input name="email" type="email" id="editCustomerEmail" class="kp_kitchen_admin_panel_form_input" required>
          </label>
          <label class="kp_kitchen_admin_panel_form_group">
            <span class="kp_kitchen_admin_panel_form_label">Postcode</span>
            <input name="pincode" type="text" id="editCustomerPincode" class="kp_kitchen_admin_panel_form_input" required>
          </label>
        </div>
        
        <label class="kp_kitchen_admin_panel_form_group">
          <span class="kp_kitchen_admin_panel_form_label">Full Address</span>
          <textarea name="address" id="editCustomerAddress" class="kp_kitchen_admin_panel_form_input" style="height: 100px;" required></textarea>
        </label>
        
        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
          <button type="button" id="cancelCustomerEditBtn" class="kp_kitchen_admin_panel_secondary_button">Cancel</button>
          <button type="submit" class="kp_kitchen_admin_panel_primary_button">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</section>
@endsection
