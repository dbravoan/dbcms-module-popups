<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Modules\Popups\Services\PopupAdministration;
use Illuminate\Contracts\View\View;

final class PopupCreateGetController extends Controller
{
    public function __construct(private readonly PopupAdministration $service) {}

    public function __invoke(): View
    {
        return view('popups::admin.create', $this->service->formData());
    }
}
