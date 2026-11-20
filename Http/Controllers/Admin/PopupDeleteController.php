<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Services\PopupAdministration;
use Illuminate\Http\RedirectResponse;

final class PopupDeleteController extends Controller
{
    public function __construct(private readonly PopupAdministration $service) {}

    public function __invoke(Popup $popup): RedirectResponse
    {
        $this->service->delete($popup);

        return redirect()->route('admin.popups.index')->with('success', 'Popup eliminado.');
    }
}
