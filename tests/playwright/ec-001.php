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

    if (stripos($body, 'November 2026') === false) {
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
