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
        <tbody class="kp_kitchen_admin_panel_table_body" id="usersTableBody">
          @forelse ($users as $user)
            <tr class="kp_kitchen_admin_panel_table_row">
              <td class="kp_kitchen_admin_panel_table_cell">#ADM{{ $user->id }}</td>
              <td class="kp_kitchen_admin_panel_table_cell"><strong>{{ $user->name }}</strong></td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $user->email }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">{{ $user->created_at->toDateString() }}</td>
              <td class="kp_kitchen_admin_panel_table_cell">
                <div class="kp_kitchen_admin_panel_table_actions">
                  <button class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_edit edit-user-btn"
                    data-id="{{ $user->id }}"
                    data-name="{{ $user->name }}"
                    data-email="{{ $user->email }}">
                    Edit
                  </button>
                  <form method="POST" action="{{ route('users.delete', $user->id) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this administrator?');">
                    @csrf
                    <button type="submit" class="kp_kitchen_admin_panel_action_button kp_kitchen_admin_panel_action_delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="kp_kitchen_admin_panel_table_cell" style="text-align: center;">No administrators found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </article>
</section>
@endsection
