
<?php

require __DIR__ . '/vendor/autoload.php';

use Playwright\Playwright;

$browser = Playwright::chromium([
    'args' => [
        '--host-resolver-rules=MAP drupal10-clean.civicrm.ddev.site 172.19.0.2',
        '--ignore-certificate-errors',
    ],
]);

$page = $browser->newPage();

try {
    $page->goto(
        'https://drupal10-clean.civicrm.ddev.site/civicrm/showevents?id=1'
    );

    echo "EC-002: Verify event click and details\n";

    $page->locator('[aria-label="next"]')->click();

    $event = $page->locator(
        '#calendar a[href="/civicrm/event/info?id=7"]'
    );

    if ($event->count() === 0) {
        throw new RuntimeException(
            'FAIL: Campaign event was not found in November 2026.'
        );
    }

    echo "PASS: Campaign event is visible.\n";

    $event->click();

    $page->waitForURL('**/civicrm/event/info?id=7');

    $details = $page->locator('body')->innerText();

    if (stripos($details, 'November 12th, 2026 12:00 PM') === false) {
        throw new RuntimeException(
            'FAIL: Expected event start date and time were not found.'
        );
    }

    echo "PASS: Clicking the event opens its details page.\n";
    echo "PASS: Event start date and time are correct.\n";

    if (stripos($details, 'November 13th, 2026 9:00 PM') === false) {
        throw new RuntimeException(
            'FAIL: Expected event end date and time were not found.'
        );
    }

    echo "PASS: Event end date and time are correct.\n";

} finally {
    $browser->close();
}

