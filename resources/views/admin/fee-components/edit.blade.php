@extends('layouts.admin')

@section('title', 'Edit Fee Component')
@section('page-title', 'Edit Fee Component: ' . $feeComponent->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-components.index') }}" class="text-decoration-none">Fee Components</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-components.update', $feeComponent->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.fee-components._form', ['submitLabel' => 'Save Changes'])
        </form>
    </div>
</div>
@endsection
