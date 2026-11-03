@extends('layouts.admin')

@section('title', 'Tambah Aturan Seleksi')
@section('page-title', 'Tambah Aturan Seleksi')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.index') }}" class="text-decoration-none">Unit Sekolah</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.school-units.selection.index', $schoolUnit) }}" class="text-decoration-none">Seleksi — {{ $schoolUnit->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Aturan</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.school-units.selection.store', $schoolUnit) }}">
        @csrf
        @include('admin.selection._form', ['rule' => new \App\Models\SelectionRule(), 'submitLabel' => 'Tambah Aturan'])
    </form>
@endsection
