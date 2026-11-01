@extends('layouts.admin')

@section('title', 'Ubah Komponen Biaya')
@section('page-title', 'Ubah Komponen Biaya: ' . $feeComponent->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-components.index') }}" class="text-decoration-none">Komponen Biaya</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-components.update', $feeComponent->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.fee-components._form', ['submitLabel' => 'Simpan Perubahan'])
        </form>
    </div>
</div>
@endsection
