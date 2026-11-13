<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use Illuminate\Contracts\View\View;

class AppProfileController extends Controller
{
    public function show(string $slug): View
    {
        $tool = Tool::query()->published()->where('slug', $slug)->with(['category', 'logo.model'])->firstOrFail();

        return view('nest.apps.show', [
            'tool' => $tool,
            'deals' => $tool->deals()->active()->with('tool.logo.model')->ordered()->get(),
            'alternatives' => $tool->alternatives()->published()->with('logo.model')->limit(6)->get(),
            'rankings' => $tool->rankings()->published()->ordered()->limit(4)->get(),
        ]);
    }
}
