@extends('layouts.admin')

@section('title', 'Ubah Status Anak')
@section('page-title', 'Ubah Status Anak: ' . $childStatus->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.child-statuses.index') }}" class="text-decoration-none">Status Anak</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.child-statuses.update', $childStatus->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.child-statuses._form', ['submitLabel' => 'Simpan Perubahan'])
        </form>
    </div>
</div>
@endsection
