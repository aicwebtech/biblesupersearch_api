<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\User;
use App\Notifications\CustomPasswordReset;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Covers the password reset flow end to end.
 *
 * The reset link previously worked only by accident: password.reset had no
 * {token} segment, so route() emitted a bare keyless query string and
 * showResetForm() recovered the token from the query array's *key*. The route
 * now carries the token properly and the email is passed through.
 */
class PasswordResetFlowTest extends TestCase
{
    /**
     * Create a throwaway user so the flow can be exercised without touching
     * any real account. Removed by the caller in a finally.
     *
     * @return \App\User
     */
    protected function makeUserFixture(): User
    {
        $User = new User();
        $User->name         = 'Reset Fixture';
        $User->username     = 'reset_fixture_' . Str::random(8);
        $User->email        = 'reset_fixture_' . Str::random(8) . '@example.com';
        $User->password     = Hash::make('Or1ginal!Pass');
        $User->access_level = 1;
        $User->save();

        return $User;
    }

    protected function removeUserFixture(?User $User): void
    {
        if($User) {
            Password::broker()->deleteToken($User);
            $User->forceDelete();
        }
    }

    /**
     * The regression Aikido asked for: request a link, follow the generated
     * URL, and complete the POST.
     */
    public function testResetLinkCanBeFollowedAndPasswordChanged(): void
    {
        $User = null;

        try {
            $User = $this->makeUserFixture();
            Notification::fake();

            $this->post('/auth/reset', ['email' => $User->email])->assertStatus(302);

            $token = null;
            Notification::assertSentTo($User, CustomPasswordReset::class, function ($notification) use (&$token) {
                $token = $notification->token;
                return true;
            });

            $this->assertNotEmpty($token, 'No reset token was issued');

            // Follow the emailed link.
            $response = $this->get('/auth/change/' . $token . '?email=' . urlencode($User->email));

            $response->assertStatus(200);
            $response->assertSee($token, false);        // hidden token field populated
            $response->assertSee($User->email, false);  // email prefilled

            // Complete the reset.
            $this->post('/auth/change', [
                'token'                 => $token,
                'email'                 => $User->email,
                'password'              => 'Br4ndNew!Pass',
                'password_confirmation' => 'Br4ndNew!Pass',
            ])->assertStatus(302);

            $this->assertTrue(Hash::check('Br4ndNew!Pass', $User->fresh()->password));
        }
        finally {
            $this->removeUserFixture($User);
        }
    }

    /**
     * The token must live in the path, not as a bare keyless query string.
     */
    public function testResetUrlCarriesTokenAsRouteParameter(): void
    {
        $url = route('password.reset', ['token' => 'TOK123', 'email' => 'a@b.com'], false);

        $this->assertStringContainsString('/auth/change/TOK123', $url);
        $this->assertStringNotContainsString('?TOK123', $url);
    }

    /**
     * An unknown address must be indistinguishable from a known one.
     */
    public function testUnknownEmailProducesIdenticalResponse(): void
    {
        $User = null;

        try {
            $User = $this->makeUserFixture();
            Notification::fake();

            $known = $this->post('/auth/reset', ['email' => $User->email]);
            $unknown = $this->post('/auth/reset', ['email' => 'definitely-not-a-user@example.com']);

            $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
            $this->assertSame(
                $known->headers->get('Location'),
                $unknown->headers->get('Location')
            );

            // The known address still gets a mail; the unknown one does not.
            Notification::assertSentTo($User, CustomPasswordReset::class);
        }
        finally {
            $this->removeUserFixture($User);
        }
    }

    /**
     * The failure response is overridden too, so a broker failure cannot
     * reintroduce the difference.
     */
    public function testUnknownEmailDoesNotFlashUserNotFoundError(): void
    {
        Notification::fake();

        $response = $this->post('/auth/reset', ['email' => 'definitely-not-a-user@example.com']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
    }

    /**
     * The reset request endpoint is rate limited so the generic response cannot
     * be used to probe addresses in bulk.
     */
    public function testResetRequestIsThrottled(): void
    {
        Notification::fake();

        $statuses = [];

        for($i = 0; $i < 7; $i++) {
            $statuses[] = $this->post('/auth/reset', ['email' => 'probe@example.com'])->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'Expected the reset endpoint to start refusing requests');
        $this->assertSame(302, $statuses[0], 'The first request should be accepted');
    }
}
