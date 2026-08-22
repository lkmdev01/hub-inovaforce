<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCustomerGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:customer_groups,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'in:violet,blue,emerald,amber,red,zinc'],
        ]);

        CustomerGroup::query()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'active' => true,
        ]);

        return back()->with('success', 'Grupo de clientes criado.');
    }

    public function destroy(CustomerGroup $group): RedirectResponse
    {
        if ($group->customers()->exists()) {
            return back()->with('warning', 'Mova os clientes deste grupo antes de excluí-lo.');
        }

        $group->delete();

        return back()->with('success', 'Grupo removido.');
    }
}
