<?php
namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase {
    use RefreshDatabase;
    private function platformAdmin(): User { $u = User::factory()->create(); $u->is_platform_admin = true; $u->save(); return $u; }
    public function test_guests_are_redirected_to_login(): void { $this->get('/')->assertRedirect('/login'); }
    public function test_platform_admin_can_create_organization(): void {
        $this->actingAs($this->platformAdmin())->post('/organizations', ['name' => 'Example'])->assertRedirect();
        $this->assertDatabaseHas('organizations', ['name' => 'Example']);
    }
    public function test_org_admin_cannot_access_another_organization(): void {
        $user = User::factory()->create(); $a = Organization::create(['name' => 'A']); $b = Organization::create(['name' => 'B']);
        $a->users()->attach($user->id, ['is_admin' => true]);
        $this->actingAs($user)->get('/organizations/'.$a->id)->assertOk();
        $this->actingAs($user)->get('/organizations/'.$b->id)->assertForbidden();
        $this->actingAs($user)->post('/organizations/'.$b->id.'/units', ['name' => 'Unauthorized'])->assertForbidden();
    }
    public function test_cross_organization_parent_is_rejected(): void {
        $a = Organization::create(['name' => 'A']); $b = Organization::create(['name' => 'B']);
        $parent = $b->units()->create(['name' => 'Other']);
        $this->actingAs($this->platformAdmin())->post('/organizations/'.$a->id.'/units', ['name' => 'Bad', 'parent_id' => $parent->id])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('units', ['name' => 'Bad']);
    }
    public function test_cycles_are_rejected(): void {
        $org = Organization::create(['name' => 'A']); $top = $org->units()->create(['name' => 'Top']); $child = $org->units()->create(['name' => 'Child', 'parent_id' => $top->id]);
        $this->actingAs($this->platformAdmin())->post('/organizations/'.$org->id.'/units/'.$top->id, ['name' => 'Top', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
        $this->assertNull($top->fresh()->parent_id);
    }
    public function test_admin_cannot_assign_organization_admin(): void {
        $org = Organization::create(['name' => 'A']); $user = User::factory()->create(); $org->users()->attach($user->id, ['is_admin' => true]);
        $this->actingAs($user)->post('/organizations/'.$org->id.'/admins', ['name' => 'Other', 'email' => 'other@example.com', 'password' => 'abcdefghijkl'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }
    public function test_non_admin_members_cannot_manage_organization(): void {
        $org = Organization::create(['name' => 'A']); $user = User::factory()->create(); $org->users()->attach($user->id, ['is_admin' => false]);
        $this->actingAs($user)->get('/organizations/'.$org->id)->assertForbidden();
    }
    public function test_branding_is_validated_and_isolated(): void {
        $org = Organization::create(['name' => 'A']); $other = Organization::create(['name' => 'B']);
        $admin = $this->platformAdmin();
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/branding', ['primary_color' => 'bad', 'accent_color' => '#112233'])->assertSessionHasErrors('primary_color');
        $this->actingAs($admin)->post('/organizations/'.$org->id.'/branding', ['primary_color' => '#123456', 'accent_color' => '#112233'])->assertRedirect();
        $this->assertSame('#123456', $org->fresh()->primary_color); $this->assertSame('#2459a6', $other->fresh()->primary_color);
    }
}
