<?php

namespace Tests\Feature\ItemEnhancements;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorSuppressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_save_suppressed_vendors(): void
    {
        $response = $this->actingAs($this->user)->patch('/settings', [
            'suppressed_vendors' => ['Amazon', 'eBay'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify the setting was saved
        $saved = Setting::get(Setting::SUPPRESSED_VENDORS, $this->user->id);
        $vendors = json_decode($saved, true);
        $this->assertContains('Amazon', $vendors);
        $this->assertContains('eBay', $vendors);
    }

    public function test_user_can_clear_suppressed_vendors(): void
    {
        // First, set some vendors
        Setting::set(Setting::SUPPRESSED_VENDORS, json_encode(['Amazon']), $this->user->id);

        // Then clear them
        $response = $this->actingAs($this->user)->patch('/settings', [
            'suppressed_vendors' => [],
        ]);

        $response->assertRedirect();

        // Verify they were cleared
        $saved = Setting::get(Setting::SUPPRESSED_VENDORS, $this->user->id);
        $vendors = json_decode($saved, true);
        $this->assertEmpty($vendors);
    }

    public function test_suppressed_vendors_appear_in_settings_response(): void
    {
        Setting::set(Setting::SUPPRESSED_VENDORS, json_encode(['Amazon', 'Walmart']), $this->user->id);

        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertInertia(fn ($page) => $page
            ->component('Settings')
            ->has('settings.suppressed_vendors', 2)
            ->where('settings.suppressed_vendors', ['Amazon', 'Walmart'])
        );
    }

    public function test_suppressed_vendors_default_to_empty_array(): void
    {
        $response = $this->actingAs($this->user)->get('/settings');

        $response->assertInertia(fn ($page) => $page
            ->component('Settings')
            ->where('settings.suppressed_vendors', [])
        );
    }
}
