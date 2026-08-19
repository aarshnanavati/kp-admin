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
    <form method="POST" action="{{ route('notifications.read-all') }}">
      @csrf
      <button type="submit" class="kp_kitchen_admin_panel_secondary_button" id="markAllReadButton">Mark all as read</button>
    </form>
  </div>
  
  <div class="kp_kitchen_admin_panel_notification_list" id="notificationsList">
    @forelse ($notifications as $notification)
      <article class="kp_kitchen_admin_panel_notification_item {{ $notification->read_status ? '' : 'kp_kitchen_admin_panel_notification_item_unread' }}">
        <div class="kp_kitchen_admin_panel_notification_icon">
          {{ str_contains($notification->title, 'Payment') ? '$' : '🔔' }}
        </div>
        <div class="kp_kitchen_admin_panel_notification_content">
          <div class="kp_kitchen_admin_panel_notification_heading">
            <strong class="kp_kitchen_admin_panel_notification_title">{{ $notification->title }}</strong>
            <span class="kp_kitchen_admin_panel_notification_time">{{ $notification->created_at->diffForHumans() }}</span>
          </div>
          <p class="kp_kitchen_admin_panel_notification_message">{{ $notification->message }}</p>
        </div>
        @if (!$notification->read_status)
          <form method="POST" action="{{ route('notifications.read', $notification->id) }}" style="margin: 0; display: inline;">
            @csrf
            <button type="submit" class="kp_kitchen_admin_panel_notification_read_button">Mark read</button>
          </form>
        @else
          <button class="kp_kitchen_admin_panel_notification_read_button" disabled style="opacity: 0.5; cursor: default;">Read</button>
        @endif
      </article>
    @empty
      <div class="kp_kitchen_admin_panel_empty_state">No notifications available.</div>
    @endforelse
  </div>
</section>
@endsection
