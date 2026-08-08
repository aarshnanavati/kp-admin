@extends('layouts.app')

@section('title', 'Categories')
@section('current_page', 'categories')
@section('page_title', 'Menu Categories')
@section('page_subtitle', 'Organise tiffin plans and individual items into categories.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="categoriesPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Categories</h2>
      <p class="kp_kitchen_admin_panel_section_text">Manage food categories for easier customer browsing.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addCategoryButton">+ Add Category</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Category ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Name</th>
            <th class="kp_kitchen_admin_panel_table_heading">Description</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="categoriesTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
