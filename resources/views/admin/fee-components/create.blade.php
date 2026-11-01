@extends('layouts.admin')

@section('title', 'Tambah Komponen Biaya')
@section('page-title', 'Tambah Komponen Biaya')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-components.index') }}" class="text-decoration-none">Komponen Biaya</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-components.store') }}" method="POST">
            @csrf
            @include('admin.fee-components._form', ['submitLabel' => 'Tambah Komponen Biaya'])
        </form>
    </div>
</div>
@endsection
