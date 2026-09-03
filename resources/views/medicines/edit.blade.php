@extends('layouts.app')
@section('title','Edit Medicine')
@section('content')
<div class="page-header"><div><div class="breadcrumb"><a href="{{ route('medicines.index') }}">Medicines</a><x-icon name="chevron" class="icon-sm" /><a href="{{ route('medicines.show',$medicine) }}">{{ $medicine->generic_name }}</a><x-icon name="chevron" class="icon-sm" /> Edit</div><h1 class="page-title">Edit {{ $medicine->generic_name }}</h1><p class="page-subtitle">Update catalog, threshold, pricing, and storage details.</p></div></div>
@include('medicines._form')
@endsection
