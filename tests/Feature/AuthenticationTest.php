<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_local_part_of_an_email_gets_the_corporate_suffix(): void
    {
        $user = User::factory()->create(['email' => 'alfi@bpe.com.my']);

        $this->post('/login', ['email' => 'alfi', 'password' => 'Costflow@123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_non_corporate_email_is_rejected(): void
    {
        $this->post('/login', ['email' => 'someone@gmail.com', 'password' => 'Costflow@123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unverified_user_cannot_sign_in(): void
    {
        User::factory()->unverified()->create(['email' => 'new@bpe.com.my']);

        $this->post('/login', ['email' => 'new', 'password' => 'Costflow@123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_three_wrong_passwords_lock_the_account(): void
    {
        $user = User::factory()->create(['email' => 'ira@bpe.com.my']);

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => 'ira', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->assertTrue($user->refresh()->isLocked());
    }

    public function test_a_locked_account_refuses_even_the_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'ira@bpe.com.my']);
        $user->forceFill(['locked_until' => now()->addMinutes(5)])->save();

        $this->post('/login', ['email' => 'ira', 'password' => 'Costflow@123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_successful_sign_in_clears_the_failure_counter(): void
    {
        $user = User::factory()->create(['email' => 'alfi@bpe.com.my', 'failed_attempts' => 2]);

        $this->post('/login', ['email' => 'alfi', 'password' => 'Costflow@123']);

        $this->assertSame(0, $user->refresh()->failed_attempts);
    }

    public function test_registering_emails_a_verification_link_and_does_not_sign_the_user_in(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Nur Aisyah Rahman',
            'role' => 'engineer',
            'phone' => '012-3456789',
            'email' => 'nurul',
            'password' => 'Costflow@123',
            'password_confirmation' => 'Costflow@123',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();

        $user = User::where('email', 'nurul@bpe.com.my')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('0123456789', $user->phone);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_weak_password_is_rejected_at_registration(): void
    {
        $this->post('/register', [
            'name' => 'Weak Person',
            'role' => 'engineer',
            'phone' => '0123456789',
            'email' => 'weak',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@bpe.com.my']);
    }

    public function test_a_one_time_code_signs_a_user_in_and_cannot_be_replayed(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'alfi@bpe.com.my']);

        $this->post('/otp', ['email' => 'alfi'])->assertRedirect(route('otp.create'));

        $code = null;
        Notification::assertSentTo($user, LoginCodeNotification::class, function ($notification) use (&$code) {
            $code = (new \ReflectionClass($notification))->getProperty('code')->getValue($notification);

            return true;
        });

        $this->post('/otp/verify', ['email' => 'alfi', 'code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Replaying the same code must fail.
        $this->post('/logout');
        $this->post('/otp/verify', ['email' => 'alfi', 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_a_wrong_one_time_code_is_rejected(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'alfi@bpe.com.my']);

        $this->post('/otp', ['email' => 'alfi']);
        $this->post('/otp/verify', ['email' => 'alfi', 'code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_requesting_a_code_for_an_unknown_account_reveals_nothing(): void
    {
        Notification::fake();

        $this->post('/otp', ['email' => 'ghost'])
            ->assertRedirect(route('otp.create'))
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }
}
