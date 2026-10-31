@extends('layouts.admin')

@section('title', 'Tambah Nominal Biaya')
@section('page-title', 'Tambah Nominal Biaya')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-amounts.index') }}" class="text-decoration-none">Fee Amounts</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-amounts.store') }}" method="POST">
            @csrf
            @include('admin.fee-amounts._form', ['submitLabel' => 'Tambah Nominal Biaya'])
        </form>
    </div>
</div>
@endsection
