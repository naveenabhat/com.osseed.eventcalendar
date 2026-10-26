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

    echo "EC-001: Verify Event Calendar visibility\n";

    $body = $page->locator('body')->innerText();

    if (stripos($body, 'Access denied') !== false) {
        throw new RuntimeException('FAIL: Access denied.');
    }

    echo "PASS: Calendar page is accessible.\n";

    // Move from October to November 2026.
    $page->locator('[aria-label="next"]')->click();

    // Read the updated calendar content.
    $body = $page->locator('body')->innerText();

    if (stripos($body, 'campaign') !== false) {
        echo "PASS: Event campaign is visible in the calendar.\n";
    } else {
        echo "FAIL: Event campaign was not found after navigating to the next month.\n";
        echo "Visible calendar text:\n";
        echo $body . "\n";
    }

} finally {
    $browser->close();
}
