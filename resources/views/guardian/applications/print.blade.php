@extends('layouts.guardian')

@section('title', 'Cetak Formulir')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
    <h3 class="fw-bold mb-0">Formulir Pendaftaran</h3>
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak Formulir
    </button>
</div>

@include('prints._application_form')
@endsection
