@extends('layouts.admin')

@section('title', 'Tambah Bidang Formulir')
@section('page-title', 'Tambah Bidang Formulir')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.form-fields.index') }}" class="text-decoration-none">Form Fields</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.form-fields.store') }}" method="POST">
            @csrf
            @include('admin.form-fields._form', ['submitLabel' => 'Tambah Bidang Formulir'])
        </form>
    </div>
</div>
@endsection
