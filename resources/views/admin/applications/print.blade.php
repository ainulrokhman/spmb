@extends('layouts.admin')

@section('title', 'Cetak Formulir')
@section('page-title', 'Cetak Formulir Pendaftaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.applications.index') }}">Verifikasi Pendaftaran</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.applications.show', $application) }}">Detail</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cetak Formulir</li>
@endsection

@section('content')
<div class="d-flex justify-content-end mb-3 d-print-none">
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak Formulir
    </button>
</div>

@include('prints._application_form')
@endsection
