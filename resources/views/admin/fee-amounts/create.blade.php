@extends('layouts.admin')

@section('title', 'Create Fee Amount')
@section('page-title', 'Create New Fee Amount')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-amounts.index') }}" class="text-decoration-none">Fee Amounts</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-amounts.store') }}" method="POST">
            @csrf
            @include('admin.fee-amounts._form', ['submitLabel' => 'Create Fee Amount'])
        </form>
    </div>
</div>
@endsection
