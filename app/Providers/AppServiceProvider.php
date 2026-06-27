<?php

namespace App\Providers;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Single source of truth for password strength. Applied wherever a
        // password is validated via Password::defaults() (registration, reset,
        // install). See AuthController, PasswordController, InstallController.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            if ($event->connection instanceof SQLiteConnection) {
                $pdo = $event->connection->getPdo();

                $this->sqliteCreateFunction($pdo, 'regexp', function ($pattern, $value) {
                    if ($value === null) {
                        return false;
                    }

                    return (bool) preg_match('~' . $pattern . '~iu', $value);
                }, 2);

                // Override SQLite's built-in LIKE to be accent-insensitive (like MySQL utf8mb4_unicode_ci).
                // Exact-case queries bypass this by using GLOB instead of LIKE (see SqlSearch).
                // Cache compiled regexes per pattern (paid once per unique pattern, not per row).
                $likeCache = [];
                $this->sqliteCreateFunction($pdo, 'like', function ($pattern, $value) use (&$likeCache) {
                    if ($value === null) {
                        return false;
                    }

                    $cacheKey = (string) $pattern;

                    if (!array_key_exists($cacheKey, $likeCache)) {
                        $fold = static fn (string $s): string =>
                            preg_replace('/\p{M}/u', '', \Normalizer::normalize(mb_strtolower($s), \Normalizer::NFD));

                        $folded = $fold($pattern);
                        $regex  = '';
                        $len    = mb_strlen($folded);

                        for ($i = 0; $i < $len; $i++) {
                            $char = mb_substr($folded, $i, 1);
                            if ($char === '%') {
                                $regex .= '.*';
                            } elseif ($char === '_') {
                                $regex .= '.';
                            } else {
                                $regex .= preg_quote($char, '~');
                            }
                        }
                        $likeCache[$cacheKey] = '~^' . $regex . '$~su';
                    }

                    $v = (string) $value;
                    // Skip expensive Unicode normalization for ASCII-only values (most English verses)
                    if (preg_match('/[^\x00-\x7F]/', $v)) {
                        $fold = static fn (string $s): string =>
                            preg_replace('/\p{M}/u', '', \Normalizer::normalize(mb_strtolower($s), \Normalizer::NFD));
                        $v = $fold($v);
                    } else {
                        $v = strtolower($v);
                    }

                    return (bool) preg_match($likeCache[$cacheKey], $v);
                }, -1);
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Pdo\Sqlite::createFunction() replaces PDO::sqliteCreateFunction() in PHP 8.4+.
     * PDO::sqliteCreateFunction() is deprecated as of PHP 8.5.
     */
    private function sqliteCreateFunction(\PDO $pdo, string $name, callable $callback, int $argCount = -1): void
    {
        if (method_exists($pdo, 'createFunction')) {
            $pdo->createFunction($name, $callback, $argCount);
            return;
        }

        $pdo->sqliteCreateFunction($name, $callback, $argCount);
    }
}
