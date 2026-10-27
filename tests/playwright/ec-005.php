
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

    echo "EC-005: Verify event details link\n";

    $page->locator('[aria-label="next"]')->click();

    $event = $page->locator(
        '#calendar a[href="/civicrm/event/info?id=7"]'
    );

    if ($event->count() === 0) {
        throw new RuntimeException(
            'FAIL: Expected event link was not found.'
        );
    }

    echo "PASS: Event link points to event ID 7.\n";

    $event->click();
    $page->waitForURL('**/civicrm/event/info?id=7');

    if (strpos($page->url(), 'id=7') === false) {
        throw new RuntimeException(
            'FAIL: Details page URL does not contain event ID 7.'
        );
    }

    echo "PASS: Correct event details page opened.\n";

} finally {
    $browser->close();
}
