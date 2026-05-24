<?php

namespace Tests\Unit\Domain\Users\Services;

use App\Domain\Users\Services\UserSessionStateResolver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSessionStateResolverTest extends TestCase
{
    use RefreshDatabase;

    private UserSessionStateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UserSessionStateResolver();
    }

    /**
     * Test user with no login history shows "Tidak login" status.
     */
    public function test_user_with_no_login_history_shows_never_logged_in(): void
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $this->assertEquals('Tidak login', $this->resolver->getStatus($user));
        $this->assertEquals('secondary', $this->resolver->getStatusColor($user));
    }

    /**
     * Test user with recent login shows "Online" status.
     */
    public function test_user_with_recent_login_shows_online(): void
    {
        $now = now(config('app.timezone'));
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $user = User::factory()->create([
            'last_login_at' => $fiveMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $this->assertEquals('Online', $this->resolver->getStatus($user));
        $this->assertEquals('success', $this->resolver->getStatusColor($user));
    }

    /**
     * Test user with expired session shows "Offline" status.
     */
    public function test_user_with_expired_session_shows_offline(): void
    {
        $now = now(config('app.timezone'));
        $sessionLifetime = config('session.lifetime') * 60; // seconds
        $sixtyMinutesAgo = $now->copy()->subSeconds($sessionLifetime + 60);

        $user = User::factory()->create([
            'last_login_at' => $sixtyMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $this->assertEquals('Offline', $this->resolver->getStatus($user));
        $this->assertEquals('warning', $this->resolver->getStatusColor($user));
    }

    /**
     * Test user who logged out shows "Offline" status.
     */
    public function test_user_who_logged_out_shows_offline(): void
    {
        $now = now(config('app.timezone'));
        $oneHourAgo = $now->copy()->subHour();

        $user = User::factory()->create([
            'last_login_at' => $oneHourAgo,
            'last_logout_at' => $oneHourAgo->copy()->addMinutes(30),
               'status' => 'active',
        ]);

        $this->assertEquals('Offline', $this->resolver->getStatus($user));
        $this->assertEquals('warning', $this->resolver->getStatusColor($user));
    }

    /**
     * Test session window calculation with recent login.
     */
    public function test_get_session_window_with_recent_login(): void
    {
        $now = now(config('app.timezone'));
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $user = User::factory()->create([
            'last_login_at' => $fiveMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $window = $this->resolver->getSessionWindow($user);

        $this->assertNotNull($window);
        $this->assertArrayHasKey('start', $window);
        $this->assertArrayHasKey('end', $window);

        // Start should be near the login time
        $this->assertTrue($window['start']->equalTo($fiveMinutesAgo));

        // End should be session lifetime after start
        $expectedEnd = $fiveMinutesAgo->copy()
            ->addSeconds(config('session.lifetime') * 60);
        $this->assertTrue($window['end']->equalTo($expectedEnd));
    }

    /**
     * Test session window returns null for user with no login.
     */
    public function test_get_session_window_returns_null_for_never_logged_in(): void
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $this->assertNull($this->resolver->getSessionWindow($user));
    }

    /**
     * Test description for user who never logged in.
     */
    public function test_description_for_never_logged_in_user(): void
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'last_logout_at' => null,
            'status' => 'active',
        ]);

        $description = $this->resolver->getDescription($user);

        $this->assertStringContainsString('belum pernah login', $description);
    }

    /**
     * Test description for active session.
     */
    public function test_description_for_active_session(): void
    {
        $now = now(config('app.timezone'));
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $user = User::factory()->create([
            'last_login_at' => $fiveMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $description = $this->resolver->getDescription($user);

        $this->assertStringContainsString('Login sejak', $description);
        $this->assertStringContainsString('berakhir', $description);
        $this->assertStringContainsString('tersisa', $description);
    }

    /**
     * Test description for expired session.
     */
    public function test_description_for_expired_session(): void
    {
        $now = now(config('app.timezone'));
        $sessionLifetime = config('session.lifetime') * 60; // seconds
        $oneHourAgo = $now->copy()->subSeconds($sessionLifetime + 60);

        $user = User::factory()->create([
            'last_login_at' => $oneHourAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $description = $this->resolver->getDescription($user);

        $this->assertStringContainsString('berakhir pada', $description);
    }

    /**
     * Test tooltip for active session.
     */
    public function test_tooltip_for_active_session(): void
    {
        $now = now(config('app.timezone'));
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $user = User::factory()->create([
            'last_login_at' => $fiveMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $tooltip = $this->resolver->getTooltip($user);

        $this->assertStringContainsString('WIB', $tooltip);
        $this->assertStringContainsString('-', $tooltip);
    }

    /**
     * Test tooltip for no active session.
     */
    public function test_tooltip_for_no_active_session(): void
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $tooltip = $this->resolver->getTooltip($user);

        $this->assertStringContainsString('Tidak ada sesi', $tooltip);
    }

    /**
     * Test time remaining formatting.
     */
    public function test_format_time_remaining_with_various_values(): void
    {
        // Less than 1 minute
        $this->assertEquals('kurang dari 1 menit', $this->resolver->formatTimeRemaining(0));
        $this->assertEquals('kurang dari 1 menit', $this->resolver->formatTimeRemaining(-5));

        // Minutes only
        $this->assertEquals('30 menit', $this->resolver->formatTimeRemaining(30));
        $this->assertEquals('45 menit', $this->resolver->formatTimeRemaining(45));

        // Hours only
        $this->assertEquals('2 jam', $this->resolver->formatTimeRemaining(120));
        $this->assertEquals('1 jam', $this->resolver->formatTimeRemaining(60));

        // Hours and minutes
        $this->assertEquals('2 jam 30 menit', $this->resolver->formatTimeRemaining(150));
        $this->assertEquals('1 jam 45 menit', $this->resolver->formatTimeRemaining(105));
    }

    /**
     * Test timezone is properly handled.
     */
    public function test_session_calculation_uses_app_timezone(): void
    {
        $now = now(config('app.timezone'));
        $fiveMinutesAgo = $now->copy()->subMinutes(5);

        $user = User::factory()->create([
            'last_login_at' => $fiveMinutesAgo,
            'last_logout_at' => null,
               'status' => 'active',
        ]);

        $window = $this->resolver->getSessionWindow($user);

        $this->assertEquals(
            config('app.timezone'),
            $window['start']->timezone->getName()
        );
        $this->assertEquals(
            config('app.timezone'),
            $window['end']->timezone->getName()
        );
    }
}
