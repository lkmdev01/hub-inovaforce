<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function show(Request $request): View
    {
        return view('legal.accept', ['user' => $request->user()]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['terms' => ['accepted']]);
        $request->user()->forceFill(['accepted_terms_at' => now()])->save();

        return redirect()->route('dashboard')->with('success', 'Termos aceitos. Bem-vindo ao Hub.');
    }
}
