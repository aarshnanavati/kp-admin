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
        <tbody class="kp_kitchen_admin_panel_table_body" id="categoriesTableBody">
          @forelse ($categories as $category)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell">#CAT{{ $category->id }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $category->name }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $category->description ?? 'No description' }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_table_actions">
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-category-btn"
                    data-id="{{ $category->id }}"
                    data-name="{{ $category->name }}"
                    data-description="{{ $category->description }}">
                    Edit
                  </button>
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_view view-items-btn" 
                    data-name="{{ $category->name }}"
                    data-items="{{ json_encode($category->items) }}">
                    View Items
                  </button>
                  <form method="POST" action="{{ route('categories.delete', $category->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category? All menu items under this category will be updated.');">
                    @csrf
                    <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr class="kp_kitchen_admin_panel_table_row">
              <td colspan="4" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No categories found. Click "+ Add Category" to create one.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
