<?php

require __DIR__ . '/vendor/autoload.php';

use Playwright\Playwright;

$username = getenv('DRUPAL_USERNAME');
$password = getenv('DRUPAL_PASSWORD');

if (!$username || !$password) {
    throw new RuntimeException(
        'Set DRUPAL_USERNAME and DRUPAL_PASSWORD environment variables.'
    );
}

$browser = Playwright::chromium([
    'args' => [
        '--host-resolver-rules=MAP drupal10-clean.civicrm.ddev.site 172.19.0.2',
        '--ignore-certificate-errors',
    ],
]);

$page = $browser->newPage();

try {
    echo "EC-006: Verify event type configuration\n";

    // Open Drupal login page.
    $page->goto(
        'https://drupal10-clean.civicrm.ddev.site/user/login'
    );

    $page->locator('#edit-name')->fill($username);
    $page->locator('#edit-pass')->fill($password);

    // Submit the actual Drupal login form.
    $page->locator('form#user-login-form input[name="op"]')->click();

    $page->waitForLoadState('load');

    $loginBody = $page->locator('body')->innerText();

    if (
        stripos($loginBody, 'Access denied') !== false ||
        stripos($loginBody, 'Unrecognized username') !== false ||
        stripos($loginBody, 'The password you entered') !== false
    ) {
        throw new RuntimeException(
            'FAIL: Drupal login failed. Check credentials and permissions.'
        );
    }

    echo "Login URL: " . $page->url() . "\n";

    // Open Event Calendar settings.
    $page->goto(
        'https://drupal10-clean.civicrm.ddev.site/civicrm/eventcalendarsettings?action=update&id=1&reset=1'
    );

    $body = $page->locator('body')->innerText();

    if (
        stripos($body, 'Access denied') !== false ||
        stripos($body, 'You are not authorized') !== false
    ) {
        throw new RuntimeException(
            'FAIL: Event Calendar settings access was denied.'
        );
    }

    echo "PASS: Event Calendar settings page is accessible.\n";

    // Verify all six event types.
    $eventTypes = [
        'Conference',
        'Exhibition',
        'Fundraiser',
        'Meeting',
        'Performance',
        'Workshop',
    ];

    foreach ($eventTypes as $type) {
        if (stripos($body, $type) === false) {
            throw new RuntimeException(
                "FAIL: Event type '$type' was not found."
            );
        }

        echo "PASS: $type event type is displayed.\n";
    }

    // Check that at least six checkboxes are present.
    $checkboxCount = $page->locator(
        'input[type="checkbox"]'
    )->count();

    echo "Checkboxes found: $checkboxCount\n";

    if ($checkboxCount < 6) {
        throw new RuntimeException(
            'FAIL: Fewer than six checkboxes were found.'
        );
    }

    echo "PASS: At least six checkboxes are present.\n";
    echo "EC-006 completed successfully.\n";

} finally {
    $browser->close();
}
