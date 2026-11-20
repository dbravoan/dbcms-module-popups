@extends('admin.layout')
@section('title', 'Popups')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.datatable-header title="Popups" description="Ventanas, barras y paneles con contenido editorial y apertura contextual." search-placeholder="Buscar popup por título…" :create-route="route('admin.popups.create')" create-label="Crear popup">
        <x-admin.select name="is_active" :label="__('admin.common.availability')" :value="request('is_active', 'all')" :options="['all' => __('admin.listing.all_statuses'), '1' => __('admin.status.active'), '0' => __('admin.status.inactive')]" />
        <x-admin.select name="is_published" :label="__('admin.common.status')" :value="request('is_published', 'all')" :options="['all' => __('admin.listing.all_statuses'), '1' => __('admin.status.published'), '0' => __('admin.status.draft')]" />
    </x-admin.datatable-header>
    <x-admin.table :empty="$popups->isEmpty()" emptyTitle="Todavía no hay popups" emptyDescription="Crea el primer aviso y configura sus condiciones." :emptyActionRoute="route('admin.popups.create')" emptyActionLabel="Crear popup">
        <x-slot:header>
            <th scope="col" class="px-4 py-3">{{ __('admin.common.name') }}</th>
            <th scope="col" class="px-4 py-3">Formato</th>
            <x-admin.sortable-th column="is_published" :label="__('admin.common.status')" />
            <x-admin.sortable-th column="priority" label="Prioridad" />
            <th scope="col" class="px-4 py-3 text-right">{{ __('admin.common.actions') }}</th>
        </x-slot:header>
        @foreach($popups as $popup)
            <tr>
                <td class="px-4 py-3 font-bold">{{ $popup->getAttribute('admin_title') }}</td>
                <td class="px-4 py-3">{{ $popup->mode }}</td>
                <td class="px-4 py-3"><x-admin.status-badge :status="$popup->getAttribute('admin_status')" /></td>
                <td class="px-4 py-3">{{ $popup->priority }}</td>
                <td class="px-4 py-3 text-right"><div class="inline-flex items-center gap-1.5">
                    <x-admin.action-button :href="route('admin.popups.edit', $popup)" icon="fa-solid fa-pen-to-square" :tooltip="__('admin.actions.edit')" variant="primary" />
                    <x-admin.action-button :href="route('admin.popups.preview', $popup)" target="_blank" rel="noopener" icon="fa-solid fa-eye" :tooltip="__('admin.actions.preview')" variant="view" />
                    <x-admin.action-button icon="fa-solid fa-trash-can" :tooltip="__('admin.actions.delete')" variant="danger" @click="$dispatch('confirm-delete', {{ Illuminate\Support\Js::from(['action' => route('admin.popups.destroy', $popup), 'title' => 'Eliminar popup', 'message' => '¿Confirmas que deseas eliminar este popup?']) }})" />
                </div></td>
            </tr>
        @endforeach
        <x-slot:pagination><x-admin.datatable-pagination :paginator="$popups" /></x-slot:pagination>
    </x-admin.table>
</div>
@endsection
