@extends('layouts.admin')

@section('title', 'Ubah Program')
@section('page-title', 'Ubah Program: ' . $program->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}" class="text-decoration-none">Program</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.programs.update', $program->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.programs._form', ['submitLabel' => 'Simpan Perubahan'])
        </form>
    </div>
</div>
@endsection
