<?php

require __DIR__ . '/vendor/autoload.php';

use Playwright\Playwright;

$browser = Playwright::chromium([
    'args' => [
        '--host-resolver-rules=MAP drupal10-clean.civicrm.ddev.site 172.19.0.3',
        '--ignore-certificate-errors',
    ],
]);

$page = $browser->newPage();

try {
    $url = 'https://drupal10-clean.civicrm.ddev.site/civicrm/showevents?id=1';
    $page->goto($url);

    echo "EC-001: Verify Event Calendar and event visibility\n";

    $body = $page->locator('body')->innerText();

    if (stripos($body, 'Access denied') !== false) {
        throw new RuntimeException('FAIL: Access denied.');
    }

    echo "PASS: Calendar page is accessible.\n";

    if (stripos($body, 'October 2026') === false) {
        throw new RuntimeException('FAIL: Expected October 2026 as the initial calendar month.');
    }

    echo "PASS: October 2026 is displayed initially.\n";

    $page->locator('[aria-label="next"]')->click();

    $body = $page->locator('body')->innerText();

    if (stripos($body, 'campaign') === false) {
        throw new RuntimeException('FAIL: Event campaign was not found in November 2026.');
    }

    echo "PASS: Event campaign is visible in November 2026.\n";

    $eventPage = $browser->newPage();
    $eventPage->goto('https://drupal10-clean.civicrm.ddev.site/civicrm/event/info?id=7');

    $eventDetails = $eventPage->locator('body')->innerText();

    if (stripos($eventDetails, 'November 12th, 2026 12:00 PM') === false) {
        throw new RuntimeException('FAIL: Event start date or time is incorrect.');
    }

    echo "PASS: Event starts on November 12, 2026, at 12:00 PM.\n";

    if (stripos($eventDetails, 'November 13th, 2026 9:00 PM') === false) {
        throw new RuntimeException('FAIL: Event end date or time is incorrect.');
    }

    echo "PASS: Event ends on November 13, 2026, at 9:00 PM.\n";

    $eventPage->close();    if (stripos($body, 'November 2026') === false) {
        throw new RuntimeException('FAIL: Calendar did not navigate to November 2026.');
    }

    echo "PASS: Calendar navigated to November 2026.\n";

    if (stripos($body, 'campaign') === false) {
        throw new RuntimeException('FAIL: Event campaign was not found in November 2026.');
    }

    echo "PASS: Event campaign is visible in November 2026.\n";

} finally {
    $browser->close();
}
