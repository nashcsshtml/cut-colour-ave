<?php

defined('ABSPATH') or die('No script kiddies please!');

/** @var \AmeliaVendor\Melograno\UsageTracker\Collectors\ConsentNoticeCollectorInterface $usageTrackingCollector */
$usageTrackingCollector = $usageTrackingCollector ?? null;
$usageTrackingAjaxPrefix = $usageTrackingCollector !== null
    ? $usageTrackingCollector->getConsentNoticeAjaxPrefix()
    : 'amelia';
$usageTrackingConsentNonce = wp_create_nonce($usageTrackingAjaxPrefix . '_usage_tracking_consent');
?>
<div id="melograno-usage-tracking-notice" class="melograno-usage-tracking-notice melograno-usage-tracking-notice--pending">
<div class="am-usage-tracking-banner" role="region" aria-label="<?php esc_attr_e('Usage tracking notice', 'wpamelia'); ?>">
    <div class="am-usage-tracking-banner__bar" aria-hidden="true"></div>

    <div class="am-usage-tracking-banner__inner">
        <div class="am-usage-tracking-banner__brand">
            <img
                src="<?php echo esc_url(AMELIA_URL . 'public/img/amelia-logo-admin-icon.svg'); ?>"
                alt="<?php esc_attr_e('Amelia', 'wpamelia'); ?>"
                width="32"
                height="32"
            />
        </div>

        <div class="am-usage-tracking-banner__content">
            <p class="am-usage-tracking-banner__title">
                <?php echo esc_html(\AmeliaBooking\Infrastructure\WP\Translations\BackendStrings::get('improve_amelia')); ?>
            </p>
            <p class="am-usage-tracking-banner__description">
                <?php echo esc_html(\AmeliaBooking\Infrastructure\WP\Translations\BackendStrings::get('usage_tracking_description')); ?>
            </p>
            <div class="am-usage-tracking-banner__actions">
                <button type="button" class="am-usage-tracking-banner__enable">
                    <?php echo esc_html(\AmeliaBooking\Infrastructure\WP\Translations\BackendStrings::get('improve_plugin')); ?>
                </button>
                <a
                    class="am-usage-tracking-banner__learn-more"
                    href="https://wpamelia.com/usage-data-privacy/"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <?php echo esc_html(\AmeliaBooking\Infrastructure\WP\Translations\BackendStrings::get('learn_more')); ?>
                </a>
            </div>
        </div>

        <button type="button" class="am-usage-tracking-banner__dismiss" aria-label="<?php esc_attr_e('Dismiss this notice.', 'wpamelia'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
</div>
</div>
<script>
    (function ($) {
        function findMountTarget() {
            return document.getElementById('amelia-redesign-page');
        }

        function showNotice(root) {
            var target = findMountTarget();

            if (!target) {
                return;
            }

            root.classList.remove('melograno-usage-tracking-notice--pending');

            if (root.parentNode !== target) {
                target.insertBefore(root, target.firstChild);
            }
        }

        function mountNotice() {
            var SPA_READY_TIMEOUT_MS = 10000;
            var root = document.getElementById('melograno-usage-tracking-notice');

            if (!root) {
                return;
            }

            var redesignPage = document.getElementById('amelia-redesign-page');

            if (!redesignPage) {
                return;
            }

            var revealed = false;
            var observers = [];
            var timeoutId;

            function stopWatching() {
                observers.forEach(function (observer) {
                    observer.disconnect();
                });
                observers = [];

                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                    timeoutId = null;
                }
            }

            function revealWhenSpaReady() {
                if (revealed) {
                    return;
                }

                revealed = true;
                stopWatching();
                showNotice(root);
            }

            function watchAppRoot(appRoot) {
                if (appRoot.children.length > 0) {
                    revealWhenSpaReady();
                    return;
                }

                var appObserver = new MutationObserver(function () {
                    if (appRoot.children.length > 0) {
                        revealWhenSpaReady();
                    }
                });

                appObserver.observe(appRoot, { childList: true });
                observers.push(appObserver);
            }

            var appRoot = redesignPage.querySelector('#amelia-redesign');

            if (!appRoot) {
                if (redesignPage.children.length > 0) {
                    revealWhenSpaReady();
                    return;
                }

                var pageObserver = new MutationObserver(function () {
                    var lateAppRoot = redesignPage.querySelector('#amelia-redesign');

                    if (!lateAppRoot) {
                        return;
                    }

                    pageObserver.disconnect();
                    observers = observers.filter(function (observer) {
                        return observer !== pageObserver;
                    });
                    watchAppRoot(lateAppRoot);
                });

                pageObserver.observe(redesignPage, { childList: true, subtree: true });
                observers.push(pageObserver);
            } else {
                watchAppRoot(appRoot);

                if (revealed) {
                    return;
                }
            }

            timeoutId = window.setTimeout(revealWhenSpaReady, SPA_READY_TIMEOUT_MS);
        }

        $(function () {
            mountNotice();

            var $root = $('#melograno-usage-tracking-notice');
            var $banner = $root.find('.am-usage-tracking-banner');

            $banner.find('.am-usage-tracking-banner__dismiss').on('click', function (e) {
                e.preventDefault();

                $.post(ajaxurl, {
                    action: '<?php echo esc_js($usageTrackingAjaxPrefix); ?>_dismiss_usage_tracking_notice',
                    _ajax_nonce: '<?php echo esc_js($usageTrackingConsentNonce); ?>'
                }).done(function () {
                    $root.slideUp('fast');
                });
            });

            $banner.find('.am-usage-tracking-banner__enable').on('click', function (e) {
                e.preventDefault();

                $.post(ajaxurl, {
                    action: '<?php echo esc_js($usageTrackingAjaxPrefix); ?>_enable_usage_tracking',
                    _ajax_nonce: '<?php echo esc_js($usageTrackingConsentNonce); ?>'
                }).done(function () {
                    $root.slideUp('fast');
                });
            });
        });
    })(jQuery);
</script>
