@extends('admin.layout')
@section('title', 'Editar popup')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header title="Editar popup" description="Actualiza el contenido, las condiciones y los disparadores." :backRoute="route('admin.popups.index')" backLabel="Volver a popups" />
    @include('popups::admin._form')
</div>
@endsection
