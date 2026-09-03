@extends('layouts.app')
@section('title','Roles & Permissions')
@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('users.index') }}">Users</a><x-icon name="chevron" class="icon-sm" /> Roles & permissions</div><h1 class="page-title">Roles & permissions</h1><p class="page-subtitle">Control exactly which modules and actions each staff role can access. Changes take effect on the next request.</p></div></div>
<div class="alert alert-warning"><x-icon name="alert" /><span><strong>Permission changes affect every user assigned to the role.</strong><br>Administrator always retains full access as a recovery safeguard.</span></div>
<div class="grid grid-2">
@foreach($roles as $role)
    <form class="card" method="POST" action="{{ route('roles.update',$role) }}">@csrf @method('PUT')
        <div class="card-header"><div><h2 class="card-title">{{ $role->name }}</h2><p class="card-subtitle">{{ $role->description }} · {{ $role->users->count() }} user(s)</p></div><x-status-badge :status="$role->is_active?'active':'inactive'" /></div>
        <div class="card-body">
            <div class="field" style="margin-bottom:18px"><label class="required" for="role-status-{{ $role->id }}">Role status</label><select class="select" id="role-status-{{ $role->id }}" name="is_active" @disabled($role->slug==='administrator')><option value="1" @selected($role->is_active)>Active</option><option value="0" @selected(!$role->is_active)>Inactive</option></select>@if($role->slug==='administrator')<input type="hidden" name="is_active" value="1"><div class="help-text">The Administrator role cannot be disabled.</div>@endif</div>
            @foreach($permissionGroups as $group=>$permissions)
                <fieldset style="border:0;border-top:1px solid var(--border);padding:14px 0 0;margin:0 0 14px"><legend style="padding-right:8px;color:var(--muted);font-size:10px;font-weight:750;text-transform:uppercase;letter-spacing:.08em">{{ $group }}</legend><div class="form-grid" style="gap:8px">@foreach($permissions as $permission)<label class="checkbox-row" style="min-height:30px"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission->id))><span>{{ $permission->name }}</span></label>@endforeach</div></fieldset>
            @endforeach
            <div class="form-actions"><button class="btn btn-primary" type="submit"><x-icon name="check" class="icon-sm" /> Save {{ $role->name }}</button></div>
        </div>
    </form>
@endforeach
</div>
@endsection
