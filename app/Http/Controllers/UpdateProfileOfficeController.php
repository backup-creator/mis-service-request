<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateProfileOfficeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where('location_id', $request->input('location_id')),
            ],
        ]);

        $request->user()->update($validated);

        return back()->with('success', 'Your campus and department have been saved.');
    }
}
