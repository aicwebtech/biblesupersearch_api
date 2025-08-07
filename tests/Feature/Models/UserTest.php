<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;

use App\User;

class UserTest extends TestCase
{
    public function testOneUser() 
    {
        $users = \DB::table('users')->get();

        // Make sure there is at leste ONE user in the system
        $this->assertGreaterThanOrEqual(1, count($users));
    }

    // AI generated test does not work
    public function _testSendPasswordResetNotification()
    {
        Notification::fake();

        $user = User::find(1); // Assuming a user with ID 1 exists
        //$user = new User(['email' => 'test@example.com']);
        $token = 'dummy-token';

        $user->sendPasswordResetNotification($token);

        Notification::assertSentTo(
            $user,
            CustomPasswordReset::class,
            function ($notification, $channels) use ($user, $token) {
                return $notification->token === $token && $notification->user === $user;
            }
        );
    }
}
