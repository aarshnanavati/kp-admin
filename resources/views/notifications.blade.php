@extends('layouts.app')

@section('title', 'Notifications')
@section('current_page', 'notifications')
@section('page_title', 'Notifications')
@section('page_subtitle', 'Review customer order and payment notifications.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="notificationsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Order Notifications</h2>
      <p class="kp_kitchen_admin_panel_section_text">See when customers place or update orders.</p>
    </div>
    <button class="kp_kitchen_admin_panel_secondary_button" id="markAllReadButton">Mark all as read</button>
  </div>
  <div class="kp_kitchen_admin_panel_notification_list" id="notificationsList"></div>
</section>
@endsection
