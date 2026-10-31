@extends('layouts.admin')

@section('title', 'Create Fee Component')
@section('page-title', 'Create New Fee Component')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fee-components.index') }}" class="text-decoration-none">Fee Components</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.fee-components.store') }}" method="POST">
            @csrf
            @include('admin.fee-components._form', ['submitLabel' => 'Create Fee Component'])
        </form>
    </div>
</div>
@endsection
