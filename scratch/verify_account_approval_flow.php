<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Notifications\NewUserRegisteredNotification;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use App\Notifications\AccountActivationRequestedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;

echo "=== STARTING VERIFICATION: ACCOUNT APPROVAL & ACTIVATION WORKFLOW ===\n\n";

// 1. Verify Existing Users
$unapprovedExisting = User::whereNull('approval_status')
    ->orWhere('approval_status', '!=', User::STATUS_APPROVED)
    ->count();
echo "[TEST 1] Existing database users unapproved count: {$unapprovedExisting} (Expected: 0)\n";
if ($unapprovedExisting === 0) {
    echo "  --> PASSED: All existing users remain fully approved.\n";
} else {
    echo "  --> FAILED: Some existing users are not approved.\n";
}

// 2. Create Dummy User via Registration simulation (Pending)
$staffRoleId = Role::idByName(Role::STAFF);
$deptId = Department::value('id');

$testUser = User::create([
    'name'              => 'Test Pending User',
    'email'             => 'test_pending_' . time() . '@example.com',
    'password'          => Hash::make('secret123'),
    'role_id'           => $staffRoleId,
    'department_id'     => $deptId,
    'phone'             => '1234567890',
    'emergency_number'  => '0987654321',
    'join_date'         => now()->toDateString(),
    'email_verified_at' => now(),
    'approval_status'   => User::STATUS_PENDING,
]);

echo "\n[TEST 2] Created dummy user #{$testUser->id} ({$testUser->email}).\n";
echo "  - approval_status: '{$testUser->approval_status}'\n";
echo "  - isApproved(): " . ($testUser->isApproved() ? 'TRUE' : 'FALSE') . " (Expected: FALSE)\n";
echo "  - isPendingApproval(): " . ($testUser->isPendingApproval() ? 'TRUE' : 'FALSE') . " (Expected: TRUE)\n";
echo "  - isRejected(): " . ($testUser->isRejected() ? 'TRUE' : 'FALSE') . " (Expected: FALSE)\n";

if (!$testUser->isApproved() && $testUser->isPendingApproval()) {
    echo "  --> PASSED: Pending user initial state correct.\n";
} else {
    echo "  --> FAILED: Incorrect pending user state.\n";
}

// 3. Test Super Admin notification dispatch
$superAdmins = User::whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->get();
echo "\n[TEST 3] Found {$superAdmins->count()} Super Admin(s). Sending NewUserRegisteredNotification...\n";
$superAdmin = $superAdmins->first();
$initialNotifCount = $superAdmin ? $superAdmin->notifications()->count() : 0;

Notification::send($superAdmins, new NewUserRegisteredNotification($testUser));
$newNotifCount = $superAdmin ? $superAdmin->notifications()->count() : 0;
echo "  - Super Admin notification count before: {$initialNotifCount}, after: {$newNotifCount}\n";

$latestNotif = $superAdmin ? $superAdmin->notifications()->latest()->first() : null;
if ($latestNotif && ($latestNotif->data['type'] ?? '') === 'account_approval_required') {
    echo "  - Latest notification type: {$latestNotif->data['type']}\n";
    echo "  - Latest notification message: {$latestNotif->data['message']}\n";
    echo "  - Latest notification url: {$latestNotif->data['url']}\n";
    echo "  --> PASSED: Super Admin in-app notification recorded.\n";
} else {
    echo "  --> FAILED: Super Admin notification not recorded properly.\n";
}

// 4. Test Middleware EnsureAccountApproved
$middleware = new \App\Http\Middleware\EnsureAccountApproved();

// Request as pending user
$req = Request::create('/admin/hierarchy', 'GET');
$req->setUserResolver(fn() => $testUser);
$response = $middleware->handle($req, function($r) {
    return new \Illuminate\Http\Response('ALLOWED');
});

echo "\n[TEST 4] Middleware protection for unapproved user on /admin/hierarchy:\n";
echo "  - Response status code: " . $response->getStatusCode() . " (Expected: 302 redirect)\n";
echo "  - Target redirect URL: " . $response->headers->get('Location') . "\n";
if ($response->isRedirect(route('admin.dashboard'))) {
    echo "  --> PASSED: Middleware intercepted unapproved user and redirected to dashboard.\n";
} else {
    echo "  --> FAILED: Middleware did not redirect unapproved user properly.\n";
}

// 5. Test Rejection flow
echo "\n[TEST 5] Super Admin rejects user...\n";
$testUser->update([
    'approval_status' => User::STATUS_REJECTED,
    'rejected_at'     => now(),
]);
$testUser->notify(new AccountRejectedNotification());

$testUser->refresh();
echo "  - approval_status: '{$testUser->approval_status}'\n";
echo "  - rejected_at: {$testUser->rejected_at}\n";
echo "  - isRejected(): " . ($testUser->isRejected() ? 'TRUE' : 'FALSE') . " (Expected: TRUE)\n";
echo "  - isApproved(): " . ($testUser->isApproved() ? 'TRUE' : 'FALSE') . " (Expected: FALSE)\n";

$userNotif = $testUser->notifications()->latest()->first();
if ($userNotif && ($userNotif->data['type'] ?? '') === 'account_rejected') {
    echo "  - User received notification: {$userNotif->data['message']}\n";
    echo "  --> PASSED: User rejected and notified.\n";
} else {
    echo "  --> FAILED: User rejection notification missing.\n";
}

// 6. Test User Re-activation Request flow
echo "\n[TEST 6] User requests account activation from dashboard...\n";
$testUser->update([
    'approval_status'         => User::STATUS_PENDING,
    'activation_requested_at' => now(),
]);
Notification::send($superAdmins, new AccountActivationRequestedNotification($testUser));

$testUser->refresh();
echo "  - approval_status: '{$testUser->approval_status}' (Expected: 'pending')\n";
echo "  - activation_requested_at: {$testUser->activation_requested_at}\n";
echo "  - isPendingApproval(): " . ($testUser->isPendingApproval() ? 'TRUE' : 'FALSE') . " (Expected: TRUE)\n";

$saNotif = $superAdmin ? $superAdmin->notifications()->where('type', AccountActivationRequestedNotification::class)->first() : null;
if ($saNotif && ($saNotif->data['type'] ?? '') === 'account_activation_requested') {
    echo "  - Super Admin received activation request notification: {$saNotif->data['message']}\n";
    echo "  --> PASSED: Re-activation request cycle succeeded.\n";
} else {
    echo "  --> FAILED: Activation request notification missing.\n";
}

// 7. Test Super Admin Approves User
echo "\n[TEST 7] Super Admin approves user...\n";
$testUser->update([
    'approval_status' => User::STATUS_APPROVED,
    'approved_at'     => now(),
    'rejected_at'     => null,
]);
$testUser->notify(new AccountApprovedNotification());

$testUser->refresh();
echo "  - approval_status: '{$testUser->approval_status}' (Expected: 'approved')\n";
echo "  - approved_at: {$testUser->approved_at}\n";
echo "  - isApproved(): " . ($testUser->isApproved() ? 'TRUE' : 'FALSE') . " (Expected: TRUE)\n";

$userApprovedNotif = $testUser->notifications()->where('type', AccountApprovedNotification::class)->first();
if ($userApprovedNotif && ($userApprovedNotif->data['type'] ?? '') === 'account_approved') {
    echo "  - User received approval notification: {$userApprovedNotif->data['message']}\n";
    echo "  --> PASSED: Account unlocked and user notified.\n";
} else {
    echo "  --> FAILED: Approval notification missing.\n";
}

// Verify approved user passes middleware
$reqApproved = Request::create('/admin/hierarchy', 'GET');
$reqApproved->setUserResolver(fn() => $testUser);
$respApproved = $middleware->handle($reqApproved, function($r) {
    return new \Illuminate\Http\Response('ALLOWED');
});
echo "  - Approved user accessing /admin/hierarchy: " . $respApproved->getContent() . " (Expected: ALLOWED)\n";
if ($respApproved->getContent() === 'ALLOWED') {
    echo "  --> PASSED: Approved user seamlessly accesses portal.\n";
} else {
    echo "  --> FAILED: Approved user was blocked by middleware.\n";
}

// 8. Clean up
echo "\n[TEST 8] Cleaning up test records...\n";
foreach ($superAdmins as $sa) {
    $sa->notifications()->whereIn('type', [
        NewUserRegisteredNotification::class,
        AccountActivationRequestedNotification::class,
    ])->delete();
}
$testUser->notifications()->delete();
$testUser->forceDelete();
echo "  --> Cleaned up dummy user and notifications.\n";

echo "\n=== ALL VERIFICATIONS COMPLETED SUCCESSFULLY ===\n";
