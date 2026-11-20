<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Modules\Popups\Http\Requests\PopupRequest;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Services\PopupAdministration;
use Illuminate\Http\RedirectResponse;

final class PopupPutController extends Controller
{
    public function __construct(private readonly PopupAdministration $service) {}

    public function __invoke(PopupRequest $request, Popup $popup): RedirectResponse
    {
        $this->service->update($popup, $request->validated());

        return redirect()->route('admin.popups.index')->with('success', 'Popup actualizado.');
    }
}
