@extends('layouts.admin')

@section('title', 'Edit Form Field')
@section('page-title', 'Edit Form Field: ' . $formField->label)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.form-fields.index') }}" class="text-decoration-none">Form Fields</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6 mx-auto">
        <form action="{{ route('admin.form-fields.update', $formField->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.form-fields._form', ['submitLabel' => 'Save Changes'])
        </form>
    </div>
</div>
@endsection
