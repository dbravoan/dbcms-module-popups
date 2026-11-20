<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Modules\Popups\Models\Popup;
use DbCms\Modules\Popups\Services\PopupAdministration;
use Illuminate\Contracts\View\View;

final class PopupEditGetController extends Controller
{
    public function __construct(private readonly PopupAdministration $service) {}

    public function __invoke(Popup $popup): View
    {
        return view('popups::admin.edit', $this->service->formData($popup));
    }
}
