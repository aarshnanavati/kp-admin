@extends('layouts.app')

@section('title', 'Coupons')
@section('current_page', 'coupons')
@section('page_title', 'Coupons & Discounts')
@section('page_subtitle', 'Manage discount promo codes and discount models.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="couponsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Coupons &amp; Discounts</h2>
      <p class="kp_kitchen_admin_panel_section_text">Create promotional and customer discount codes.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addCouponButton">+ Add Coupon</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Code</th>
            <th class="kp_kitchen_admin_panel_table_heading">Type</th>
            <th class="kp_kitchen_admin_panel_table_heading">Discount Value</th>
            <th class="kp_kitchen_admin_panel_table_heading">Expiry Date</th>
            <th class="kp_kitchen_admin_panel_table_heading">Status</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="couponsTableBody">
          @forelse ($coupons as $coupon)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell"><strong style="letter-spacing: 1px; color: var(--primary-color);">{{ $coupon->code }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $coupon->type }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <strong>{{ $coupon->type === 'Percentage' ? number_format($coupon->value, 0) . '%' : '$' . number_format($coupon->value, 2) }}</strong>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $coupon->expiry_date }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <span class="kp_kitchen_admin_panel_status kp_kitchen_admin_panel_status_{{ strtolower(str_replace(' ', '_', $coupon->status)) }}">{{ $coupon->status }}</span>
              </td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_table_actions">
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-coupon-btn"
                    data-id="{{ $coupon->id }}"
                    data-code="{{ $coupon->code }}"
                    data-type="{{ $coupon->type }}"
                    data-value="{{ $coupon->value }}"
                    data-expiry_date="{{ $coupon->expiry_date }}"
                    data-status="{{ $coupon->status }}">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('coupons.delete', $coupon->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this coupon code?');">
                    @csrf
                    <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No coupons found. Click "+ Add Coupon" to create one.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
