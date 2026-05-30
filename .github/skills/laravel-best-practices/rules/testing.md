# Testing Best Practices

## Project-Specific Test Rules

### All tests
- Create both unit tests and feature tests as applicable for a given class.
- Do NOT create an empty test file when no tests are applicable.
- Do NOT create tests for database migration classes.
- Test method names must be camelCase (e.g. `testItReturnsNullForUnknownIdentifier`).

### Unit tests
- No database access.
- No access to the rest of the application (no HTTP, no Eloquent models, no service providers).
- Test pure logic, static helpers, value objects, and callbacks in isolation.
- Validate callback contracts with `ReflectionFunction` rather than executing destructive callbacks.

### Feature tests
- Have access to the database.
- Database contents are generally static (Bible texts, Bible book lists, cross-reference data, etc.).
- Do NOT INSERT, UPDATE, or DELETE database records without explicit instruction and authorization.
- Read and assert against existing data only.

## Use `LazilyRefreshDatabase` Over `RefreshDatabase`

`RefreshDatabase` migrates once per process and wraps each test in a rolled-back transaction. `LazilyRefreshDatabase` skips even that first migration if the schema is already up to date.

## Use Model Assertions Over Raw Database Assertions

Incorrect: `$this->assertDatabaseHas('users', ['id' => $user->id]);`

Correct: `$this->assertModelExists($user);`

More expressive, type-safe, and fails with clearer messages.

## Use Factory States and Sequences

Named states make tests self-documenting. Sequences eliminate repetitive setup.

Incorrect: `User::factory()->create(['email_verified_at' => null]);`

Correct: `User::factory()->unverified()->create();`

## Use `Exceptions::fake()` to Assert Exception Reporting

Instead of `withoutExceptionHandling()`, use `Exceptions::fake()` to assert the correct exception was reported while the request completes normally.

## Call `Event::fake()` After Factory Setup

Model factories rely on model events (e.g., `creating` to generate UUIDs). Calling `Event::fake()` before factory calls silences those events, producing broken models.

Incorrect: `Event::fake(); $user = User::factory()->create();`

Correct: `$user = User::factory()->create(); Event::fake();`

## Use `recycle()` to Share Relationship Instances Across Factories

Without `recycle()`, nested factories create separate instances of the same conceptual entity.

```php
Ticket::factory()
    ->recycle(Airline::factory()->create())
    ->create();
```