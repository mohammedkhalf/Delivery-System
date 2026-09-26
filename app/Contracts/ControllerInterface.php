<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

interface ControllerInterface
{
    /**
     * This is the starting method of a Controller
     * @param Request|null $request
     * @return View|Collection|LengthAwarePaginator|RedirectResponse|null
     */
    public function index(?Request $request):View|Collection|LengthAwarePaginator|RedirectResponse|null;
}
