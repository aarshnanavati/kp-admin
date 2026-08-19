@extends('layouts.app')

@section('title', 'Customers')
@section('current_page', 'customers')
@section('page_title', 'Customer Management')
@section('page_subtitle', 'Review customer accounts, postcodes, and ordering histories.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="customersPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Customers</h2>
      <p class="kp_kitchen_admin_panel_section_text">Registered app customers in Australia.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addCustomerButton">+ Add Customer</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 10%;">Customer ID</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 12%;">Name</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 12%;">Phone</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 18%;">Email</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 10%;">Default Postcode</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 18%;">Default Address</th>
            <th class="kp_kitchen_admin_panel_table_heading" style="width: 20%; min-width: 290px;">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="customersTableBody">
          @forelse ($customers as $customer)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell">#CUST{{ $customer->id }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $customer->name }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->phone }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->email }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $customer->pincode }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $customer->address }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_table_actions">
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-customer-details-btn"
                    data-name="{{ $customer->name }}"
                    data-phone="{{ $customer->phone }}"
                    data-email="{{ $customer->email }}"
                    data-pincode="{{ $customer->pincode }}"
                    data-address="{{ $customer->address }}"
                    data-total_orders="{{ $customer->orders->count() }}"
                    data-total_spend="{{ $customer->payments->where('status', 'Successful')->sum('amount') }}"
                    data-orders="{{ json_encode($customer->orders()->orderBy('date', 'desc')->take(5)->get()) }}">
                    Customer Details
                  </button>
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-customer-btn"
                    data-id="{{ $customer->id }}"
                    data-name="{{ $customer->name }}"
                    data-phone="{{ $customer->phone }}"
                    data-email="{{ $customer->email }}"
                    data-pincode="{{ $customer->pincode }}"
                    data-address="{{ $customer->address }}">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('customers.delete', $customer->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this customer account? All associated orders and invoices will be deleted.');">
                    @csrf
                    <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No customers found. Click "+ Add Customer" to create one.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
