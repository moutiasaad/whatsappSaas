<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression cover for the OTP service settings page.
 *
 * Built manually rather than via `RefreshDatabase` because one of the
 * pre-existing WhatsApp migrations uses raw `ALTER TABLE ... MODIFY`
 * (MySQL-only) and cannot run on the in-memory SQLite test connection —
 * same approach as WebChatLifecycleTest.
 */
class OtpServiceSettingsTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();

        $this->tenant = Tenant::create([
            'name'                => 'Acme',
            'slug'                => 'acme',
            'subscription_status' => 'active',
            'is_active'           => true,
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Tenant Admin',
            'email'     => 'admin@example.com',
            'password'  => 'secret-password',
            'role'      => 'admin',
            'is_active' => true,
            'api_key'   => 'wvd_testkey',
        ]);

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /** The payload a browser sends when the toggle is switched on. */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled'     => '1',
            'code_length' => '6',
            'ttl_minutes' => '10',
            'template'    => 'Your code is {code}, valid {ttl} minutes.',
        ], $overrides);
    }

    /** An unchecked checkbox is simply absent from the form payload. */
    protected function payloadWithoutToggle(): array
    {
        $payload = $this->payload();
        unset($payload['enabled']);

        return $payload;
    }

    public function test_enabling_the_toggle_survives_a_page_reload(): void
    {
        $this->actingAs($this->admin)
            ->put('/tenant-admin/otp-service', $this->payload())
            ->assertRedirect('/tenant-admin/otp-service')
            ->assertSessionHasNoErrors();

        $stored = Tenant::find($this->tenant->id)->settings;

        $this->assertSame(true, $stored['otp']['enabled']);
        $this->assertSame(6, $stored['otp']['code_length']);
        $this->assertSame(10, $stored['otp']['ttl_minutes']);

        // Reloading the page must render the checkbox as checked.
        $this->actingAs($this->admin)
            ->get('/tenant-admin/otp-service')
            ->assertOk()
            ->assertSee('name="enabled" value="1" checked', false);
    }

    public function test_unchecking_the_toggle_persists_false(): void
    {
        $this->actingAs($this->admin)->put('/tenant-admin/otp-service', $this->payload());

        $this->actingAs($this->admin)
            ->put('/tenant-admin/otp-service', $this->payloadWithoutToggle())
            ->assertSessionHasNoErrors();

        $stored = Tenant::find($this->tenant->id)->settings;
        $this->assertSame(false, $stored['otp']['enabled']);

        $this->actingAs($this->admin)
            ->get('/tenant-admin/otp-service')
            ->assertOk()
            ->assertDontSee('name="enabled" value="1" checked', false);
    }

    public function test_saving_otp_settings_leaves_unrelated_tenant_settings_intact(): void
    {
        $this->tenant->forceFill(['settings' => ['branding' => ['color' => '#fff']]])->save();

        $this->actingAs($this->admin)
            ->put('/tenant-admin/otp-service', $this->payload())
            ->assertSessionHasNoErrors();

        $stored = Tenant::find($this->tenant->id)->settings;

        $this->assertSame('#fff', $stored['branding']['color']);
        $this->assertTrue($stored['otp']['enabled']);
    }

    public function test_template_without_the_code_placeholder_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->put('/tenant-admin/otp-service', $this->payload(['template' => 'No placeholder here']))
            ->assertSessionHasErrors('template');

        $this->assertNull(Tenant::find($this->tenant->id)->settings);
    }

    protected function buildSchema(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->string('subscription_status')->default('trial');
            $t->timestamp('subscription_starts_at')->nullable();
            $t->timestamp('subscription_ends_at')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->string('stripe_id')->nullable();
            $t->json('settings')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('remember_token', 100)->nullable();
            $t->string('role')->default('agent');
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_login_at')->nullable();
            $t->string('avatar_url')->nullable();
            $t->string('api_key')->nullable()->unique();
            $t->json('sidebar_permissions')->nullable();
            $t->timestamps();
        });

        Schema::create('whatsapp_instances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('team_id')->nullable()->index();
            $t->string('name');
            $t->string('gateway_instance_id')->nullable();
            $t->string('phone_number')->nullable();
            $t->string('status')->default('disconnected');
            $t->timestamps();
        });

        // The admin layout's sidebar counts unassigned conversations.
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->unsignedBigInteger('whatsapp_instance_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('team_id')->nullable();
            $t->unsignedBigInteger('owner_agent_id')->nullable();
            $t->string('state')->default('pool');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('identifier', 32);
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedTinyInteger('resend_count')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'identifier']);
        });
    }
}
