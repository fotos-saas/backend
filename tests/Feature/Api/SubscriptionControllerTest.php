<?php

namespace Tests\Feature\Api;

use App\Models\Partner;
use App\Models\User;
use App\Services\Subscription\PartnerRegistrationService;
use App\Services\Subscription\SubscriptionStripeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * SubscriptionController Feature Tesztek
 *
 * Előfizetés kezelés API végpontok tesztelése.
 *
 * FONTOS: DatabaseTransactions használata RefreshDatabase HELYETT!
 * FONTOS: Stripe API hívások mock-olva vannak!
 */
class SubscriptionControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'partner@example.com',
        ]);
        $this->user->assignRole('partner');

        $this->partner = Partner::create([
            'user_id' => $this->user->id,
            'company_name' => 'Test Studio Kft.',
            'tax_number' => '12345678-1-12',
            'billing_country' => 'HU',
            'billing_postal_code' => '1234',
            'billing_city' => 'Budapest',
            'billing_address' => 'Test utca 1.',
            'phone' => '+36301234567',
            'plan' => 'alap',
            'billing_cycle' => 'monthly',
            'subscription_status' => 'active',
            'subscription_started_at' => now(),
            'stripe_customer_id' => 'cus_test123',
            'stripe_subscription_id' => 'sub_test123',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: Token létrehozása és authentikáció beállítása
     */
    protected function actingAsPartner(): void
    {
        $token = $this->user->createToken('auth-token');
        $this->actingAs($this->user->withAccessToken($token->accessToken), 'sanctum');
    }

    // ==================== CREATE CHECKOUT SESSION TESZTEK ====================

    public function test_create_checkout_session_validation_errors(): void
    {
        $response = $this->postJson('/api/subscription/checkout', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123', // Túl rövid
            'billing' => [],
            'plan' => 'invalid_plan',
            'billing_cycle' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'billing.company_name',
                'billing.country',
                'plan',
                'billing_cycle',
            ]);
    }

    public function test_create_checkout_session_email_unique(): void
    {
        // Meglévő email cím
        $response = $this->postJson('/api/subscription/checkout', [
            'name' => 'Új Partner',
            'email' => 'partner@example.com', // Már létezik
            'password' => 'SecurePass123',
            'billing' => [
                'company_name' => 'Új Cég Kft.',
                'country' => 'HU',
                'postal_code' => '1234',
                'city' => 'Budapest',
                'address' => 'Test utca 2.',
                'phone' => '+36301234568',
            ],
            'plan' => 'alap',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_checkout_session_with_valid_data(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn((object) [
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/session/test',
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        // Mock registration service
        $mockRegistrationService = Mockery::mock(PartnerRegistrationService::class);
        $mockRegistrationService->shouldReceive('prepareRegistration')
            ->once()
            ->andReturn('reg_token_123');

        $this->app->instance(PartnerRegistrationService::class, $mockRegistrationService);

        $response = $this->postJson('/api/subscription/checkout', [
            'name' => 'Új Partner',
            'email' => 'new-partner@example.com',
            'password' => 'SecurePass123',
            'billing' => [
                'company_name' => 'Új Cég Kft.',
                'tax_number' => '87654321-1-12',
                'country' => 'HU',
                'postal_code' => '1234',
                'city' => 'Budapest',
                'address' => 'Test utca 2.',
                'phone' => '+36301234568',
            ],
            'plan' => 'alap',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'checkout_url',
                'session_id',
            ])
            ->assertJson([
                'session_id' => 'cs_test_123',
            ]);
    }

    // ==================== COMPLETE REGISTRATION TESZTEK ====================

    public function test_complete_registration_with_invalid_session(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->andReturn((object) [
                'status' => 'open', // Még nem fizetett
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/complete', [
            'session_id' => 'cs_test_invalid',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'A fizetés még nem fejeződött be.',
            ]);
    }

    public function test_complete_registration_with_missing_token(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->andReturn((object) [
                'status' => 'complete',
                'metadata' => (object) [
                    'registration_token' => null, // Nincs token
                ],
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/complete', [
            'session_id' => 'cs_test_no_token',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Érvénytelen munkamenet.',
            ]);
    }

    public function test_complete_registration_with_expired_token(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->andReturn((object) [
                'status' => 'complete',
                'metadata' => (object) [
                    'registration_token' => 'expired_token',
                ],
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        // Mock registration service - lejárt token
        $mockRegistrationService = Mockery::mock(PartnerRegistrationService::class);
        $mockRegistrationService->shouldReceive('getRegistrationData')
            ->once()
            ->with('expired_token')
            ->andReturn(null);

        $this->app->instance(PartnerRegistrationService::class, $mockRegistrationService);

        $response = $this->postJson('/api/subscription/complete', [
            'session_id' => 'cs_test_expired',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'A regisztrációs adatok lejártak. Kérjük, kezdd újra a regisztrációt.',
            ]);
    }

    public function test_complete_registration_successful(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->andReturn((object) [
                'status' => 'complete',
                'metadata' => (object) [
                    'registration_token' => 'valid_token',
                ],
                'customer' => 'cus_new123',
                'subscription' => 'sub_new123',
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        // Mock registration service
        $mockRegistrationService = Mockery::mock(PartnerRegistrationService::class);
        $mockRegistrationService->shouldReceive('getRegistrationData')
            ->once()
            ->with('valid_token')
            ->andReturn([
                'name' => 'Új Partner',
                'email' => 'new-complete@example.com',
                'plan' => 'alap',
            ]);

        $mockRegistrationService->shouldReceive('isEmailRegistered')
            ->once()
            ->with('new-complete@example.com')
            ->andReturn(false);

        $newUser = User::factory()->create(['email' => 'new-complete@example.com']);
        $newPartner = Partner::create([
            'user_id' => $newUser->id,
            'company_name' => 'Új Cég',
            'plan' => 'alap',
        ]);

        $mockRegistrationService->shouldReceive('createPartnerWithUser')
            ->once()
            ->andReturn(['user' => $newUser, 'partner' => $newPartner]);

        $mockRegistrationService->shouldReceive('clearRegistrationCache')
            ->once()
            ->with('valid_token');

        $this->app->instance(PartnerRegistrationService::class, $mockRegistrationService);

        $response = $this->postJson('/api/subscription/complete', [
            'session_id' => 'cs_test_valid',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJson([
                'message' => 'Sikeres regisztráció! Most már bejelentkezhetsz.',
            ]);
    }

    // ==================== GET SUBSCRIPTION TESZTEK ====================

    public function test_get_subscription_requires_auth(): void
    {
        $response = $this->getJson('/api/subscription');

        $response->assertStatus(401);
    }

    public function test_get_subscription_returns_details(): void
    {
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('getSubscriptionDetails')
            ->once()
            ->with('sub_test123')
            ->andReturn([
                'current_period_end' => now()->addMonth()->timestamp,
                'cancel_at_period_end' => false,
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->getJson('/api/subscription');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'plan',
                'plan_name',
                'billing_cycle',
                'status',
                'started_at',
                'features',
                'limits',
                'usage',
                'prices',
            ])
            ->assertJson([
                'plan' => 'alap',
                'status' => 'active',
            ]);
    }

    public function test_get_subscription_returns_404_for_non_partner(): void
    {
        // User partner profil nélkül
        $regularUser = User::factory()->create();
        $token = $regularUser->createToken('auth-token');
        $this->actingAs($regularUser->withAccessToken($token->accessToken), 'sanctum');

        $response = $this->getJson('/api/subscription');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Partner profil nem található.',
            ]);
    }

    // ==================== CREATE PORTAL SESSION TESZTEK ====================

    public function test_create_portal_session_requires_auth(): void
    {
        $response = $this->postJson('/api/subscription/portal');

        $response->assertStatus(401);
    }

    public function test_create_portal_session_without_subscription(): void
    {
        // Partner Stripe ID nélkül
        $this->partner->update(['stripe_customer_id' => null]);
        $this->actingAsPartner();

        $response = $this->postJson('/api/subscription/portal');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Nincs aktív előfizetésed.',
            ]);
    }

    public function test_create_portal_session_successful(): void
    {
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('createPortalSession')
            ->once()
            ->andReturn((object) [
                'url' => 'https://billing.stripe.com/session/test',
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/portal');

        $response->assertStatus(200)
            ->assertJsonStructure(['portal_url'])
            ->assertJson([
                'portal_url' => 'https://billing.stripe.com/session/test',
            ]);
    }

    // ==================== CANCEL SUBSCRIPTION TESZTEK ====================

    public function test_cancel_subscription_requires_auth(): void
    {
        $response = $this->postJson('/api/subscription/cancel');

        $response->assertStatus(401);
    }

    public function test_cancel_subscription_without_active_subscription(): void
    {
        $this->partner->update(['stripe_subscription_id' => null]);
        $this->actingAsPartner();

        $response = $this->postJson('/api/subscription/cancel');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Nincs aktív előfizetésed.',
            ]);
    }

    public function test_cancel_subscription_successful(): void
    {
        $this->actingAsPartner();

        $cancelAt = now()->addMonth()->timestamp;

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('cancelAtPeriodEnd')
            ->once()
            ->with('sub_test123')
            ->andReturn((object) [
                'current_period_end' => $cancelAt,
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/cancel');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'cancel_at',
            ]);

        // Ellenőrizzük, hogy a státusz frissült
        $this->assertEquals('canceling', $this->partner->fresh()->subscription_status);
    }

    // ==================== RESUME SUBSCRIPTION TESZTEK ====================

    public function test_resume_subscription_successful(): void
    {
        $this->partner->update(['subscription_status' => 'canceling']);
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('resumeSubscription')
            ->once()
            ->with('sub_test123');

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/resume');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Az előfizetésed újra aktív!',
            ]);

        $this->assertEquals('active', $this->partner->fresh()->subscription_status);
    }

    // ==================== PAUSE SUBSCRIPTION TESZTEK ====================

    public function test_pause_subscription_already_paused(): void
    {
        $this->partner->update(['subscription_status' => 'paused']);
        $this->actingAsPartner();

        $response = $this->postJson('/api/subscription/pause');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Az előfizetésed már szüneteltetve van.',
            ]);
    }

    public function test_pause_subscription_successful(): void
    {
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('pauseSubscription')
            ->once()
            ->with(Mockery::type(Partner::class));

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/pause');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'paused_price',
            ]);

        $freshPartner = $this->partner->fresh();
        $this->assertEquals('paused', $freshPartner->subscription_status);
        $this->assertNotNull($freshPartner->paused_at);
    }

    // ==================== UNPAUSE SUBSCRIPTION TESZTEK ====================

    public function test_unpause_subscription_not_paused(): void
    {
        $this->actingAsPartner();

        $response = $this->postJson('/api/subscription/unpause');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Az előfizetésed nincs szüneteltetve.',
            ]);
    }

    public function test_unpause_subscription_successful(): void
    {
        $this->partner->update([
            'subscription_status' => 'paused',
            'paused_at' => now()->subWeek(),
        ]);
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('unpauseSubscription')
            ->once()
            ->with(Mockery::type(Partner::class));

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/unpause');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Az előfizetésed újra aktív!',
            ]);

        $freshPartner = $this->partner->fresh();
        $this->assertEquals('active', $freshPartner->subscription_status);
        $this->assertNull($freshPartner->paused_at);
    }

    // ==================== GET INVOICES TESZTEK ====================

    public function test_get_invoices_requires_auth(): void
    {
        $response = $this->getJson('/api/subscription/invoices');

        $response->assertStatus(401);
    }

    public function test_get_invoices_without_customer_id(): void
    {
        $this->partner->update(['stripe_customer_id' => null]);
        $this->actingAsPartner();

        $response = $this->getJson('/api/subscription/invoices');

        $response->assertStatus(200)
            ->assertJson([
                'invoices' => [],
                'has_more' => false,
            ]);
    }

    public function test_get_invoices_successful(): void
    {
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('getInvoices')
            ->once()
            ->andReturn((object) [
                'data' => [
                    (object) [
                        'id' => 'in_123',
                        'number' => 'INV-001',
                        'amount_paid' => 9900,
                        'currency' => 'huf',
                        'status' => 'paid',
                        'created' => now()->timestamp,
                        'invoice_pdf' => 'https://stripe.com/invoice.pdf',
                        'hosted_invoice_url' => 'https://stripe.com/invoice',
                    ],
                ],
                'has_more' => false,
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->getJson('/api/subscription/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'invoices' => [
                    '*' => [
                        'id',
                        'number',
                        'amount',
                        'currency',
                        'status',
                        'created_at',
                        'pdf_url',
                        'hosted_url',
                    ],
                ],
                'has_more',
            ]);
    }

    public function test_get_invoices_with_pagination(): void
    {
        $this->actingAsPartner();

        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('getInvoices')
            ->once()
            ->withArgs(function ($customerId, $params) {
                return $customerId === 'cus_test123'
                    && $params['limit'] === 10
                    && $params['starting_after'] === 'in_previous';
            })
            ->andReturn((object) [
                'data' => [],
                'has_more' => false,
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->getJson('/api/subscription/invoices?per_page=10&starting_after=in_previous');

        $response->assertStatus(200);
    }

    // ==================== VERIFY SESSION TESZTEK ====================

    public function test_verify_session_validation(): void
    {
        $response = $this->postJson('/api/subscription/verify', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_verify_session_with_invalid_session(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('verifySession')
            ->once()
            ->andThrow(new \Exception('Invalid session'));

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/verify', [
            'session_id' => 'invalid_session',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Érvénytelen munkamenet.',
            ]);
    }

    public function test_verify_session_successful(): void
    {
        // Mock Stripe service
        $mockStripeService = Mockery::mock(SubscriptionStripeService::class);
        $mockStripeService->shouldReceive('verifySession')
            ->once()
            ->with('cs_valid_123')
            ->andReturn([
                'status' => 'complete',
                'payment_status' => 'paid',
            ]);

        $this->app->instance(SubscriptionStripeService::class, $mockStripeService);

        $response = $this->postJson('/api/subscription/verify', [
            'session_id' => 'cs_valid_123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'complete',
                'payment_status' => 'paid',
            ]);
    }
}
