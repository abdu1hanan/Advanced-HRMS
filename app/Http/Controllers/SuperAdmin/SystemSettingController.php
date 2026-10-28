<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function saveApprovalLevels(Request $request)
    {
        $request->validate([
            'approval_levels' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        SystemSetting::set('approval_levels', $request->approval_levels);

        return back()->with('success', "Approval chain level updated to {$request->approval_levels} level(s). New leave requests will use this setting.");
    }
}
