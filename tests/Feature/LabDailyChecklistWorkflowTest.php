<?php

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\LabDailyChecklist;
use App\Models\Role;
use App\Models\User;

function makeLabRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => true], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('lab technician can create today\'s checklist and only sees their own in the index', function () {
    $technician = makeLabRoleUser('lab-technician');
    $otherTechnician = makeLabRoleUser('lab-technician');

    $own = LabDailyChecklist::factory()->create(['employee_id' => $technician->id]);
    $other = LabDailyChecklist::factory()->create(['employee_id' => $otherTechnician->id]);

    $response = $this->actingAs($technician)->get(route('lab-checklists.index'));

    $response->assertOk();
    $response->assertSee($own->checklist_no);
    $response->assertDontSee($other->checklist_no);
});

test('a technician cannot view another technician\'s checklist', function () {
    $technician = makeLabRoleUser('lab-technician');
    $otherTechnician = makeLabRoleUser('lab-technician');
    $other = LabDailyChecklist::factory()->create(['employee_id' => $otherTechnician->id]);

    $this->actingAs($technician)->get(route('lab-checklists.show', $other))->assertForbidden();
});

test('creating a checklist for the same employee and date twice is rejected', function () {
    $technician = makeLabRoleUser('lab-technician');
    $date = now()->toDateString();

    LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'checklist_date' => $date]);

    $response = $this->actingAs($technician)->post(route('lab-checklists.store'), [
        'checklist_date' => $date,
        'items' => ['uv_on' => ['done' => 1]],
    ]);

    $response->assertSessionHasErrors('checklist_date');
});

test('storing a checklist creates one item row per catalogue entry, and compliance_score reflects it', function () {
    $technician = makeLabRoleUser('lab-technician');

    $response = $this->actingAs($technician)->post(route('lab-checklists.store'), [
        'checklist_date' => now()->toDateString(),
        'items' => [
            'uv_on' => ['done' => 1, 'time' => '08:00'],
            'sterilizer_on' => ['done' => 1, 'time' => '08:05'],
            'room2_spray' => ['done' => 1, 'time' => '09:00', 'chemical' => 'Sodium Hypochlorite'],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $checklist = LabDailyChecklist::where('employee_id', $technician->id)->first();
    expect($checklist->checklistItems()->count())->toBe(count(LabDailyChecklist::CHECKLIST_ITEMS));
    expect($checklist->checklistItems()->where('item_key', 'uv_on')->first()->time_recorded)->toBe('08:00');
    expect($checklist->checklistItems()->where('item_key', 'room2_spray')->first()->chemical_used)->toBe('Sodium Hypochlorite');

    $done = 3;
    $total = count(LabDailyChecklist::CHECKLIST_ITEMS);
    expect($checklist->compliance_score)->toBe(round(($done / $total) * 100, 2));
});

test('a draft checklist is editable by its owner but locked once pending', function () {
    $technician = makeLabRoleUser('lab-technician');
    $checklist = LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->get(route('lab-checklists.edit', $checklist))->assertOk();

    $checklist->update(['status' => 'pending']);

    $this->actingAs($technician)->get(route('lab-checklists.edit', $checklist))->assertForbidden();
});

test('submit moves a draft checklist to pending and logs an activity entry', function () {
    $technician = makeLabRoleUser('lab-technician');
    $checklist = LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'status' => 'draft']);

    $this->actingAs($technician)->post(route('lab-checklists.submit', $checklist))->assertRedirect();

    expect($checklist->fresh()->status)->toBe('pending');
    expect(ActivityLog::where('module', 'lab-checklists')->where('record_id', $checklist->id)->exists())->toBeTrue();
});

test('supervisor can approve a pending checklist with a signature, which is stored on the approval', function () {
    $technician = makeLabRoleUser('lab-technician');
    $supervisor = makeLabRoleUser('supervisor');
    $checklist = LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'status' => 'pending']);

    $signature = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

    $response = $this->actingAs($supervisor)->post(route('lab-checklists.approve', $checklist), [
        'remarks' => 'Looks good',
        'signature_data' => $signature,
    ]);

    $response->assertRedirect();
    expect($checklist->fresh()->status)->toBe('approved');

    $approval = Approval::forRecord('lab-checklists', $checklist->id)->where('approval_level', Approval::LEVEL_VERIFICATION)->first();
    expect($approval)->not->toBeNull();
    expect($approval->status)->toBe('approved');
    expect($approval->signature)->not->toBeNull();
    expect(\Illuminate\Support\Facades\Storage::disk('public')->exists($approval->signature))->toBeTrue();

    expect($technician->fresh()->notifications()->count())->toBe(1);
});

test('a lab technician cannot approve their own checklist', function () {
    $technician = makeLabRoleUser('lab-technician');
    $checklist = LabDailyChecklist::factory()->create(['employee_id' => $technician->id, 'status' => 'pending']);

    $this->actingAs($technician)->post(route('lab-checklists.approve', $checklist), [
        'signature_data' => 'data:image/png;base64,'.base64_encode('x'),
    ])->assertForbidden();
});

test('rejecting requires a remark and flips status to rejected', function () {
    $supervisor = makeLabRoleUser('supervisor');
    $checklist = LabDailyChecklist::factory()->create(['status' => 'pending']);

    $this->actingAs($supervisor)->post(route('lab-checklists.reject', $checklist), [])->assertSessionHasErrors('remarks');

    $this->actingAs($supervisor)->post(route('lab-checklists.reject', $checklist), ['remarks' => 'Missing UV log'])->assertRedirect();
    expect($checklist->fresh()->status)->toBe('rejected');
});

test('admin can unlock an approved checklist back to draft', function () {
    $admin = makeLabRoleUser('admin');
    $checklist = LabDailyChecklist::factory()->create(['status' => 'approved']);

    $this->actingAs($admin)->post(route('lab-checklists.unlock', $checklist))->assertRedirect();
    expect($checklist->fresh()->status)->toBe('draft');
});

test('a supervisor cannot unlock a checklist', function () {
    $supervisor = makeLabRoleUser('supervisor');
    $checklist = LabDailyChecklist::factory()->create(['status' => 'approved']);

    $this->actingAs($supervisor)->post(route('lab-checklists.unlock', $checklist))->assertForbidden();
});

test('only admin or super-admin can delete a checklist', function () {
    $technician = makeLabRoleUser('lab-technician');
    $admin = makeLabRoleUser('admin');
    $checklist = LabDailyChecklist::factory()->create();

    $this->actingAs($technician)->delete(route('lab-checklists.destroy', $checklist))->assertForbidden();

    $this->actingAs($admin)->delete(route('lab-checklists.destroy', $checklist))->assertRedirect();
    $this->assertSoftDeleted('lab_daily_checklists', ['id' => $checklist->id]);
});
