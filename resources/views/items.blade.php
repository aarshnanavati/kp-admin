@extends('layouts.app')

@section('title', 'Menu Items')
@section('current_page', 'items')
@section('page_title', 'Menu Items')
@section('page_subtitle', 'Manage individual items that can be ordered or added to tiffins.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="itemsPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Menu Items</h2>
      <p class="kp_kitchen_admin_panel_section_text">Manage sides, extra rotis, drinks, and individual dishes.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addItemButton">+ Add Menu Item</button>
  </div>

  <div class="kp_kitchen_admin_panel_tiffin_grid" id="itemsGrid"></div>
</section>
@endsection
