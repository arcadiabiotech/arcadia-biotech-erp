<?php

use App\Models\Dealer;
use App\Models\Rating;
use App\Models\RatingHistory;
use App\Models\Role;
use App\Models\User;

function ratingRoleUser(string $roleName, array $attributes = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName), 'status' => true]);

    return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

test('super admin can set a manual rating override and it is recorded in history', function () {
    $superAdmin = ratingRoleUser('super-admin');
    $dealer = Dealer::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('ratings.update', ['dealer', $dealer->id]), [
        'score' => 9,
        'reason' => 'Consistently pays on time',
    ]);

    $response->assertRedirect();

    $rating = Rating::where('rateable_type', Dealer::class)->where('rateable_id', $dealer->id)->first();
    expect($rating)->not->toBeNull();
    expect($rating->is_manual_override)->toBeTrue();
    expect($rating->manual_score)->toBe(9);
    expect($rating->manual_star)->toBe(5);
    expect($rating->manual_reason)->toBe('Consistently pays on time');
    expect($rating->manual_by)->toBe($superAdmin->id);

    $history = RatingHistory::where('module', 'dealer')->where('rateable_id', $dealer->id)->first();
    expect($history)->not->toBeNull();
    expect($history->action)->toBe('manual_override');
    expect($history->new_score)->toBe(9);
    expect($history->changed_by)->toBe($superAdmin->id);
});

test('admin (not super admin) is forbidden from setting a manual rating', function () {
    $admin = ratingRoleUser('admin');
    $dealer = Dealer::factory()->create();

    $response = $this->actingAs($admin)->put(route('ratings.update', ['dealer', $dealer->id]), [
        'score' => 9,
        'reason' => 'Should not be allowed',
    ]);

    $response->assertForbidden();
    expect(Rating::where('rateable_type', Dealer::class)->where('rateable_id', $dealer->id)->exists())->toBeFalse();
});

test('reset to auto clears the manual override and appends a second history row', function () {
    $superAdmin = ratingRoleUser('super-admin');
    $dealer = Dealer::factory()->create();

    Rating::create([
        'rateable_type' => Dealer::class,
        'rateable_id' => $dealer->id,
        'auto_score' => 7,
        'auto_star' => 4,
        'is_manual_override' => true,
        'manual_score' => 9,
        'manual_star' => 5,
        'manual_reason' => 'Prior override',
        'manual_by' => $superAdmin->id,
        'manual_at' => now(),
    ]);

    $response = $this->actingAs($superAdmin)->post(route('ratings.reset', ['dealer', $dealer->id]));

    $response->assertRedirect();

    $rating = Rating::where('rateable_type', Dealer::class)->where('rateable_id', $dealer->id)->first();
    expect($rating->is_manual_override)->toBeFalse();
    expect($rating->manual_score)->toBeNull();
    expect($rating->auto_score)->toBe(7);

    expect(RatingHistory::where('module', 'dealer')->where('rateable_id', $dealer->id)->count())->toBe(1);
    expect(RatingHistory::where('module', 'dealer')->where('rateable_id', $dealer->id)->first()->action)->toBe('reset_to_auto');
});

test('manual score outside 0-10 or a missing reason fails validation', function () {
    $superAdmin = ratingRoleUser('super-admin');
    $dealer = Dealer::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('ratings.update', ['dealer', $dealer->id]), [
        'score' => 15,
        'reason' => '',
    ]);

    $response->assertSessionHasErrors(['score', 'reason']);
});
