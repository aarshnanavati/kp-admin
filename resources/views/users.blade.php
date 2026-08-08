@extends('layouts.app')

@section('title', 'Admin Users')
@section('current_page', 'users')
@section('page_title', 'Administrator Users')
@section('page_subtitle', 'Manage control panel admin users and credentials.')

@section('content')
<section class="kp_kitchen_admin_panel_page kp_kitchen_admin_panel_page_active" id="usersPage">
  <div class="kp_kitchen_admin_panel_section_toolbar">
    <div>
      <h2 class="kp_kitchen_admin_panel_section_title">Administrators</h2>
      <p class="kp_kitchen_admin_panel_section_text">Control panel users with database access.</p>
    </div>
    <button class="kp_kitchen_admin_panel_primary_button" id="addUserButton">+ Add Administrator</button>
  </div>

  <article class="kp_kitchen_admin_panel_card">
    <div class="kp_kitchen_admin_panel_table_wrap">
      <table class="kp_kitchen_admin_panel_table">
        <thead class="kp_kitchen_admin_panel_table_head">
          <tr class="kp_kitchen_admin_panel_table_row">
            <th class="kp_kitchen_admin_panel_table_heading">Admin ID</th>
            <th class="kp_kitchen_admin_panel_table_heading">Name</th>
            <th class="kp_kitchen_admin_panel_table_heading">Email</th>
            <th class="kp_kitchen_admin_panel_table_heading">Created Date</th>
            <th class="kp_kitchen_admin_panel_table_heading">Actions</th>
          </tr>
        </thead>
        <tbody class="kp_kitchen_admin_panel_table_body" id="usersTableBody"></tbody>
      </table>
    </div>
  </article>
</section>
@endsection
