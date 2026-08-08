@extends('layouts.app')

@section('title', 'Manage Tiffins')
@section('current_page', 'tiffins')
@section('page_title', 'Manage Tiffins')
@section('page_subtitle', 'Add, update and remove tiffin plans.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="tiffinsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Manage Tiffins</h2>
      <p class="kp_kitchen_admin_panel_section_text">Add, update or remove tiffin plans.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addTiffinButton">+ Add Tiffin</button>
  </div>
  <div class="kp_kitchen_admin_panel_tiffin_grid" id="tiffinsGrid"></div>
</section>
@endsection
