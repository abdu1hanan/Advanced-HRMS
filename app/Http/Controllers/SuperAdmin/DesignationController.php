<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    /**
     * Create a new designation (called inline from user edit/index forms).
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:designations,name'],
        ]);

        $designation = Designation::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'id'   => $designation->id,
                'name' => $designation->name,
            ]);
        }

        return back()->with('success', "Designation \"{$designation->name}\" created.");
    }

    /**
     * Delete a designation only if no users are currently assigned to it.
     */
    public function destroy(Designation $designation): RedirectResponse
    {
        $count = User::where('designation_id', $designation->id)->count();

        if ($count > 0) {
            return back()->withErrors([
                'delete' => "Cannot delete \"{$designation->name}\" — {$count} user(s) currently have this designation.",
            ]);
        }

        $designation->delete();

        return back()->with('success', "Designation \"{$designation->name}\" deleted.");
    }
}
