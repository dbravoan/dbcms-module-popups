@extends('admin.layout')
@section('title', 'Crear popup')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header title="Crear popup" description="Compón un aviso con bloques y define su audiencia y momento de apertura." :backRoute="route('admin.popups.index')" backLabel="Volver a popups" />
    @include('popups::admin._form')
</div>
@endsection
