<?php
/**
 * Plugin Name: Blackfire Integration
 * Plugin URI: https://blackfire.io
 * Description: Blackfire Monitoring integration with Wordpress. Identifies Transactions from incoming requests.
 * Author: Jérôme Vieilledent
 * License: MIT
 */

namespace Blackfire\Bridge\WordPress;

function set_transaction_name($transactionName)
{
    if (!method_exists(\BlackfireProbe::class, 'setTransactionName')) {
        return;
    }

    \BlackfireProbe::setTransactionName($transactionName);
}

// Identify from regular templates
define('CONTENT_DIRNAME', basename(WP_CONTENT_DIR));
add_filter('template_include', function (string $template): string {
    set_transaction_name(substr($template, strpos($template, CONTENT_DIRNAME.'/') + strlen(CONTENT_DIRNAME.'/')));
    return $template;
}, 100);

// Identify from REST API
add_filter('rest_request_before_callbacks', function ($response, array $handler, \WP_REST_Request $request) {
    if (\is_array($handler['callback'])) {
        $callbackName = sprintf(
            "%s::%s",
            \is_object($handler['callback'][0]) ? get_class($handler['callback'][0]) : $handler['callback'][0],
            $handler['callback'][1]
        );
    } else {
        $callbackName = $handler['callback'];
    }

    set_transaction_name(sprintf('WP_REST_API/%s', $callbackName));

    return $response;
}, 10, 3);

// Identify from XML-RPC
add_action('xmlrpc_call', function (string $methodName): void {
    set_transaction_name("XMLRPC/$methodName");
});

// Identify Cronjobs
add_action('wp_loaded', function () {
    if (!(defined('DOING_CRON') && true === DOING_CRON)) {
        return;
    }

    set_transaction_name('wp-cron');
});

$simpleActionHooks = [
    // Authentication related
    'wp_login',
    'wp_logout',
    'user_register',
    // Post related
    'trackback_post',
    'comment_post'
];
foreach ($simpleActionHooks as $actionHook) {
    add_action($actionHook, function () use ($actionHook) {
        set_transaction_name($actionHook);
    });
}

// Admin
add_action('admin_init', function () {
    $transactionName = 'admin';
    if (defined('DOING_AJAX') && true === DOING_AJAX) {
        $transactionName .= '_ajax';
    }

    set_transaction_name($transactionName);
});

// RSS
add_action('rss_tag_pre', function ($context) {
    set_transaction_name("RSS/$context");
});
