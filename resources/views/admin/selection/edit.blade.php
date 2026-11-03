@extends('layouts.admin')

@section('title', 'Ubah Aturan Seleksi')
@section('page-title', 'Ubah Aturan Seleksi')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">Unit Sekolah</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.selection.index', $schoolUnit) }}" class="text-decoration-none">Seleksi — {{ $schoolUnit->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Ubah Aturan</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.school-units.selection.update', [$schoolUnit, $rule]) }}">
        @csrf
        @method('PUT')
        @include('admin.selection._form', ['submitLabel' => 'Simpan Perubahan'])
    </form>
@endsection
