@extends('layouts.admin')

@section('title', 'Tambah Program')
@section('page-title', 'Tambah Program')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.programs.index') }}" class="text-decoration-none">Program</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.programs.store') }}" method="POST">
            @csrf
            @include('admin.programs._form', ['submitLabel' => 'Tambah Program'])
        </form>
    </div>
</div>
@endsection
