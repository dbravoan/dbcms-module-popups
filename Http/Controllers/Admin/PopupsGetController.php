<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Admin\DatatableQueryService;
use DbCms\Modules\Popups\Models\Popup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PopupsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $request->validate(['is_active' => 'nullable|in:all,0,1', 'is_published' => 'nullable|in:all,0,1']);
        $popups = $tables->paginate(
            Popup::with('translations'),
            DatatableQueryData::fromInput($request->query()),
            ['translations.title'],
            ['is_active' => 'is_active', 'is_published' => 'is_published'],
            'priority',
        );
        foreach ($popups->items() as $popup) {
            $translations = $popup->translations;
            $title = '#'.$popup->id;
            if ($translations->isNotEmpty()) {
                $title = ($translations->firstWhere('locale', app()->getLocale()) ?? $translations->first())->title;
            }
            $popup->setAttribute('admin_title', $title);
            $popup->setAttribute('admin_status', ! $popup->is_active ? 'inactive' : ($popup->is_published ? 'published' : 'draft'));
        }

        return view('popups::admin.index', compact('popups'));
    }
}
