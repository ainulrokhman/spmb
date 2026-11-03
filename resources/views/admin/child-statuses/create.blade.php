@extends('layouts.admin')

@section('title', 'Tambah Status Anak')
@section('page-title', 'Tambah Status Anak')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.child-statuses.index') }}" class="text-decoration-none">Status Anak</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.child-statuses.store') }}" method="POST">
            @csrf
            @include('admin.child-statuses._form', ['submitLabel' => 'Tambah Status Anak'])
        </form>
    </div>
</div>
@endsection
