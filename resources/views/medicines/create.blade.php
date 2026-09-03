@extends('layouts.app')
@section('title','Add Medicine')
@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('medicines.index') }}">Medicines</a><x-icon name="chevron" class="icon-sm" /> Add</div><h1 class="page-title">Add medicine</h1><p class="page-subtitle">Create the catalog record first, then receive stock into a batch.</p></div></div>
@include('medicines._form')
@endsection
