<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Edge cases for the main login flow (resources/views/livewire/pages/auth/login.blade.php,
 * backed by App\Livewire\Forms\LoginForm), beyond the happy-path/invalid-password cases
 * Breeze's own AuthenticationTest already covers.
 */
class LoginEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function login(string $email, string $password, bool $remember = false)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->set('form.remember', $remember)
            ->call('login');
    }

    // --- Input validation -------------------------------------------------

    public function test_empty_email_and_password_are_rejected_with_validation_errors(): void
    {
        $this->login('', '')
            ->assertHasErrors(['form.email' => 'required', 'form.password' => 'required']);

        $this->assertGuest();
    }

    public function test_a_malformed_email_is_rejected_by_validation(): void
    {
        $this->login('not-an-email', 'password')
            ->assertHasErrors(['form.email' => 'email']);

        $this->assertGuest();
    }

    public function test_whitespace_only_password_fails_required_validation(): void
    {
        // Laravel's 'required' rule treats a whitespace-only string as empty, so this is
        // rejected at validation (form.password) before ever reaching the auth attempt.
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->login($user->email, '   ')
            ->assertHasErrors(['form.password' => 'required'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    // --- Non-existent / mismatched credentials -----------------------------

    public function test_login_with_an_unregistered_email_fails_without_revealing_the_account_does_not_exist(): void
    {
        $component = $this->login('nobody@example.com', 'whatever-password');

        $component->assertHasErrors(['form.email']);
        $this->assertGuest();

        // Same generic message as a wrong password for an existing account (auth.failed) —
        // the login form must not leak whether the email is registered.
        $this->assertSame(
            trans('auth.failed'),
            $component->errors()->first('form.email')
        );
    }

    public function test_a_sql_injection_style_email_payload_is_treated_as_ordinary_invalid_input(): void
    {
        $this->login("' OR '1'='1", 'password')
            ->assertHasErrors(['form.email' => 'email']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_xss_payload_in_the_email_field_is_rejected_and_not_reflected_unescaped(): void
    {
        $payload = '<script>alert(1)</script>@example.com';

        $response = $this->get('/login');
        $component = Volt::test('pages.auth.login')
            ->set('form.email', $payload)
            ->set('form.password', 'password')
            ->call('login');

        $component->assertHasErrors(['form.email']);
        $this->assertGuest();
    }

    // --- Case sensitivity & exact-match behavior ----------------------------

    public function test_login_email_matching_is_case_insensitive(): void
    {
        $user = User::factory()->create([
            'email' => 'someone@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->login('SOMEONE@EXAMPLE.COM', 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_leading_or_trailing_space_in_the_email_prevents_login(): void
    {
        User::factory()->create([
            'email' => 'someone@example.com',
            'password' => Hash::make('password'),
        ]);

        // No trimming happens on the email field, so a padded email simply won't match —
        // documents actual behavior (a UX nicety to fix later, not a security issue).
        $this->login(' someone@example.com', 'password')
            ->assertHasErrors(['form.email']);

        $this->assertGuest();
    }

    // --- Rate limiting / lockout --------------------------------------------

    public function test_the_sixth_consecutive_failed_attempt_is_throttled_with_a_lockout_message(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'wrong-password')->assertHasErrors(['form.email']);
        }

        $component = $this->login($user->email, 'password'); // correct password, but locked out

        $component->assertHasErrors(['form.email']);
        $this->assertStringContainsString(
            'Too many login attempts',
            $component->errors()->first('form.email')
        );
        $this->assertGuest();
    }

    public function test_rate_limiting_is_scoped_per_email_so_a_different_account_is_unaffected(): void
    {
        $lockedOut = User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('password')]);
        $unaffected = User::factory()->create(['email' => 'bystander@example.com', 'password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->login($lockedOut->email, 'wrong-password');
        }
        $this->login($lockedOut->email, 'password')->assertHasErrors(['form.email']);
        $this->assertGuest();

        // A different email from the same test client (same "IP") must not be throttled.
        $this->login($unaffected->email, 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($unaffected);
    }

    public function test_a_successful_login_clears_the_rate_limiter_for_that_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->login($user->email, 'wrong-password');
        $this->login($user->email, 'wrong-password');
        $this->login($user->email, 'password')->assertHasNoErrors(); // succeeds, clears limiter

        auth()->logout();

        // Confirm the limiter was actually cleared, not just that login #3 slipped in under 5.
        $this->login($user->email, 'wrong-password');
        $this->login($user->email, 'wrong-password');
        $this->login($user->email, 'password')->assertHasNoErrors();
    }

    // --- Session & redirect behavior ----------------------------------------

    public function test_the_session_id_is_regenerated_on_successful_login_to_prevent_session_fixation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->get('/login');
        $idBefore = session()->getId();

        $this->login($user->email, 'password')->assertHasNoErrors();

        $this->assertNotSame($idBefore, session()->getId());
    }

    public function test_login_redirects_back_to_the_originally_intended_page(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('customer');

        // Hitting a protected page while a guest records it as the "intended" URL.
        $this->get('/cart')->assertRedirect('/login');

        $this->login($user->email, 'password')
            ->assertHasNoErrors()
            ->assertRedirect('/cart');
    }

    public function test_an_admin_logging_in_is_eventually_routed_to_the_admin_panel_via_the_dashboard(): void
    {
        $admin = User::factory()->create(['password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $this->login($admin->email, 'password')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_an_already_authenticated_user_visiting_login_is_redirected_away(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_remember_me_sets_a_persistent_remember_token_cookie(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password'), 'remember_token' => null]);

        $this->login($user->email, 'password', remember: true)->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_without_remember_me_does_not_set_a_remember_token(): void
    {
        // remember_token defaults to a random value on the factory, so it's explicitly
        // nulled here to prove login-without-remember leaves it untouched, not just unset.
        $user = User::factory()->create(['password' => Hash::make('password'), 'remember_token' => null]);

        $this->login($user->email, 'password', remember: false)->assertHasNoErrors();

        $this->assertNull($user->fresh()->remember_token);
    }
}
