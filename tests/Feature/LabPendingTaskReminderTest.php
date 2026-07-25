<?php

use App\Models\LabDailyChecklist;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PendingLabTaskNotification;
use Illuminate\Support\Carbon;

function makeReminderRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('a technician with no checklist today gets reminded after the cutoff, and so do supervisors', function () {
    Carbon::setTestNow(Carbon::parse('12:00'));

    $technician = makeReminderRoleUser('lab-technician');
    $supervisor = makeReminderRoleUser('supervisor');

    $this->artisan('lab:send-pending-reminders')->assertSuccessful();

    expect($technician->fresh()->notifications()->where('type', PendingLabTaskNotification::class)->count())->toBe(1);
    expect($supervisor->fresh()->notifications()->where('type', PendingLabTaskNotification::class)->count())->toBe(1);
});

test('the missing-checklist reminder does not duplicate on a second run the same day', function () {
    Carbon::setTestNow(Carbon::parse('12:00'));

    $technician = makeReminderRoleUser('lab-technician');

    $this->artisan('lab:send-pending-reminders');
    $this->artisan('lab:send-pending-reminders');

    expect($technician->fresh()->notifications()->where('type', PendingLabTaskNotification::class)->count())->toBe(1);
});

test('no reminder fires before the cutoff time', function () {
    Carbon::setTestNow(Carbon::parse('09:00'));

    $technician = makeReminderRoleUser('lab-technician');

    $this->artisan('lab:send-pending-reminders');

    expect($technician->fresh()->notifications()->count())->toBe(0);
});

test('a checklist pending for over 6 hours notifies supervisors, a fresh one does not', function () {
    Carbon::setTestNow(Carbon::parse('09:00'));
    $supervisor = makeReminderRoleUser('supervisor');

    $stale = LabDailyChecklist::factory()->create(['status' => 'pending']);
    $stale->timestamps = false;
    $stale->updated_at = Carbon::now()->subHours(7);
    $stale->save();

    $fresh = LabDailyChecklist::factory()->create(['status' => 'pending']);

    $this->artisan('lab:send-pending-reminders');

    $reasons = $supervisor->fresh()->notifications()->where('type', PendingLabTaskNotification::class)->get()->pluck('data.record_id')->all();

    expect($reasons)->toContain($stale->id);
    expect($reasons)->not->toContain($fresh->id);
});
