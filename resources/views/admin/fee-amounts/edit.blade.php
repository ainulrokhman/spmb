@extends('layouts.admin')

@section('title', 'Edit Fee Amount')
@section('page-title', 'Edit Fee Amount')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-amounts.index') }}" class="text-decoration-none">Fee Amounts</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-amounts.update', $feeAmount->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.fee-amounts._form', ['submitLabel' => 'Save Changes'])
        </form>
    </div>
</div>
@endsection
