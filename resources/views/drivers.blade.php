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
  <div class="kp_kitchen_admin_panel_cards_grid" id="driversGrid"></div>
</section>
@endsection
