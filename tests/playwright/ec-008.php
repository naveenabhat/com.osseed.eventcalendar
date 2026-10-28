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

$browser = Playwright::chromium([
    'args' => [
        '--host-resolver-rules=MAP drupal10-clean.civicrm.ddev.site 172.19.0.2',
        '--ignore-certificate-errors',
    ],
]);

try {
    echo "EC-008: Verify event-type filter visibility\n";

    // Step 1: Log in to Drupal.
    $page = $browser->newPage();
    $page->goto($baseUrl . '/user/login');

    $page->locator('#edit-name')->fill($username);
    $page->locator('#edit-pass')->fill($password);
    $page->locator('form#user-login-form input[name="op"]')->click();
    $page->waitForLoadState('load');

    if (strpos($page->url(), '/user/login') !== false) {
        throw new RuntimeException('FAIL: Drupal login failed.');
    }

    echo "PASS: Drupal login successful.\n";

    // Step 2: Read the saved event-type filter setting.
    $page->goto(
        $baseUrl . '/civicrm/eventcalendarsettings?action=update&id=1&reset=1',
        [
            'waitUntil' => 'commit',
            'timeout' => 15000,
        ]
    );

    $settingsBody = $page->locator('body')->innerText();

    if (
        stripos($settingsBody, 'Access denied') !== false ||
        stripos($settingsBody, 'You are not authorized') !== false
    ) {
        throw new RuntimeException(
            'FAIL: Event Calendar settings access denied.'
        );
    }

    $filterSetting = $page->locator(
        '#EventCalendarSettings #event_type_filters'
    );

    $filterEnabled = $filterSetting->evaluate('(el) => el.checked');

    echo 'Saved Filter Event Types setting: ' .
        ($filterEnabled ? 'enabled' : 'disabled') . "\n";

    // Step 3: Open the public calendar in a fresh page.
    $calendarPage = $browser->newPage();

    $response = $calendarPage->goto(
        $baseUrl . '/civicrm/showevents?id=1',
        [
            'waitUntil' => 'commit',
            'timeout' => 15000,
        ]
    );

    $status = $response ? $response->status() : 0;

    if ($status !== 200) {
        throw new RuntimeException(
            "FAIL: Calendar expected HTTP 200; received {$status}."
        );
    }

    echo "PASS: Calendar returned HTTP 200.\n";

    // Step 4: Inspect the actual rendered calendar and filter selector.
    // The extension's JavaScript references #event_selector.
    $ui = $calendarPage->locator('body')->evaluate(
        "(body) => {
            const calendar = body.querySelector('#calendar');
            const selector = body.querySelector('#event_selector');

            const isVisible = (el) => {
                if (!el || !el.getClientRects().length) {
                    return false;
                }

                const style = getComputedStyle(el);

                return style.display !== 'none'
                    && style.visibility !== 'hidden';
            };

            return {
                calendarExists: !!calendar,
                calendarVisible: isVisible(calendar),
                selectorExists: !!selector,
                selectorVisible: isVisible(selector),
                selectorTag: selector ? selector.tagName : null,
                selectorOptions: selector && selector.options
                    ? Array.from(selector.options).map(option => ({
                        text: option.textContent.trim(),
                        value: option.value
                    }))
                    : []
            };
        }"
    );

    echo "Calendar container found: " .
        ($ui['calendarExists'] ? 'yes' : 'no') . "\n";

    echo "Calendar container visible: " .
        ($ui['calendarVisible'] ? 'yes' : 'no') . "\n";

    echo "Event-type selector found: " .
        ($ui['selectorExists'] ? 'yes' : 'no') . "\n";

    echo "Event-type selector visible: " .
        ($ui['selectorVisible'] ? 'yes' : 'no') . "\n";

    if (!$ui['calendarExists'] || !$ui['calendarVisible']) {
        throw new RuntimeException(
            'FAIL: The public calendar container #calendar '
            . 'was not found or is not visible.'
        );
    }

    echo "PASS: Public calendar container is visible.\n";

    // Step 5: Assert that selector visibility matches the saved setting.
    if ($filterEnabled && !$ui['selectorVisible']) {
        throw new RuntimeException(
            'FAIL: Event-type filters are enabled in settings, '
            . 'but #event_selector is not visible on the public calendar.'
        );
    }

    if (!$filterEnabled && $ui['selectorVisible']) {
        throw new RuntimeException(
            'FAIL: Event-type filters are disabled in settings, '
            . 'but #event_selector is visible on the public calendar.'
        );
    }

    echo "PASS: Event-type filter visibility matches the saved setting.\n";

    if ($ui['selectorExists']) {
        echo "\nEvent-type selector options:\n";
        print_r($ui['selectorOptions']);
    }

    echo "\nEC-008 completed successfully.\n";

} finally {
    $browser->close();
}
