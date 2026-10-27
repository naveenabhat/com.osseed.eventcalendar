
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

    echo "EC-004: Verify previous-month navigation\n";

    $page->locator('[aria-label="next"]')->click();

    $month = $page->locator('#calendar .fc-center h2')->innerText();

    if ($month !== 'November 2026') {
        throw new RuntimeException(
            "FAIL: Expected November 2026, found $month."
        );
    }

    echo "PASS: Calendar navigated to November 2026.\n";

    $page->locator('[aria-label="prev"]')->click();

    $month = $page->locator('#calendar .fc-center h2')->innerText();

    if ($month !== 'October 2026') {
        throw new RuntimeException(
            "FAIL: Expected October 2026, found $month."
        );
    }

    echo "PASS: Previous button returns calendar to October 2026.\n";

} finally {
    $browser->close();
}
