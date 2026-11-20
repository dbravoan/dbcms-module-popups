<?php

declare(strict_types=1);

namespace DbCms\Modules\Popups\Http\Controllers\Admin;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use DbCms\Modules\Popups\Http\Requests\PopupRequest;
use DbCms\Modules\Popups\Services\PopupAdministration;
use Illuminate\Http\RedirectResponse;

final class PopupPostController extends Controller
{
    public function __construct(private readonly PopupAdministration $service) {}

    public function __invoke(PopupRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->service->create($request->validated(), $actor);

        return redirect()->route('admin.popups.index')->with('success', 'Popup creado.');
    }
}
