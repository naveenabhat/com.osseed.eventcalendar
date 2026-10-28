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

$baseUrl = 'https://drupal10-clean.civicrm.ddev.site';
$settingsUrl = $baseUrl .
    '/civicrm/eventcalendarsettings?action=update&id=1&reset=1';

$browser = Playwright::chromium([
    'args' => [
        '--host-resolver-rules=MAP drupal10-clean.civicrm.ddev.site 172.19.0.2',
        '--ignore-certificate-errors',
    ],
]);

$page = $browser->newPage();
$originalShowPastEvents = false;
$settingsLoaded = false;

try {
    echo "EC-007: Verify settings save and persistence\n";

    // Log in to Drupal.
    $page->goto($baseUrl . '/user/login');
    $page->locator('#edit-name')->fill($username);
    $page->locator('#edit-pass')->fill($password);
    $page->locator('form#user-login-form input[name="op"]')->click();
    $page->waitForLoadState('load');

    if (strpos($page->url(), '/user/login') !== false) {
        throw new RuntimeException(
            'FAIL: Drupal login failed. Check the credentials.'
        );
    }

    echo "PASS: Drupal login successful.\n";

    // Open settings and confirm access.
    $page->goto($settingsUrl);

    $body = $page->locator('body')->innerText();

    if (
        stripos($body, 'Access denied') !== false ||
        stripos($body, 'You are not authorized') !== false
    ) {
        throw new RuntimeException(
            'FAIL: Event Calendar settings access denied.'
        );
    }

    $settingsLoaded = true;

    $checkbox = $page->locator(
        '#EventCalendarSettings #show_past_events'
    );

    $originalShowPastEvents = $checkbox->evaluate(
        '(el) => el.checked'
    );

    echo "Original show_past_events state: " .
        ($originalShowPastEvents ? 'checked' : 'unchecked') . "\n";

    // Toggle the setting to the opposite of its original value.
    $checkbox->setChecked(!$originalShowPastEvents);

    // Save settings.
    $page->locator(
        '#EventCalendarSettings button[type="submit"]'
    )->click();

    $page->waitForLoadState('load');

    // Reload settings and verify the new value persisted.
    $page->goto($settingsUrl);

    $savedState = $page->locator(
        '#EventCalendarSettings #show_past_events'
    )->evaluate('(el) => el.checked');

    if ($savedState !== !$originalShowPastEvents) {
        throw new RuntimeException(
            'FAIL: The changed setting did not persist after reload.'
        );
    }

    echo "PASS: Setting change persisted after reload.\n";

    // Restore the original setting value.
    $page->locator(
        '#EventCalendarSettings #show_past_events'
    )->setChecked($originalShowPastEvents);

    $page->locator(
        '#EventCalendarSettings button[type="submit"]'
    )->click();

    $page->waitForLoadState('load');
    $page->goto($settingsUrl);

    $restoredState = $page->locator(
        '#EventCalendarSettings #show_past_events'
    )->evaluate('(el) => el.checked');

    if ($restoredState !== $originalShowPastEvents) {
        throw new RuntimeException(
            'FAIL: Could not confirm the original setting was restored.'
        );
    }

    echo "PASS: Original setting restored and verified.\n";
    echo "EC-007 completed successfully.\n";

} finally {
    $browser->close();
}
