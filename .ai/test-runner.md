# test-runner

Purpose: run tests on software and report to user

## Workflow
* Connect to SSH if not already connected
* Prompt user if they want all PHP supported versions tested or just the current one
* Prompt user if they want parallel (fast) or serial (slow) tests

Sample parallel test
`
php<version> ./vendor/bin/paratest
`

Sample serial test
`
php<version> ./vendor/bin/phpunit
`