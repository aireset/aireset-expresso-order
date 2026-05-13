/* global jQuery, eop_vars */
(function ($) {
    'use strict';

    var items = [];
    var i18n = eop_vars.i18n || {};
    var discountMode = eop_vars.discount_mode || 'both';
    var selectedShippingRate = null;
    var shippingAddress = {};
    var postcodeLookupCache = {};
    var postcodeLookupBusy = false;
    var currentView = eop_vars.initial_view || 'new-order';
    var ordersLoaded = false;
    var ordersPage = 1;
    var ordersSearchDebounce = null;
    var currentEditingOrderId = 0;
    var pendingEditOrderId = 0;
    var editLoadToken = 0;
    var postFlowRequestToken = 0;
    var lazyViewRequestToken = 0;
    var cacheNamespace = String(eop_vars.cache_namespace || 'default');
    var cachePrefix = 'eop:' + cacheNamespace + ':';
    var sessionStore = null;
    var localStore = null;

    function getStorage(type) {
        var storage;
        var probeKey = cachePrefix + 'probe';

        try {
            storage = type === 'local' ? window.localStorage : window.sessionStorage;

            if (!storage) {
                return null;
            }

            storage.setItem(probeKey, '1');
            storage.removeItem(probeKey);

            return storage;
        } catch (err) {
            return null;
        }
    }

    function getCacheKey(scope, key) {
        return cachePrefix + scope + ':' + key;
    }

    function writeCache(storage, key, value) {
        if (!storage) {
            return;
        }

        try {
            storage.setItem(key, JSON.stringify({
                storedAt: Date.now(),
                value: value
            }));
        } catch (err) {
            return;
        }
    }

    function readCache(storage, key, ttl) {
        var raw;
        var parsed;

        if (!storage) {
            return null;
        }

        try {
            raw = storage.getItem(key);
        } catch (err) {
            return null;
        }

        if (!raw) {
            return null;
        }

        try {
            parsed = JSON.parse(raw);
        } catch (err) {
            return null;
        }

        if (!parsed || typeof parsed !== 'object' || !parsed.hasOwnProperty('value')) {
            return null;
        }

        if (ttl && parsed.storedAt && (Date.now() - parsed.storedAt) > ttl) {
            try {
                storage.removeItem(key);
            } catch (removeErr) {
                return null;
            }

            return null;
        }

        return parsed.value;
    }

    function removeCache(storage, key) {
        if (!storage) {
            return;
        }

        try {
            storage.removeItem(key);
        } catch (err) {
            return;
        }
    }

    function removeCacheByPrefix(storage, prefix) {
        var index;
        var key;

        if (!storage) {
            return;
        }

        for (index = storage.length - 1; index >= 0; index -= 1) {
            key = storage.key(index);

            if (key && key.indexOf(prefix) === 0) {
                try {
                    storage.removeItem(key);
                } catch (err) {
                    return;
                }
            }
        }
    }

    function getPdfCacheKey(url) {
        return getCacheKey('pdf-tab', String(url || ''));
    }

    function getOrdersCacheKey(page, search, status, flowStatus) {
        return getCacheKey('orders-list', [page, search, status, flowStatus].join('|'));
    }

    function getOrderCacheKey(orderId) {
        return getCacheKey('order-edit', String(orderId || '0'));
    }

    function getPostFlowCacheKey(orderId) {
        return getCacheKey('post-flow', String(orderId || '0'));
    }

    function getLazyViewCacheKey(url) {
        return getCacheKey('lazy-view', String(url || ''));
    }

    function getDraftCacheKey() {
        return getCacheKey('draft', 'new-order');
    }

    function applyPluginFullscreen(isEnabled) {
        var $toggle = $('#eop-admin-chrome-toggle');
        var iconClass = isEnabled ? 'dashicons-fullscreen-exit-alt' : 'dashicons-fullscreen-alt';
        var label = isEnabled ? (i18n.focus_mode_label_exit || 'Sair do foco') : (i18n.focus_mode_label_enter || 'Modo foco');
        var title = isEnabled ? (i18n.focus_mode_exit || 'Voltar a mostrar interface do WordPress') : (i18n.focus_mode_enter || 'Ocultar interface do WordPress');

        $('body').toggleClass('is-plugin-fullscreen', Boolean(isEnabled));
        document.documentElement.classList.toggle('eop-admin-spa-fullscreen', Boolean(isEnabled));

        if ($toggle.length) {
            $toggle.attr('aria-pressed', isEnabled ? 'true' : 'false');
            $toggle.attr('title', title);
            $toggle.attr('aria-label', title);
            $toggle.find('.dashicons').attr('class', 'dashicons ' + iconClass);
            $toggle.find('.eop-admin-spa__chrome-toggle-label').text(label);
        }

        writeCache(localStore, getCacheKey('ui', 'fullscreen'), Boolean(isEnabled));
    }

    function restorePluginFullscreen() {
        applyPluginFullscreen(Boolean(readCache(localStore, getCacheKey('ui', 'fullscreen'))));
    }

    function getLazyViewShell(viewName) {
        return $('.eop-pdv-view[data-eop-view="' + String(viewName || '') + '"]');
    }

    function isLazyViewPending(viewName) {
        var $view = getLazyViewShell(viewName);

        return $view.length && $view.attr('data-eop-lazy') === 'true' && $view.attr('data-eop-lazy-loaded') !== 'true';
    }

    function finalizeLazyView($view, viewName) {
        if (!$view.length) {
            return;
        }

        $view.attr('data-eop-lazy-loaded', 'true').removeClass('is-loading');

        if (viewName === 'new-order') {
            initializeNewOrderView($view);

            if (pendingEditOrderId > 0) {
                var queuedOrderId = pendingEditOrderId;

                pendingEditOrderId = 0;
                loadOrderForEditing(queuedOrderId);
                return;
            }
        }

        if (viewName === 'pdf') {
            syncPdfSettingsFormSnapshot($view);
        }

        $(document).trigger('eop:settings-ui:init', [$view]);
    }

    function hydrateLazyView(viewName, response, options) {
        var settings = options || {};
        var $currentView = getLazyViewShell(viewName);
        var $parsed;
        var $nextView;

        if (!$currentView.length) {
            return false;
        }

        $parsed = $('<div>').append($.parseHTML(response, document, true));
        $nextView = $parsed.find('.eop-pdv-view[data-eop-view="' + viewName + '"]').first();

        if (!$nextView.length) {
            return false;
        }

        $currentView.replaceWith($nextView);
        finalizeLazyView($nextView, viewName);

        if (!settings.skipHistory) {
            syncBrowserView(viewName, Boolean(settings.replaceState));
        }

        setAppView(viewName, { skipHistory: true, skipLazy: true });

        return true;
    }

    function loadLazyView(viewName, options) {
        var settings = options || {};
        var requestToken = lazyViewRequestToken + 1;
        var url = settings.url || buildViewUrl(viewName);
        var cacheKey = getLazyViewCacheKey(url);
        var cached = readCache(sessionStore, cacheKey, 5 * 60 * 1000);
        var $view = getLazyViewShell(viewName);
        var startedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

        lazyViewRequestToken = requestToken;

        if (!$view.length || !url) {
            return;
        }

        $view.addClass('is-loading');

        if (cached && hydrateLazyView(viewName, cached, settings)) {
            recordPerformanceEntry({
                flow: 'Lazy view',
                context: viewName,
                source: 'session-cache',
                totalMs: (window.performance && window.performance.now ? window.performance.now() : Date.now()) - startedAt,
                phpMs: 0,
                responseBytes: cached.length,
                peakMemoryMb: 0
            });
            return;
        }

        $.ajax({
            url: eop_vars.ajax_url,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'eop_load_admin_view',
                nonce: eop_vars.nonce,
                view_name: viewName,
                pdf_tab: (new URL(url, window.location.origin)).searchParams.get('pdf_tab') || '',
                document: (new URL(url, window.location.origin)).searchParams.get('document') || '',
                preview_order: (new URL(url, window.location.origin)).searchParams.get('preview_order') || ''
            }
        })
            .done(function (response) {
                var completedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();
                var serverMetrics = {};
                var responseHtml = '';

                if (requestToken !== lazyViewRequestToken) {
                    return;
                }

                if (!response || !response.success || !response.data || !response.data.html) {
                    window.location.href = url;
                    return;
                }

                responseHtml = response.data.html;
                serverMetrics = response.data._performance || {};
                writeCache(sessionStore, cacheKey, responseHtml);

                recordPerformanceEntry({
                    flow: 'Lazy view',
                    context: viewName,
                    source: 'network',
                    totalMs: completedAt - startedAt,
                    phpMs: serverMetrics.php_ms || 0,
                    responseBytes: serverMetrics.response_bytes || responseHtml.length,
                    peakMemoryMb: serverMetrics.peak_memory_mb || 0,
                    queries: serverMetrics.queries || 0
                });

                if (!hydrateLazyView(viewName, responseHtml, settings)) {
                    window.location.href = url;
                }
            })
            .fail(function () {
                if (requestToken !== lazyViewRequestToken) {
                    return;
                }

                window.location.href = url;
            })
            .always(function () {
                getLazyViewShell(viewName).removeClass('is-loading');
            });
    }

    function collectDraftState() {
        return {
            items: items,
            customer: {
                user_id: parseInt($('#eop-user-id').val(), 10) || 0,
                document: $('#eop-document').val() || '',
                name: $('#eop-name').val() || '',
                email: $('#eop-email').val() || '',
                phone: $('#eop-phone').val() || ''
            },
            shipping: {
                postcode: $('#eop-shipping-postcode').val() || '',
                state: $('#eop-shipping-state').val() || '',
                city: $('#eop-shipping-city').val() || '',
                address: $('#eop-shipping-address').val() || '',
                number: $('#eop-shipping-number').val() || '',
                neighborhood: $('#eop-shipping-neighborhood').val() || '',
                address_2: $('#eop-shipping-address-2').val() || '',
                total: $('#eop-shipping').val() || '0',
                method: selectedShippingRate || null
            },
            general_discount: $('#eop-discount').val() || '',
            default_quantity: $('#eop-default-item-quantity').val() || '1',
            default_discount: $('#eop-default-item-discount').val() || '',
            status: $('#eop-status').val() || 'completed'
        };
    }

    function persistDraftState() {
        if (currentEditingOrderId > 0) {
            removeCache(localStore, getDraftCacheKey());
            return;
        }

        writeCache(localStore, getDraftCacheKey(), collectDraftState());
    }

    function restoreDraftState() {
        var draft = readCache(localStore, getDraftCacheKey());
        var customer;
        var shipping;

        if (!draft || currentEditingOrderId > 0) {
            return;
        }

        customer = draft.customer || {};
        shipping = draft.shipping || {};

        items = Array.isArray(draft.items) ? draft.items : [];

        $('#eop-user-id').val(customer.user_id || 0);
        $('#eop-document').val(customer.document || '');
        $('#eop-name').val(customer.name || '');
        $('#eop-email').val(customer.email || '');
        $('#eop-phone').val(customer.phone || '');
        $('#eop-default-item-quantity').val(draft.default_quantity || '1');
        $('#eop-default-item-discount').val(draft.default_discount || '');
        $('#eop-discount').val(draft.general_discount || '');
        $('#eop-status').val(draft.status || 'completed');
        $('#eop-shipping-postcode').val(shipping.postcode || '');
        $('#eop-shipping-state').val(shipping.state || '');
        $('#eop-shipping-city').val(shipping.city || '');
        $('#eop-shipping-address').val(shipping.address || '');
        $('#eop-shipping-number').val(shipping.number || '');
        $('#eop-shipping-neighborhood').val(shipping.neighborhood || '');
        $('#eop-shipping-address-2').val(shipping.address_2 || '');
        $('#eop-shipping').val(shipping.total || '0');

        selectedShippingRate = shipping.method || null;

        if (selectedShippingRate && selectedShippingRate.label) {
            setShippingSummary(selectedShippingRate.label + ' - ' + formatBRL(selectedShippingRate.cost || 0));
        }

        renderItems();
        recalcTotals();
    }

    function clearDraftState() {
        removeCache(localStore, getDraftCacheKey());
    }

    function initProductSearch($scope) {
        var $root = $scope && $scope.length ? $scope : $(document);
        var $field = $root.find('#eop-product-search').first();

        if (!$field.length || $field.hasClass('select2-hidden-accessible')) {
            return;
        }

        $field.select2({
            placeholder: i18n.search_product,
            minimumInputLength: 3,
            allowClear: true,
            ajax: {
                url: eop_vars.ajax_url,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return {
                        action: 'eop_search_products',
                        nonce: eop_vars.nonce,
                        term: params.term
                    };
                },
                processResults: function (data) {
                    return data;
                },
                cache: true
            }
        });
    }

    function initializeNewOrderView($scope) {
        initProductSearch($scope);
        syncDefaultDiscountFieldUi();
        updateEditingState();

        if (currentEditingOrderId > 0) {
            renderItems();
            recalcTotals();
            return;
        }

        restoreDraftState();
        renderItems();
        recalcTotals();
    }

    sessionStore = getStorage('session');
    localStore = getStorage('local');

    var performanceAuditConfig = eop_vars.performance_audit || {};
    var performanceAuditEnabled = Boolean(performanceAuditConfig.enabled);

    function getPerformanceEntriesKey() {
        return getCacheKey('performance', 'entries');
    }

    function getPerformanceSessionIdKey() {
        return getCacheKey('performance', 'session-id');
    }

    function ensurePerformanceSessionId() {
        var currentId = readCache(sessionStore, getPerformanceSessionIdKey());

        if (currentId) {
            return currentId;
        }

        currentId = 'sess-' + Date.now();
        writeCache(sessionStore, getPerformanceSessionIdKey(), currentId);
        return currentId;
    }

    function readPerformanceEntries() {
        var entries = readCache(sessionStore, getPerformanceEntriesKey());

        return Array.isArray(entries) ? entries : [];
    }

    function writePerformanceEntries(entries) {
        writeCache(sessionStore, getPerformanceEntriesKey(), entries);
    }

    function formatPerformanceMs(value) {
        if (typeof value !== 'number' || isNaN(value) || value <= 0) {
            return '-';
        }

        return value.toFixed(1) + ' ms';
    }

    function formatPerformanceBytes(value) {
        var bytes = parseInt(value, 10);

        if (!bytes || isNaN(bytes) || bytes <= 0) {
            return '-';
        }

        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        }

        return (bytes / 1024).toFixed(1) + ' KB';
    }

    function formatPerformanceMemory(value) {
        var numeric = parseFloat(value);

        if (!numeric || isNaN(numeric) || numeric <= 0) {
            return '-';
        }

        return numeric.toFixed(2) + ' MB';
    }

    function safeJsonLength(value) {
        try {
            return JSON.stringify(value || '').length;
        } catch (err) {
            return 0;
        }
    }

    function recordPerformanceEntry(entry) {
        var entries;

        if (!performanceAuditEnabled || !sessionStore || !entry) {
            return;
        }

        entries = readPerformanceEntries();
        entry.recordedAt = entry.recordedAt || new Date().toISOString();
        entry.sessionId = ensurePerformanceSessionId();
        entries.unshift(entry);
        writePerformanceEntries(entries.slice(0, 40));
        renderPerformanceAuditPanel();
    }

    function buildPerformanceSummaryCards(entries) {
        var summary = [];
        var latest = entries.length ? entries[0] : null;
        var assetSummary = performanceAuditConfig.asset_summary || {};
        var totalAssetBytes = parseInt(assetSummary.total_bytes || 0, 10) || 0;
        var navigationEntry = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation')[0] : null;

        summary.push(
            '<div class="eop-performance-audit__summary-card">' +
                '<span>' + escapeHtml(i18n.performance_summary_assets || 'Assets atuais') + '</span>' +
                '<strong>' + escapeHtml(formatPerformanceBytes(totalAssetBytes)) + '</strong>' +
            '</div>'
        );

        if (latest && typeof latest.queries !== 'undefined') {
            summary.push(
                '<div class="eop-performance-audit__summary-card">' +
                    '<span>' + escapeHtml(i18n.performance_summary_queries || 'Queries') + '</span>' +
                    '<strong>' + escapeHtml(String(latest.queries)) + '</strong>' +
                '</div>'
            );
        }

        if (navigationEntry && navigationEntry.duration) {
            summary.push(
                '<div class="eop-performance-audit__summary-card">' +
                    '<span>' + escapeHtml(i18n.performance_summary_navigation || 'Navegacao inicial') + '</span>' +
                    '<strong>' + escapeHtml(formatPerformanceMs(navigationEntry.duration)) + '</strong>' +
                '</div>'
            );
        }

        return summary.join('');
    }

    function renderPerformanceAuditPanel() {
        var $panel = $('#eop-performance-audit');
        var $container = $('#eop-performance-audit-body');
        var $body = $('#eop-performance-table-body');
        var entries;
        var rows = '';

        if (!performanceAuditEnabled || !$panel.length || !$container.length || !$body.length) {
            return;
        }

        entries = readPerformanceEntries();
        $('#eop-performance-summary').html(buildPerformanceSummaryCards(entries));

        if (!entries.length) {
            $body.html('<tr><td colspan="6">' + escapeHtml(i18n.performance_empty || 'Nenhuma medicao registrada ainda nesta sessao.') + '</td></tr>');
            return;
        }

        entries.forEach(function (entry) {
            rows += '<tr>' +
                '<td><strong>' + escapeHtml(entry.flow || '-') + '</strong><br><small>' + escapeHtml(entry.context || '') + '</small></td>' +
                '<td>' + escapeHtml(entry.source || 'network') + '</td>' +
                '<td>' + escapeHtml(formatPerformanceMs(entry.totalMs)) + '</td>' +
                '<td>' + escapeHtml(formatPerformanceMs(entry.phpMs)) + '</td>' +
                '<td>' + escapeHtml(formatPerformanceBytes(entry.responseBytes)) + '</td>' +
                '<td>' + escapeHtml(formatPerformanceMemory(entry.peakMemoryMb)) + '</td>' +
            '</tr>';
        });

        $body.html(rows);
    }

    function setPerformanceAuditState(isOpen) {
        var $panel = $('#eop-performance-audit');
        var $body = $('#eop-performance-audit-body');

        if (!$body.length) {
            $body = $panel.find('.eop-performance-audit__body').first();
        }

        if (!$panel.length || !$body.length) {
            return;
        }

        $panel.toggleClass('is-open', Boolean(isOpen));
        $body.prop('hidden', !isOpen);
        $('#eop-performance-audit-toggle').attr('aria-expanded', isOpen ? 'true' : 'false');
    }

    function registerInitialPerformanceBaseline() {
        var $panel = $('#eop-performance-audit');
        var initialMetrics = null;
        var navigationEntry;
        var assetSummary;

        if (!performanceAuditEnabled || !$panel.length) {
            return;
        }

        try {
            initialMetrics = JSON.parse($panel.attr('data-eop-performance-initial') || '{}');
        } catch (err) {
            initialMetrics = null;
        }

        if (!initialMetrics || !initialMetrics.scope) {
            renderPerformanceAuditPanel();
            return;
        }

        assetSummary = performanceAuditConfig.asset_summary || {};
        navigationEntry = window.performance && window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation')[0] : null;

        recordPerformanceEntry({
            flow: 'SPA bootstrap',
            context: initialMetrics.view || currentView,
            source: 'initial',
            totalMs: navigationEntry && navigationEntry.duration ? navigationEntry.duration : initialMetrics.php_ms,
            phpMs: initialMetrics.php_ms,
            responseBytes: navigationEntry && navigationEntry.transferSize ? navigationEntry.transferSize : parseInt(assetSummary.total_bytes || 0, 10),
            peakMemoryMb: initialMetrics.peak_memory_mb,
            queries: initialMetrics.queries || 0
        });
    }

    function clearPerformanceEntries() {
        if (!sessionStore) {
            return;
        }

        removeCache(sessionStore, getPerformanceEntriesKey());
        renderPerformanceAuditPanel();
    }

    function getDefaultDiscountFieldConfig() {
        if (discountMode === 'percent') {
            return {
                placeholder: i18n.default_discount_placeholder_percent || '10',
                help: i18n.default_discount_help_percent || 'Informe somente porcentagem (%).',
                suffix: '%'
            };
        }

        if (discountMode === 'fixed') {
            return {
                placeholder: i18n.default_discount_placeholder_fixed || '10,00',
                help: i18n.default_discount_help_fixed || 'Informe somente valor fixo (R$).',
                suffix: 'R$'
            };
        }

        return {
            placeholder: i18n.default_discount_placeholder_both || '10 ou 10%',
            help: i18n.default_discount_help_both || 'Aceita valor fixo ou porcentagem.',
            suffix: ''
        };
    }

    function syncDefaultDiscountFieldUi() {
        var config = getDefaultDiscountFieldConfig();
        var $input = $('#eop-default-item-discount');
        var $suffix = $('#eop-default-item-discount-suffix');

        if ($input.length) {
            $input.attr('placeholder', config.placeholder);
            $input.attr('title', config.help);
            $input.attr('aria-description', config.help);
        }

        if ($suffix.length) {
            if (config.suffix) {
                $suffix.text(config.suffix).prop('hidden', false);
            } else {
                $suffix.text('').prop('hidden', true);
            }
        }
    }

    function getDefaultItemQuantity() {
        var quantity = parseInt($('#eop-default-item-quantity').val(), 10);

        if (isNaN(quantity) || quantity < 1) {
            return 1;
        }

        return quantity;
    }

    function getDefaultItemDiscount() {
        return parseDiscountInput($('#eop-default-item-discount').val());
    }

    function getDiscountedUnitPrice(item) {
        var quantity = Math.max(1, parseInt(item.quantity, 10) || 1);
        var lineTotal = item.price * quantity;
        var discount = calcItemDiscount(item);

        return Math.max(0, (lineTotal - discount) / quantity);
    }

    function applyItemDefaultsToAll() {
        var quantity = getDefaultItemQuantity();
        var discount = getDefaultItemDiscount();

        if (!items.length) {
            return;
        }

        items.forEach(function (item) {
            item.quantity = quantity;
            item.discount_type = discount.type;
            item.discount_value = discount.value;
        });

        renderItems();
    }

    syncDefaultDiscountFieldUi();

    function getCurrentPdfTab() {
        var params = new URLSearchParams(window.location.search);
        var fromUrl = params.get('pdf_tab');
        var fromNav = $('.eop-admin-spa-nav__submenu-item.is-active[data-eop-pdf-tab]').first().data('eop-pdf-tab');

        if (fromUrl) {
            return String(fromUrl);
        }

        if (fromNav) {
            return String(fromNav);
        }

        return 'display';
    }

    function toggleNavGroup($toggle, forceOpen) {
        var $group = $toggle.closest('.eop-admin-spa-nav__group');
        var $submenu = $group.find('.eop-admin-spa-nav__submenu').first();
        var isOpen = typeof forceOpen === 'boolean' ? !forceOpen : $toggle.attr('aria-expanded') === 'true';
        var nextState = !isOpen;

        if (!$group.length || !$submenu.length) {
            return;
        }

        $group.toggleClass('is-open', nextState);
        $toggle.attr('aria-expanded', nextState ? 'true' : 'false');
        $submenu.attr('hidden', nextState ? false : true);
    }

    function setNavGroupState(groupName) {
        $('.eop-admin-spa-nav__group').each(function () {
            var $group = $(this);
            var $toggle = $group.find('.eop-admin-spa-nav__group-toggle').first();
            var $submenu = $group.find('.eop-admin-spa-nav__submenu').first();
            var isTarget = String($toggle.data('eop-nav-toggle') || '') === String(groupName || '');

            $group.toggleClass('is-open', isTarget);
            $toggle.attr('aria-expanded', isTarget ? 'true' : 'false');
            $submenu.attr('hidden', isTarget ? false : true);
        });
    }

    function syncSidebarState(viewName, activePdfTab) {
        var targetView = normalizeView(viewName);
        var activeGroup = getNavGroupForView(targetView);
        var resolvedPdfTab = String(activePdfTab || getCurrentPdfTab());

        $('.eop-pdv-nav__item').each(function () {
            var $item = $(this);
            var isActive;

            if ($item.is('[data-eop-nav-toggle]')) {
                isActive = String($item.data('eop-nav-toggle') || '') === activeGroup;
            } else {
                isActive = String($item.data('eop-view-target') || '') === targetView;
            }

            $item.toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });

        $('.eop-admin-spa-nav__submenu-item').each(function () {
            var $item = $(this);
            var itemView = String($item.data('eop-view-target') || '');
            var itemPdfTab = String($item.data('eop-pdf-tab') || '');
            var isActive = false;

            if (itemView) {
                isActive = itemView === targetView;
            } else if (targetView === 'pdf' && itemPdfTab) {
                isActive = itemPdfTab === resolvedPdfTab;
            }

            $item.toggleClass('is-active', isActive);
        });

        setNavGroupState(activeGroup);
    }

    function getNavGroupForView(viewName) {
        var generalViews = [
            'settings-store-info',
            'settings-general-config',
            'settings-proposal-link-style',
            'settings-new-order-style',
            'settings-orders-list-style',
            'settings-customer-experience',
            'settings-texts'
        ];
		var confirmationViews = [
			'settings-confirmation-general',
			'settings-confirmation-documents',
			'settings-confirmation-preview',
			'settings-confirmation-upload-products-preview'
		];

        if (generalViews.indexOf(String(viewName || '')) !== -1) {
            return 'general';
        }

		if (confirmationViews.indexOf(String(viewName || '')) !== -1) {
			return 'confirmation';
		}

        if (String(viewName || '') === 'pdf') {
            return 'pdf';
        }

        return '';
    }

    function setPdfPreviewState($admin, isOpen) {
        if (!$admin.length) {
            return;
        }

        $admin.toggleClass('is-preview-open', isOpen);
        $admin.find('[data-eop-pdf-preview-toggle]').attr('aria-expanded', isOpen ? 'true' : 'false');
        $admin.find('.eop-pdf-admin__preview-drawer').attr('aria-hidden', isOpen ? 'false' : 'true');
    }

    function getActivePdfSettingsForm() {
        return $('.eop-pdf-admin__sidebar .eop-pdf-admin__form').first();
    }

    function getPdfSettingsFormSnapshot($form) {
        if (!$form || !$form.length) {
            return '';
        }

        return $form.serialize();
    }

    function syncPdfSettingsFormSnapshot($scope) {
        var $forms = ($scope && $scope.length ? $scope : $(document)).find('.eop-pdf-admin__sidebar .eop-pdf-admin__form');

        $forms.each(function () {
            var $form = $(this);
            $form.data('eopSnapshot', getPdfSettingsFormSnapshot($form));
        });
    }

    function clearPdfTabCache() {
        removeCacheByPrefix(sessionStore, getCacheKey('pdf-tab', ''));
    }

    function clearLazyViewCache() {
        removeCacheByPrefix(sessionStore, getCacheKey('lazy-view', ''));
    }

    function savePendingPdfSettings() {
        var $form = getActivePdfSettingsForm();
        var deferred = $.Deferred();
        var currentSnapshot;
        var initialSnapshot;

        if (!$form.length) {
            deferred.resolve();
            return deferred.promise();
        }

        currentSnapshot = getPdfSettingsFormSnapshot($form);
        initialSnapshot = $form.data('eopSnapshot');

        if (typeof initialSnapshot === 'undefined') {
            $form.data('eopSnapshot', currentSnapshot);
            initialSnapshot = currentSnapshot;
        }

        if (currentSnapshot === initialSnapshot) {
            deferred.resolve();
            return deferred.promise();
        }

        $.ajax({
            url: $form.attr('action') || window.location.href,
            method: ($form.attr('method') || 'post').toUpperCase(),
            data: currentSnapshot,
            xhrFields: {
                withCredentials: true
            }
        }).done(function () {
            clearPdfTabCache();
            clearLazyViewCache();
            $form.data('eopSnapshot', currentSnapshot);
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });

        return deferred.promise();
    }

    function syncBrowserUrl(url, replaceState) {
        if (!url || !window.history || !window.history.pushState) {
            return;
        }

        if (replaceState) {
            window.history.replaceState({ eopView: currentView }, '', url);
            return;
        }

        window.history.pushState({ eopView: currentView }, '', url);
    }

    function setPdfAdminLoading($admin, isLoading) {
        if (!$admin.length) {
            return;
        }

        $admin.toggleClass('is-loading', isLoading);
    }

    function updatePdfNavState(url) {
        var parsedUrl = new URL(url, window.location.origin);
        var nextTab = parsedUrl.searchParams.get('pdf_tab') || 'display';

        syncSidebarState('pdf', nextTab);
    }

    function buildPdfAjaxRequest(url) {
        var parsedUrl = new URL(url, window.location.origin);

        return {
            action: 'eop_load_pdf_tab',
            nonce: eop_vars.nonce,
            pdf_tab: parsedUrl.searchParams.get('pdf_tab') || 'display',
            document: parsedUrl.searchParams.get('document') || 'order',
            preview_order: parsedUrl.searchParams.get('preview_order') || ''
        };
    }

    function buildPdfToolbarUrl(form) {
        var baseUrl = new URL(window.location.href, window.location.origin);
        var currentToolbarView = String(baseUrl.searchParams.get('view') || currentView || 'pdf');

        $(form).serializeArray().forEach(function (field) {
            if (field.value === '') {
                baseUrl.searchParams.delete(field.name);
                return;
            }

            baseUrl.searchParams.set(field.name, field.value);
        });

        baseUrl.searchParams.set('view', currentToolbarView);

        if (currentToolbarView === 'pdf') {
            baseUrl.searchParams.set('pdf_tab', getCurrentPdfTab());
        } else {
            baseUrl.searchParams.delete('pdf_tab');
        }

        return baseUrl.toString();
    }

    function loadPdfTab(url, options) {
        var settings = options || {};
        var $admin = $('.eop-pdf-admin').first();
        var preservePreview = settings.preservePreview !== false && $admin.hasClass('is-preview-open');
        var cachedShell = readCache(sessionStore, getPdfCacheKey(url), 5 * 60 * 1000);
        var startedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

        function applyPdfShell(shellHtml) {
            var $nextShell = $(shellHtml);

            if (!$nextShell.length) {
                $nextShell = $('<div>').append($.parseHTML(shellHtml, document, true)).find('[data-eop-pdf-shell]').first();
            }

            if (!$nextShell.length) {
                window.location.href = url;
                return;
            }

            $admin.find('[data-eop-pdf-shell]').first().replaceWith($nextShell);

            if (!settings.skipHistory) {
                syncBrowserUrl(url, Boolean(settings.replaceState));
            }

            updatePdfNavState(url);
            setAppView('pdf', { skipHistory: true, skipLazy: true });
            setPdfPreviewState($admin, preservePreview);
            syncPdfSettingsFormSnapshot($admin);
            $(document).trigger('eop:settings-ui:init', [$nextShell]);
        }

        if (!$admin.length || !url) {
            if (url && isLazyViewPending('pdf')) {
                loadLazyView('pdf', { url: url, replaceState: Boolean(settings.replaceState), skipHistory: Boolean(settings.skipHistory) });
                return;
            }

            if (url) {
                window.location.href = url;
            }

            return;
        }

        if (cachedShell) {
            recordPerformanceEntry({
                flow: 'PDF tab',
                context: String((new URL(url, window.location.origin)).searchParams.get('pdf_tab') || 'display'),
                source: 'session-cache',
                totalMs: (window.performance && window.performance.now ? window.performance.now() : Date.now()) - startedAt,
                phpMs: 0,
                responseBytes: cachedShell.length,
                peakMemoryMb: 0
            });
            applyPdfShell(cachedShell);
            return;
        }

        setPdfAdminLoading($admin, true);

        $.ajax({
            url: eop_vars.ajax_url,
            method: 'GET',
            dataType: 'json',
            data: buildPdfAjaxRequest(url)
        })
            .done(function (response) {
                var shellHtml;
                var perf = {};
                var completedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

                if (!response || !response.success || !response.data || !response.data.html) {
                    window.location.href = url;
                    return;
                }

                shellHtml = response.data.html;
                perf = response.data._performance || {};
                writeCache(sessionStore, getPdfCacheKey(url), shellHtml);
                recordPerformanceEntry({
                    flow: 'PDF tab',
                    context: response.data.tab || String((new URL(url, window.location.origin)).searchParams.get('pdf_tab') || 'display'),
                    source: 'network',
                    totalMs: completedAt - startedAt,
                    phpMs: perf.php_ms || 0,
                    responseBytes: perf.response_bytes || shellHtml.length,
                    peakMemoryMb: perf.peak_memory_mb || 0,
                    queries: perf.queries || 0
                });
                applyPdfShell(shellHtml);
            })
            .fail(function () {
                window.location.href = url;
            })
            .always(function () {
                setPdfAdminLoading($admin, false);
            });
    }

    function getShippingPanelElements() {
        return {
            toggle: $('#eop-shipping-toggle'),
            panel: $('#eop-shipping-panel'),
            icon: $('.eop-shipping-toggle__icon'),
            summary: $('#eop-shipping-summary'),
            status: $('#eop-shipping-address-status'),
            rates: $('#eop-shipping-rates')
        };
    }

    function setShippingPanelState(isOpen) {
        var ui = getShippingPanelElements();

        if (!ui.panel.length || !ui.toggle.length) {
            return;
        }

        ui.panel.attr('hidden', !isOpen);
        ui.toggle.attr('aria-expanded', isOpen ? 'true' : 'false');

        if (ui.icon.length) {
            ui.icon.text(isOpen ? '-' : '+');
        }
    }

    function setShippingSummary(message) {
        var ui = getShippingPanelElements();

        if (ui.summary.length) {
            ui.summary.text(message);
        }
    }

    function setShippingAddressStatus(message, type) {
        var ui = getShippingPanelElements();
        var cssClass = 'eop-shipping-panel__status';

        if (!ui.status.length) {
            return;
        }

        if (type) {
            cssClass += ' is-' + type;
        }

        ui.status.text(message || '').attr('class', cssClass);
    }

    function clearShippingSelection(resetValue) {
        var ui = getShippingPanelElements();

        ui.rates.empty();
        selectedShippingRate = null;

        if (resetValue) {
            $('#eop-shipping').val(0);
            setShippingSummary(i18n.shipping_summary_default || 'Clique para calcular com o endereco do cliente.');
        } else {
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
        }

        recalcTotals();
    }

    function sanitizePostcode(value) {
        return (value || '').replace(/\D/g, '').slice(0, 8);
    }

    function formatPostcode(value) {
        var digits = sanitizePostcode(value);

        if (digits.length <= 5) {
            return digits;
        }

        return digits.slice(0, 5) + '-' + digits.slice(5);
    }

    function formatBRL(value) {
        return 'R$ ' + parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    function escapeHtml(value) {
        return $('<span>').text(value == null ? '' : String(value)).html();
    }

    function updateEditingState() {
        var isEditing = currentEditingOrderId > 0;
        var $banner = $('#eop-editing-banner');
        var $title = $('#eop-editing-title');
        var $submit = $('#eop-submit');

        $('#eop-edit-order-id').val(currentEditingOrderId || 0);

        if ($banner.length) {
            $banner.attr('hidden', !isEditing).toggleClass('is-active', isEditing);
        }

        if ($title.length && isEditing) {
            $title.text((i18n.edit_title || 'Editando pedido') + ' #' + currentEditingOrderId);
        }

        if ($submit.length) {
            $submit.text(isEditing ? (i18n.edit_submit || 'Salvar alteracoes') : i18n.submit_label);
        }

        renderPostConfirmationFlowPlaceholder(isEditing ? (i18n.post_flow_loading || 'Carregando dados complementares da proposta...') : (i18n.post_flow_unavailable_edit || 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.'));
    }

    function getCurrentSubmitLabel() {
        return currentEditingOrderId ? (i18n.edit_submit || 'Salvar alteracoes') : i18n.submit_label;
    }

    function enterEditMode(orderId) {
        currentEditingOrderId = parseInt(orderId, 10) || 0;
        updateEditingState();
        setAppView('new-order');
    }

    function exitEditMode() {
        currentEditingOrderId = 0;
        updateEditingState();

        if (currentView) {
            syncBrowserView(currentView, true);
        }
    }

    function parseDiscountInput(rawValue) {
        var raw = String(rawValue || '').trim().replace(',', '.');
        var hasPercent = raw.indexOf('%') !== -1;
        var numeric = parseFloat(raw.replace(/[^0-9.\-]/g, ''));

        if (isNaN(numeric) || numeric <= 0) {
            return { type: discountMode === 'percent' ? 'percent' : 'fixed', value: 0 };
        }

        var type;
        if (discountMode === 'percent') {
            type = 'percent';
        } else if (discountMode === 'fixed') {
            type = 'fixed';
        } else {
            type = hasPercent ? 'percent' : 'fixed';
        }

        return {
            type: type,
            value: Math.max(0, numeric)
        };
    }

    function getAllowedViews() {
        var views = ['new-order', 'orders'];

        if ($('[data-eop-view="pdf"]').length) {
            views.push('pdf');
        }

        [
            'settings-store-info',
            'settings-general-config',
            'settings-confirmation-general',
            'settings-confirmation-documents',
            'settings-confirmation-preview',
            'settings-confirmation-upload-products-preview',
            'settings-proposal-link-style',
            'settings-new-order-style',
            'settings-orders-list-style',
            'settings-customer-experience',
            'settings-texts',
            'documentation',
            'export-import'
        ].forEach(function (viewName) {
            if ($('[data-eop-view="' + viewName + '"]').length) {
                views.push(viewName);
            }
        });

        if ($('[data-eop-view="license"]').length) {
            views.push('license');
        }

        return views;
    }

    function normalizeView(viewName) {
        var targetView = String(viewName || '');
        var allowedViews = getAllowedViews();

        if (allowedViews.indexOf(targetView) !== -1) {
            return targetView;
        }

        return allowedViews[0] || 'new-order';
    }

    function buildViewUrl(viewName) {
        var viewUrls = eop_vars.view_urls || {};
        var base = String(viewUrls[normalizeView(viewName)] || eop_vars.view_url_base || '');
        var currentParams = new URLSearchParams(window.location.search);
        var normalizedView = normalizeView(viewName);
        var url;

        if (!base) {
            return '';
        }

        url = new URL(base, window.location.origin);
        url.searchParams.set('view', normalizedView);

        if (normalizedView === 'pdf') {
            url.searchParams.set('pdf_tab', getCurrentPdfTab());

            ['document', 'preview_order'].forEach(function (key) {
                var value = currentParams.get(key);

                if (value) {
                    url.searchParams.set(key, value);
                }
            });
        }

        if (currentEditingOrderId > 0) {
            url.searchParams.set('action', 'edit');
            url.searchParams.set('order_id', String(currentEditingOrderId));
        } else {
            url.searchParams.delete('action');
            url.searchParams.delete('order_id');
        }

        return url.toString();
    }

    function syncBrowserView(viewName, replaceState) {
        var nextUrl = buildViewUrl(viewName);

        if (!nextUrl || !window.history || !window.history.pushState) {
            return;
        }

        if (replaceState) {
            window.history.replaceState({ eopView: viewName }, '', nextUrl);
            return;
        }

        window.history.pushState({ eopView: viewName }, '', nextUrl);
    }

    function setAppView(viewName, options) {
        var settings = options || {};
        var targetView = normalizeView(viewName);

        currentView = targetView;

        $('.eop-pdv-view').each(function () {
            var isActive = $(this).data('eop-view') === targetView;
            $(this).toggleClass('is-active', isActive).attr('hidden', !isActive);
        });

        syncSidebarState(targetView);

        if (targetView === 'orders' && !ordersLoaded && !isLazyViewPending(targetView)) {
            loadOrdersList(1);
        }

        if (!settings.skipLazy && isLazyViewPending(targetView)) {
            loadLazyView(targetView, { skipHistory: true });
        }

        if (!settings.skipHistory) {
            syncBrowserView(targetView, Boolean(settings.replaceState));
        }
    }

    function renderOrdersSummary(totalItems, viewer) {
        var prefix = viewer && viewer.is_admin ? 'Todos os pedidos expresso' : 'Seus pedidos expresso';

        $('#eop-orders-summary').html(
            '<div class="eop-orders-summary__card">' +
                '<strong>' + escapeHtml(prefix) + '</strong>' +
                '<span>' + escapeHtml(totalItems + ' ' + (i18n.orders_of || 'pedido(s) encontrado(s)')) + '</span>' +
            '</div>'
        );
    }

    function renderOrdersPagination(page, totalPages) {
        var html = '';
        var prevDisabled = page <= 1 ? ' disabled' : '';
        var nextDisabled = page >= totalPages ? ' disabled' : '';

        if (totalPages <= 1) {
            $('#eop-orders-pagination').empty();
            return;
        }

        html += '<button type="button" class="eop-btn eop-orders-page-btn" data-page="' + (page - 1) + '"' + prevDisabled + '>' + escapeHtml(i18n.orders_previous || 'Anterior') + '</button>';
        html += '<span class="eop-orders-page-indicator">' + escapeHtml('Página ' + page + ' de ' + totalPages) + '</span>';
        html += '<button type="button" class="eop-btn eop-orders-page-btn" data-page="' + (page + 1) + '"' + nextDisabled + '>' + escapeHtml(i18n.orders_next || 'Proxima') + '</button>';

        $('#eop-orders-pagination').html(html);
    }

    function renderOrderFlowPill(label, value, tone) {
        var toneClass = tone ? ' eop-order-card__flow-pill--' + tone : '';

        return '<span class="eop-order-card__flow-pill' + toneClass + '">' +
            '<span class="eop-order-card__flow-pill-label">' + escapeHtml(label) + '</span>' +
            ' <strong class="eop-order-card__flow-pill-value">' + escapeHtml(value) + '</strong>' +
        '</span>';
    }

    function renderOrderFlowStageControls(orderId, controls) {
        if (!controls || !controls.can_update || !controls.options || !controls.options.length || !orderId) {
            return '';
        }

        return '<div class="eop-order-card__flow-stage-controls" data-order-id="' + escapeHtml(orderId) + '">' +
            '<span class="eop-order-card__flow-stage-label">' + escapeHtml(i18n.post_flow_stage_label || 'Etapa do fluxo') + '</span>' +
            '<div class="eop-order-card__flow-stage-actions">' +
                '<select class="eop-order-card__flow-stage-select">' + renderStageControlOptions(controls.options, controls.selected || 'auto') + '</select>' +
                '<button type="button" class="eop-btn eop-btn-secondary eop-order-card__flow-stage-apply">' + escapeHtml(i18n.post_flow_stage_apply || 'Atualizar etapa') + '</button>' +
            '</div>' +
        '</div>';
    }

    function renderOrdersList(orders, viewer) {
        var html = '';
        var isAdmin = viewer && viewer.is_admin;

        if (!orders.length) {
            $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_empty || 'Nenhum pedido encontrado para este filtro.') + '</div>');
            return;
        }

        orders.forEach(function (order) {
            html += '<article class="eop-card eop-order-card">';
            html += '<div class="eop-order-card__header">';
            html += '<div>';
            html += '<div class="eop-order-card__number">' + escapeHtml(order.number) + '</div>';
            html += '<h3 class="eop-order-card__customer-name">' + escapeHtml(order.customer_name) + '</h3>';

            if (order.customer_email) {
                html += '<p class="eop-order-card__email">' + escapeHtml(order.customer_email) + '</p>';
            }

            html += '</div>';
            html += '<span class="eop-order-card__status status-' + escapeHtml(order.status) + '">' + escapeHtml(order.status_label) + '</span>';
            html += '</div>';

            html += '<div class="eop-order-card__meta">';
            html += '<div><span>Data</span><strong>' + escapeHtml(order.date_label) + '</strong></div>';
            html += '<div><span>Total</span><strong>' + order.total_html + '</strong></div>';

            if (isAdmin && order.created_by_name) {
                html += '<div><span>' + escapeHtml(i18n.orders_created_by || 'Vendedor') + '</span><strong>' + escapeHtml(order.created_by_name) + '</strong></div>';
            }

            html += '</div>';

            if (order.post_confirmation_flow_summary && order.post_confirmation_flow_summary.active_for_order) {
                var flowSummary = order.post_confirmation_flow_summary || {};
                var contractAccepted = !!(flowSummary.contract && flowSummary.contract.accepted);
                var documentsCompleted = flowSummary.documents ? (flowSummary.documents.completed + '/' + flowSummary.documents.total) : '0/0';
                var documentsDone = flowSummary.documents ? (parseInt(flowSummary.documents.completed, 10) >= parseInt(flowSummary.documents.total, 10)) : false;
                var attachmentUploaded = !!(flowSummary.attachment && flowSummary.attachment.uploaded);
                var attachmentOptional = !!(flowSummary.attachment && !flowSummary.attachment.required);
                var finalPdfReady = !!(flowSummary.final_pdf && flowSummary.final_pdf.ready);
                var productsProgress = flowSummary.products ? (flowSummary.products.completed + '/' + flowSummary.products.editable) : '0/0';
                var productsDone = flowSummary.products ? (parseInt(flowSummary.products.completed, 10) >= parseInt(flowSummary.products.editable, 10)) : false;

                html += '<div class="eop-order-card__flow">';
                html += '<div class="eop-order-card__flow-head">';
                html += '<span>' + escapeHtml(i18n.orders_flow_title || 'Fluxo complementar') + '</span>';
                html += ' <strong class="eop-order-card__flow-stage">' + escapeHtml(order.post_confirmation_flow_summary.stage_label || '') + '</strong>';
                html += '</div>';
                html += '<div class="eop-order-card__flow-list">';
                html += renderOrderFlowPill(
                    i18n.orders_flow_contract || 'Contrato',
                    contractAccepted ? (i18n.post_flow_contract_done || 'Aceito') : (i18n.post_flow_pending || 'Pendente'),
                    contractAccepted ? 'success' : 'warning'
                );
                html += renderOrderFlowPill(
                    i18n.orders_flow_fields || 'Campos',
                    documentsCompleted,
                    documentsDone ? 'success' : 'info'
                );
                html += renderOrderFlowPill(
                    i18n.orders_flow_attachment || 'Anexo',
                    attachmentUploaded ? (i18n.orders_flow_uploaded || 'Enviado') : (attachmentOptional ? (i18n.orders_flow_optional || 'Opcional') : (i18n.post_flow_pending || 'Pendente')),
                    attachmentUploaded ? 'success' : (attachmentOptional ? 'neutral' : 'warning')
                );
                html += renderOrderFlowPill(
                    i18n.orders_flow_final_pdf || 'PDF final',
                    finalPdfReady ? (i18n.orders_flow_ready || 'Pronto') : (i18n.post_flow_pending || 'Pendente'),
                    finalPdfReady ? 'success' : 'warning'
                );
                html += renderOrderFlowPill(
                    i18n.orders_flow_products || 'Produtos',
                    productsProgress,
                    productsDone ? 'success' : 'info'
                );
                html += '</div>';
                html += renderOrderFlowStageControls(order.id, flowSummary.stage_controls || null);
                html += '</div>';
            }

            html += '<div class="eop-order-card__actions">';

            if (order.public_url) {
                html += '<a class="eop-btn eop-btn-primary" href="' + escapeHtml(order.public_url) + '" target="_blank" rel="noopener">' + escapeHtml(i18n.orders_public || 'Link do cliente') + '</a>';
            }

            if (order.pdf_url) {
                html += '<a class="eop-btn eop-btn-primary" href="' + escapeHtml(order.pdf_url) + '" target="_blank" rel="noopener">' + escapeHtml(i18n.orders_pdf || 'PDF') + '</a>';
            }

            html += '<button type="button" class="eop-btn eop-btn-primary eop-order-edit-spa" data-order-id="' + escapeHtml(order.id) + '">' + escapeHtml(i18n.orders_edit || 'Editar aqui') + '</button>';
            html += '</div>';
            html += '</article>';
        });

        $('#eop-orders-list').html(html);
    }

    function loadOrdersList(page) {
        var requestedPage = parseInt(page, 10) || 1;
        var search = $('#eop-orders-search').val() || '';
        var status = $('#eop-orders-status-filter').val() || 'any';
        var postConfirmationFlow = $('#eop-orders-flow-filter').val() || 'any';
        var cachedResponse = readCache(sessionStore, getOrdersCacheKey(requestedPage, search, status, postConfirmationFlow), 60 * 1000);
        var startedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

        ordersPage = requestedPage;
        $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_loading || 'Carregando pedidos...') + '</div>');
        $('#eop-orders-pagination').empty();

        if (cachedResponse) {
            ordersLoaded = true;
            recordPerformanceEntry({
                flow: 'Orders list',
                context: 'page ' + requestedPage,
                source: 'session-cache',
                totalMs: (window.performance && window.performance.now ? window.performance.now() : Date.now()) - startedAt,
                phpMs: 0,
                responseBytes: safeJsonLength(cachedResponse),
                peakMemoryMb: 0
            });
            renderOrdersSummary(cachedResponse.pagination.total_items, cachedResponse.viewer);
            renderOrdersList(cachedResponse.orders || [], cachedResponse.viewer || {});
            renderOrdersPagination(cachedResponse.pagination.page, cachedResponse.pagination.total_pages);
            return;
        }

        $.post(eop_vars.ajax_url, {
            action: 'eop_list_orders',
            nonce: eop_vars.nonce,
            paged: requestedPage,
            search: search,
            status: status,
            post_confirmation_flow: postConfirmationFlow
        }, function (res) {
            var perf = (res && res.data && res.data._performance) || {};
            var completedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

            if (!res.success) {
                $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml((res.data && res.data.message) || i18n.orders_error || 'Nao foi possivel carregar os pedidos agora.') + '</div>');
                return;
            }

            ordersLoaded = true;
            writeCache(sessionStore, getOrdersCacheKey(requestedPage, search, status, postConfirmationFlow), res.data);
            recordPerformanceEntry({
                flow: 'Orders list',
                context: 'page ' + requestedPage,
                source: 'network',
                totalMs: completedAt - startedAt,
                phpMs: perf.php_ms || 0,
                responseBytes: safeJsonLength(res.data),
                peakMemoryMb: perf.peak_memory_mb || 0,
                queries: perf.queries || 0
            });
            renderOrdersSummary(res.data.pagination.total_items, res.data.viewer);
            renderOrdersList(res.data.orders || [], res.data.viewer || {});
            renderOrdersPagination(res.data.pagination.page, res.data.pagination.total_pages);
        }).fail(function () {
            $('#eop-orders-list').html('<div class="eop-card eop-orders-empty-state">' + escapeHtml(i18n.orders_error || 'Nao foi possivel carregar os pedidos agora.') + '</div>');
        });
    }

    function loadOrderForEditing(orderId) {
        var requestToken = editLoadToken + 1;
        var cachedOrder = readCache(sessionStore, getOrderCacheKey(orderId), 2 * 60 * 1000);
        var startedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

        if (isLazyViewPending('new-order')) {
            pendingEditOrderId = parseInt(orderId, 10) || 0;
            setAppView('new-order');
            return;
        }

        pendingEditOrderId = 0;

        function applyOrderData(d, fallbackOrderId) {
            var addr = d.shipping_address || {};

            enterEditMode(d.order_id || fallbackOrderId);

            $('#eop-document').val(d.customer && d.customer.document ? d.customer.document : '');
            $('#eop-name').val(d.customer && d.customer.name ? d.customer.name : '');
            $('#eop-email').val(d.customer && d.customer.email ? d.customer.email : '');
            $('#eop-phone').val(d.customer && d.customer.phone ? d.customer.phone : '');
            $('#eop-user-id').val(d.customer && d.customer.user_id ? d.customer.user_id : 0);

            items = (d.items || []).map(function (item) {
                return {
                    product_id: item.product_id,
                    name: item.name,
                    sku: item.sku || '',
                    price: parseFloat(item.price) || 0,
                    image: item.image || '',
                    quantity: parseInt(item.quantity, 10) || 1,
                    discount_type: item.discount_type || (discountMode === 'fixed' ? 'fixed' : 'percent'),
                    discount_value: parseFloat(item.discount_value) || 0
                };
            });
            renderItems();

            $('#eop-shipping').val(parseFloat(d.shipping) || 0);
            $('#eop-discount').val(formatDiscountInput(d.discount_type || 'fixed', d.discount || 0));
            $('#eop-status').val(d.status || 'pending');

            $('#eop-shipping-postcode').val(addr.postcode ? formatPostcode(addr.postcode) : '');
            $('#eop-shipping-state').val(addr.state || '');
            $('#eop-shipping-city').val(addr.city || '');
            $('#eop-shipping-address').val(addr.address || '');
            $('#eop-shipping-number').val(addr.number || '');
            $('#eop-shipping-neighborhood').val(addr.neighborhood || '');
            $('#eop-shipping-address-2').val(addr.address_2 || '');

            selectedShippingRate = null;
            $('#eop-shipping-rates').empty();
            setShippingPanelState(false);
            setShippingAddressStatus('', '');

            if (d.shipping_method && parseFloat(d.shipping) > 0) {
                setShippingSummary(d.shipping_method + ' - ' + formatBRL(d.shipping));
            } else {
                setShippingSummary(i18n.shipping_summary_default || 'Clique para calcular com o endereco do cliente.');
            }

            loadPostConfirmationFlow(fallbackOrderId);
            recalcTotals();
            showNotice(i18n.edit_loaded || 'Pedido carregado no painel para edicao.', 'success');
        }

        editLoadToken = requestToken;

        if (cachedOrder) {
            recordPerformanceEntry({
                flow: 'Order edit',
                context: 'order #' + orderId,
                source: 'session-cache',
                totalMs: (window.performance && window.performance.now ? window.performance.now() : Date.now()) - startedAt,
                phpMs: 0,
                responseBytes: safeJsonLength(cachedOrder),
                peakMemoryMb: 0
            });
            applyOrderData(cachedOrder, orderId);
            return;
        }

        $.post(eop_vars.ajax_url, {
            action: 'eop_load_order',
            nonce: eop_vars.nonce,
            order_id: orderId
        }, function (res) {
            var d;
            var perf = (res && res.data && res.data._performance) || {};
            var completedAt = window.performance && window.performance.now ? window.performance.now() : Date.now();

            if (requestToken !== editLoadToken) {
                return;
            }

            if (!res.success) {
                showNotice((res.data && res.data.message) || i18n.edit_error || 'Nao foi possivel abrir este pedido para edicao.', 'error');
                return;
            }

            d = res.data || {};
            writeCache(sessionStore, getOrderCacheKey(orderId), d);
            recordPerformanceEntry({
                flow: 'Order edit',
                context: 'order #' + orderId,
                source: 'network',
                totalMs: completedAt - startedAt,
                phpMs: perf.php_ms || 0,
                responseBytes: safeJsonLength(d),
                peakMemoryMb: perf.peak_memory_mb || 0,
                queries: perf.queries || 0
            });
            applyOrderData(d, orderId);
        }).fail(function () {
            if (requestToken !== editLoadToken) {
                return;
            }

            showNotice(i18n.edit_error || 'Nao foi possivel abrir este pedido para edicao.', 'error');
        });
    }

    function formatDiscountInput(type, value) {
        var numeric = parseFloat(value) || 0;

        if (numeric <= 0) {
            return '';
        }

        return type === 'percent' ? (String(numeric).replace('.', ',') + '%') : String(numeric).replace('.', ',');
    }

    function calcItemDiscount(item) {
        var lineTotal = item.price * item.quantity;
        var discVal = parseFloat(item.discount_value) || 0;

        if (discVal <= 0) {
            return 0;
        }

        if (item.discount_type === 'percent') {
            return Math.min(lineTotal, lineTotal * discVal / 100);
        }

        return Math.min(lineTotal, discVal);
    }

    function recalcTotals() {
        var subtotal = 0;
        var itemDiscounts = 0;

        items.forEach(function (item) {
            var lineTotal = item.price * item.quantity;
            subtotal += lineTotal;
            itemDiscounts += calcItemDiscount(item);
        });

        var afterItemDisc = subtotal - itemDiscounts;
        var shipping = parseFloat($('#eop-shipping').val()) || 0;
        var discountParsed = parseDiscountInput($('#eop-discount').val());
        var discountType = discountParsed.type;
        var discountInput = discountParsed.value;
        var generalDiscount = 0;

        if (discountInput > 0) {
            if (discountType === 'percent') {
                generalDiscount = afterItemDisc * discountInput / 100;
            } else {
                generalDiscount = discountInput;
            }
        }

        var totalDiscount = itemDiscounts + generalDiscount;
        var grand = Math.max(0, subtotal + shipping - totalDiscount);

        $('#eop-subtotal').text(formatBRL(subtotal));
        $('#eop-shipping-total').text(formatBRL(shipping));
        $('#eop-discount-total').text('- ' + formatBRL(totalDiscount));
        $('#eop-grand-total').text(formatBRL(grand));
        persistDraftState();
    }

    function updateCardSubtotal($card, idx) {
        var item = items[idx];
        var lineTotal = item.price * item.quantity;
        var disc = calcItemDiscount(item);
        var sub = lineTotal - disc;
        var discountedUnitPrice = getDiscountedUnitPrice(item);

        $card.find('.eop-item-card__subtotal').text(formatBRL(sub));
        $card.find('.eop-item-card__discounted-unit-price').text(formatBRL(discountedUnitPrice));
    }

    function renderItems() {
        var $body = $('#eop-items-body');

        $body.empty();

        if (!items.length) {
            $body.append('<div class="eop-items-empty">' + escapeHtml(i18n.no_items || 'Nenhum produto adicionado.') + '</div>');
            recalcTotals();
            return;
        }

        items.forEach(function (item, idx) {
            var lineTotal = item.price * item.quantity;
            var disc = calcItemDiscount(item);
            var sub = lineTotal - disc;
            var discountedUnitPrice = getDiscountedUnitPrice(item);
            var discType = item.discount_type || 'fixed';
            var discVal = parseFloat(item.discount_value) || 0;
            var imgSrc = item.image || '';
            var nameHtml = $('<span>').text(item.name + (item.sku ? ' [' + item.sku + ']' : '')).html();

            var discSuffix = '';
            var discPlaceholder = '0';

            if (discountMode === 'percent') {
                discSuffix = '<span class="eop-item-discount-suffix">%</span>';
                discPlaceholder = '0';
            } else if (discountMode === 'fixed') {
                discSuffix = '<span class="eop-item-discount-suffix">R$</span>';
                discPlaceholder = '0,00';
            } else {
                discSuffix = '';
                discPlaceholder = '10 ou 10%';
            }

            var card = '<div class="eop-item-card" data-idx="' + idx + '">' +
                '<a href="#" class="eop-remove-item" title="Remover">&times;</a>' +
                '<div class="eop-item-card__left">' +
                    (imgSrc ? '<img src="' + $('<span>').text(imgSrc).html() + '" alt="" class="eop-item-card__img" />' : '') +
                '</div>' +
                '<div class="eop-item-card__right">' +
                    '<div class="eop-item-card__name">' + nameHtml + '</div>' +
                    '<div class="eop-item-card__fields">' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + escapeHtml(i18n.label_price || 'Preco') + '</span>' +
                            '<span class="eop-item-card__value">' + formatBRL(item.price) + '</span>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + escapeHtml(i18n.label_quantity || 'Qtd') + '</span>' +
                            '<div class="eop-qty-stepper">' +
                                '<button type="button" class="eop-qty-btn eop-qty-dec" aria-label="Diminuir">&#8722;</button>' +
                                '<input type="number" class="eop-qty" min="1" value="' + item.quantity + '" inputmode="numeric" />' +
                                '<button type="button" class="eop-qty-btn eop-qty-inc" aria-label="Aumentar">+</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + escapeHtml(i18n.label_discount || 'Desconto') + '</span>' +
                            '<div class="eop-item-discount-group">' +
                                '<input type="text" class="eop-item-discount" value="' + (discVal > 0 ? formatDiscountInput(discType, discVal) : '') + '" placeholder="' + discPlaceholder + '" inputmode="decimal" />' +
                                discSuffix +
                            '</div>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + escapeHtml(i18n.label_discounted_unit_price || 'Valor c/ desconto') + '</span>' +
                            '<span class="eop-item-card__value eop-item-card__discounted-unit-price">' + formatBRL(discountedUnitPrice) + '</span>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + escapeHtml(i18n.label_subtotal || 'Subtotal') + '</span>' +
                            '<span class="eop-item-card__value eop-item-card__subtotal">' + formatBRL(sub) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $body.append(card);
        });

        recalcTotals();
        persistDraftState();
    }

    function renderPostConfirmationFlowPlaceholder(message) {
        var $card = $('#eop-post-flow-card');

        if (!$card.length) {
            return;
        }

        $('#eop-post-flow-badge').text(i18n.post_flow_stage_inactive || 'Inativo').attr('class', 'eop-post-flow-badge is-inactive');
        $('#eop-post-flow-subtitle').text(message || i18n.post_flow_unavailable_edit || 'O resumo complementar aparece quando um pedido existente entra em modo de edicao.');
        $('#eop-post-flow-stats').empty();
        $('#eop-post-flow-contract').text(i18n.post_flow_not_available || 'Este pedido nao esta usando o fluxo complementar da proposta.');
        $('#eop-post-flow-signature-documents').html('<p>' + escapeHtml(i18n.post_flow_signature_documents_empty || 'Nenhum documento para assinatura foi gerado ainda.') + '</p>');
        $('#eop-post-flow-order-data').html('<p>' + escapeHtml(i18n.post_flow_documents_empty || 'Nenhum dado do pedido preenchido no WooCommerce ate agora.') + '</p>');
        $('#eop-post-flow-attachment').html('<p>' + escapeHtml(i18n.post_flow_attachment_missing || 'Nenhum anexo registrado.') + '</p>');
        $('#eop-post-flow-products').html('<p>' + escapeHtml(i18n.post_flow_products_empty || 'Nenhuma personalizacao registrada ate agora.') + '</p>');
        $('#eop-post-flow-downloads').html('<p>' + escapeHtml(i18n.post_flow_downloads_empty || 'Nenhum download complementar disponivel ainda.') + '</p>');
        $('#eop-post-flow-public-link').prop('hidden', true).attr('href', '#');
        $('#eop-post-flow-pdf-link').prop('hidden', true).attr('href', '#');
        $('#eop-post-flow-final-pdf-link').prop('hidden', true).attr('href', '#');
        resetPostFlowStageControls();
    }

    function renderStageControlOptions(options, selectedValue) {
        var html = '';

        (options || []).forEach(function (option) {
            var value = option && typeof option.value !== 'undefined' ? String(option.value) : '';
            var label = option && option.label ? String(option.label) : value;
            var selected = String(selectedValue || 'auto') === value ? ' selected' : '';

            html += '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
        });

        return html;
    }

    function resetPostFlowStageControls() {
        $('#eop-post-flow-stage-toolbar').prop('hidden', true);
        $('#eop-post-flow-stage-select').empty().prop('disabled', true);
        $('#eop-post-flow-stage-apply').prop('disabled', true).data('orderId', 0).text(i18n.post_flow_stage_apply || 'Atualizar etapa');
        $('#eop-post-flow-stage-hint').text(i18n.post_flow_stage_hint_auto || 'Use Automatico para voltar ao fluxo calculado pelo sistema.');
    }

    function renderPostFlowStageControls(flow) {
        var controls = flow && flow.stage_controls ? flow.stage_controls : null;
        var orderId = flow && flow.order && flow.order.id ? parseInt(flow.order.id, 10) : (currentEditingOrderId || 0);

        if (!controls || !controls.can_update || !controls.options || !controls.options.length || !orderId) {
            resetPostFlowStageControls();
            return;
        }

        $('#eop-post-flow-stage-toolbar').prop('hidden', false);
        $('#eop-post-flow-stage-select').html(renderStageControlOptions(controls.options, controls.selected || 'auto')).prop('disabled', false);
        $('#eop-post-flow-stage-apply').prop('disabled', false).data('orderId', orderId).text(i18n.post_flow_stage_apply || 'Atualizar etapa');
        $('#eop-post-flow-stage-hint').text((controls.manual_stage ? (i18n.post_flow_stage_hint_manual || 'Etapa travada manualmente. Use Automatico para voltar ao fluxo calculado pelo sistema.') : (i18n.post_flow_stage_hint_auto || 'Use Automatico para voltar ao fluxo calculado pelo sistema.')));
    }

    function appendPostFlowDownloadRow($container, label, url, linkLabel, note) {
        if (!$container || !$container.length || !url) {
            return;
        }

        $container.append(
            '<div class="eop-post-flow-row eop-post-flow-row--download">' +
                '<strong>' + escapeHtml(label || '') + '</strong>' +
                '<span>' +
                    '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(linkLabel || label || 'Abrir') + '</a>' +
                    (note ? '<small>' + escapeHtml(note) + '</small>' : '') +
                '</span>' +
            '</div>'
        );
    }

    function renderPostConfirmationFlow(flow) {
        flow = flow || {};

        if (!flow.active_for_order) {
            renderPostConfirmationFlowPlaceholder(i18n.post_flow_not_available || 'Este pedido nao esta usando o fluxo complementar da proposta.');
            return;
        }

        renderPostFlowStageControls(flow);

        $('#eop-post-flow-badge').text((flow.status && flow.status.current_stage_label) || '').attr('class', 'eop-post-flow-badge is-active');
        $('#eop-post-flow-subtitle').text((flow.status && flow.status.completed_at) ? ((i18n.post_flow_completed_at || 'Concluido em') + ' ' + flow.status.completed_at + '.') : (i18n.post_flow_summary_ready || 'Payload estruturado pronto para PDF, admin e integracoes futuras.'));
        $('#eop-post-flow-stats').empty();
        $('#eop-post-flow-signature-documents').empty();
        $('#eop-post-flow-order-data').empty();
        $('#eop-post-flow-attachment').empty();
        $('#eop-post-flow-products').empty();
        $('#eop-post-flow-downloads').empty();

        var contractAccepted = !!(flow.contract && flow.contract.accepted);
        var proposalConfirmed = !!(flow.order && flow.order.proposal_confirmed);
        var attachmentRequired = !!(flow.summary && flow.summary.attachment_required);
        var attachmentUploaded = !!(flow.summary && flow.summary.attachment_uploaded);
        var productsEditable = (flow.summary && typeof flow.summary.products_editable !== 'undefined') ? (parseInt(flow.summary.products_editable, 10) || 0) : 0;
        var productsCompleted = (flow.summary && typeof flow.summary.products_completed !== 'undefined') ? (parseInt(flow.summary.products_completed, 10) || 0) : 0;
        var currentStage = (flow.status && flow.status.current_stage) ? String(flow.status.current_stage) : '';
        var stageRankMap = {
            awaiting_confirmation: 1,
            payment: 2,
            contract: 3,
            upload: 4,
            completed: 5
        };
        var currentRank = stageRankMap[currentStage] || 0;
        var paymentNeeded = currentStage === 'payment';
        var paymentDone = currentRank > 2 || (proposalConfirmed && !paymentNeeded);
        var contractDone = contractAccepted || currentRank > 3;
        var uploadDone = currentStage === 'completed';
        var $downloads = $('#eop-post-flow-downloads');

        [
            {
                label: i18n.post_flow_stat_confirmation || 'Aguardando confirmacao do pedido',
                value: proposalConfirmed ? (i18n.post_flow_confirmed || 'Confirmado') : (i18n.post_flow_pending || 'Pendente'),
                detail: proposalConfirmed ? (i18n.post_flow_confirmation_done_detail || 'Pedido confirmado pelo cliente') : (i18n.post_flow_confirmation_pending_detail || 'Cliente ainda nao confirmou'),
                tone: proposalConfirmed ? 'success' : 'warning'
            },
            {
                label: i18n.post_flow_stat_payment || 'Pagamento',
                value: paymentDone ? (i18n.post_flow_confirmed || 'Confirmado') : (i18n.post_flow_pending || 'Pendente'),
                detail: (!paymentNeeded && proposalConfirmed) ? (i18n.post_flow_not_required || 'Nao obrigatorio') : (paymentDone ? (i18n.post_flow_payment_done || 'Pagamento aprovado') : (i18n.post_flow_payment_waiting || 'Aguardando pagamento')),
                tone: (!paymentNeeded && proposalConfirmed) ? 'neutral' : (paymentDone ? 'success' : 'warning')
            },
            {
                label: i18n.post_flow_stat_contract || 'Contrato',
                value: contractDone ? (i18n.post_flow_confirmed || 'Confirmado') : (i18n.post_flow_pending || 'Pendente'),
                detail: (flow.contract && flow.contract.accepted_at) ? flow.contract.accepted_at : (contractDone ? (i18n.post_flow_contract_done_short || 'Aceite registrado') : (i18n.post_flow_contract_pending || 'Aceite contratual pendente.')),
                tone: contractDone ? 'success' : 'warning'
            },
            {
                label: i18n.post_flow_stat_upload || 'Upload e personalizacao',
                value: uploadDone ? (i18n.post_flow_confirmed || 'Confirmado') : (i18n.post_flow_pending || 'Pendente'),
                detail: uploadDone ? (i18n.post_flow_upload_stage_done || 'Arquivo e personalizacao concluidos') : (attachmentRequired && !attachmentUploaded ? (i18n.post_flow_upload_attachment_waiting || 'Aguardando envio do anexo') : ((productsEditable > 0 && productsCompleted < productsEditable) ? ('Personalizacao pendente (' + productsCompleted + '/' + productsEditable + ')') : (attachmentUploaded ? (i18n.post_flow_upload_finishing || 'Aguardando concluir a etapa final') : (i18n.post_flow_upload_stage_waiting || 'Etapa final pendente')))),
                tone: uploadDone ? 'success' : 'info'
            }
        ].forEach(function (stat) {
            $('#eop-post-flow-stats').append(
                '<div class="eop-post-flow-stat eop-post-flow-stat--' + escapeHtml(stat.tone || 'neutral') + '">' +
                    '<span>' + escapeHtml(stat.label) + '</span>' +
                    '<strong>' + escapeHtml(stat.value) + '</strong>' +
                    (stat.detail ? ('<small>' + escapeHtml(stat.detail) + '</small>') : '') +
                '</div>'
            );
        });

        if (flow.contract && flow.contract.accepted) {
            $('#eop-post-flow-contract').text((i18n.post_flow_contract_done || 'Aceite registrado.') + ' ' + [flow.contract.accepted_name, flow.contract.accepted_at].filter(Boolean).join(' • '));
        } else {
            $('#eop-post-flow-contract').text(i18n.post_flow_contract_pending || 'Aceite contratual pendente.');
        }

        if (flow.signature_documents && flow.signature_documents.length) {
            flow.signature_documents.forEach(function (documentRow) {
                $('#eop-post-flow-signature-documents').append(
                    '<div class="eop-post-flow-row">' +
                        '<strong>' + escapeHtml(documentRow.title || '') + '</strong>' +
                        '<span><a href="' + escapeHtml(documentRow.admin_view_url || documentRow.public_view_url || '#') + '" target="_blank" rel="noopener">' + escapeHtml(documentRow.filename || documentRow.title || 'PDF') + '</a></span>' +
                    '</div>'
                );
            });
        }

        if (!$('#eop-post-flow-signature-documents').children().length) {
            $('#eop-post-flow-signature-documents').html('<p>' + escapeHtml(i18n.post_flow_signature_documents_empty || 'Nenhum documento para assinatura foi gerado ainda.') + '</p>');
        }

        if (orderData.length) {
            orderData.forEach(function (documentField) {
                if (!documentField.filled) {
                    return;
                }

                $('#eop-post-flow-order-data').append(
                    '<div class="eop-post-flow-row">' +
                        '<strong>' + escapeHtml(documentField.label || '') + '</strong>' +
                        '<span>' + escapeHtml(documentField.value || '') + '</span>' +
                    '</div>'
                );
            });
        }

        if (!$('#eop-post-flow-order-data').children().length) {
            $('#eop-post-flow-order-data').html('<p>' + escapeHtml(i18n.post_flow_documents_empty || 'Nenhum dado do pedido preenchido no WooCommerce ate agora.') + '</p>');
        }

        if (flow.attachment && flow.attachment.id) {
            $('#eop-post-flow-attachment').append(
                '<div class="eop-post-flow-row">' +
                    '<strong>' + escapeHtml(flow.attachment.filename || '') + '</strong>' +
                    '<span>' + escapeHtml(flow.attachment.uploaded_at || '') + '</span>' +
                '</div>'
            );

            if (flow.attachment.url) {
                $('#eop-post-flow-attachment').append('<p><a href="' + escapeHtml(flow.attachment.url) + '" target="_blank" rel="noopener">' + escapeHtml(flow.attachment.filename || 'Arquivo') + '</a></p>');
                appendPostFlowDownloadRow($downloads, i18n.post_flow_link_attachment || 'Logo enviada', flow.attachment.url, flow.attachment.filename || 'Arquivo', flow.attachment.uploaded_at || '');
            }
        } else {
            $('#eop-post-flow-attachment').html('<p>' + escapeHtml(i18n.post_flow_attachment_missing || 'Nenhum anexo registrado.') + '</p>');
        }

        if (flow.products && flow.products.length) {
            flow.products.forEach(function (productRow) {
                var statusLabel = productRow.locked ? (i18n.post_flow_locked || 'Bloqueado') : (productRow.custom_name ? (i18n.post_flow_customized || 'Personalizado') : (i18n.post_flow_pending || 'Pendente'));
                var valueLabel = productRow.custom_name || productRow.original_name || '';

                $('#eop-post-flow-products').append(
                    '<div class="eop-post-flow-row">' +
                        '<strong>' + escapeHtml(productRow.original_name || '') + '</strong>' +
                        '<span>' + escapeHtml(valueLabel + ' • ' + statusLabel) + '</span>' +
                    '</div>'
                );
            });
        } else {
            $('#eop-post-flow-products').html('<p>' + escapeHtml(i18n.post_flow_products_empty || 'Nenhuma personalizacao registrada ate agora.') + '</p>');
        }

        if (flow.links && flow.links.public_url) {
            appendPostFlowDownloadRow($downloads, i18n.post_flow_link_public || 'Jornada publica', flow.links.public_url, i18n.post_flow_open_public || 'Abrir link publico');
        }

        if (flow.signature_documents && flow.signature_documents.length) {
            flow.signature_documents.forEach(function (documentRow) {
                appendPostFlowDownloadRow($downloads, documentRow.title || (i18n.post_flow_stat_signature_documents || 'Documentos para assinatura'), documentRow.admin_view_url || documentRow.public_view_url || '', documentRow.filename || documentRow.title || 'PDF');
            });
        }

        if (flow.links && flow.links.admin_pdf_url) {
            appendPostFlowDownloadRow($downloads, i18n.post_flow_download_pdf || 'Baixar PDF complementar', flow.links.admin_pdf_url, i18n.post_flow_download_pdf || 'Baixar PDF complementar');
        }

        if (flow.links && flow.links.admin_final_pdf_url) {
            appendPostFlowDownloadRow($downloads, i18n.post_flow_stat_final_pdf || 'PDF final', flow.links.admin_final_pdf_url, i18n.post_flow_download_final_pdf || 'Baixar PDF final da personalizacao', (flow.final_customization_pdf && flow.final_customization_pdf.generated_at) ? flow.final_customization_pdf.generated_at : '');
        }

        if (!$downloads.children().length) {
            $downloads.html('<p>' + escapeHtml(i18n.post_flow_downloads_empty || 'Nenhum download complementar disponivel ainda.') + '</p>');
        }

        if (flow.links && flow.links.public_url) {
            $('#eop-post-flow-public-link').prop('hidden', false).attr('href', flow.links.public_url).text(i18n.post_flow_open_public || 'Abrir link publico');
        } else {
            $('#eop-post-flow-public-link').prop('hidden', true).attr('href', '#');
        }

        if (flow.links && flow.links.admin_pdf_url) {
            $('#eop-post-flow-pdf-link').prop('hidden', false).attr('href', flow.links.admin_pdf_url).text(i18n.post_flow_download_pdf || 'Baixar PDF complementar');
        } else {
            $('#eop-post-flow-pdf-link').prop('hidden', true).attr('href', '#');
        }

        if (flow.links && flow.links.admin_final_pdf_url) {
            $('#eop-post-flow-final-pdf-link').prop('hidden', false).attr('href', flow.links.admin_final_pdf_url).text(i18n.post_flow_download_final_pdf || 'Baixar PDF final da personalizacao');
        } else {
            $('#eop-post-flow-final-pdf-link').prop('hidden', true).attr('href', '#');
        }
    }

    function updatePostConfirmationStage(orderId, stageValue, onSuccess, onComplete) {
        var normalizedOrderId = parseInt(orderId, 10) || 0;

        if (!normalizedOrderId) {
            showNotice(i18n.error || 'Erro ao criar pedido. Tente novamente.', 'error');
            if (typeof onComplete === 'function') {
                onComplete(false);
            }
            return;
        }

        $.post(eop_vars.ajax_url, {
            action: 'eop_set_post_confirmation_stage',
            nonce: eop_vars.nonce,
            order_id: normalizedOrderId,
            stage: stageValue || 'auto'
        }, function (res) {
            if (!res || !res.success) {
                showNotice((res && res.data && res.data.message) ? res.data.message : (i18n.error || 'Erro ao criar pedido. Tente novamente.'), 'error');
                if (typeof onComplete === 'function') {
                    onComplete(false, res);
                }
                return;
            }

            removeCache(sessionStore, getPostFlowCacheKey(normalizedOrderId));
            removeCache(sessionStore, getOrderCacheKey(normalizedOrderId));
            removeCacheByPrefix(sessionStore, getCacheKey('orders-list', ''));
            ordersLoaded = false;

            if (typeof onSuccess === 'function') {
                onSuccess(res.data || {});
            }

            if ($('#eop-orders-list').length) {
                loadOrdersList(ordersPage || 1);
            }

            if (currentEditingOrderId === normalizedOrderId && res.data && res.data.flow) {
                renderPostConfirmationFlow(res.data.flow);
            }

            showNotice((res.data && res.data.message) ? res.data.message : (i18n.post_flow_stage_apply || 'Atualizar etapa'), 'success');

            if (typeof onComplete === 'function') {
                onComplete(true, res);
            }
        }).fail(function () {
            showNotice(i18n.error || 'Erro ao criar pedido. Tente novamente.', 'error');
            if (typeof onComplete === 'function') {
                onComplete(false);
            }
        });
    }

    function loadPostConfirmationFlow(orderId) {
        var requestToken = postFlowRequestToken + 1;
        var baseUrl = String(eop_vars.rest_url || '');
        var cachedFlow = readCache(sessionStore, getPostFlowCacheKey(orderId), 2 * 60 * 1000);

        postFlowRequestToken = requestToken;

        if (!orderId || !baseUrl) {
            renderPostConfirmationFlowPlaceholder(i18n.post_flow_not_available || 'Este pedido nao esta usando o fluxo complementar da proposta.');
            return;
        }

        renderPostConfirmationFlowPlaceholder(i18n.post_flow_loading || 'Carregando dados complementares da proposta...');

        if (cachedFlow) {
            renderPostConfirmationFlow(cachedFlow);
            return;
        }

        window.fetch(baseUrl + 'orders/' + encodeURIComponent(orderId) + '/post-confirmation?context=internal', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': String(eop_vars.rest_nonce || ''),
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('rest_error');
            }

            return response.json();
        }).then(function (data) {
            if (requestToken !== postFlowRequestToken) {
                return;
            }

            writeCache(sessionStore, getPostFlowCacheKey(orderId), data || {});
            renderPostConfirmationFlow(data || {});
        }).catch(function () {
            if (requestToken !== postFlowRequestToken) {
                return;
            }

            renderPostConfirmationFlowPlaceholder(i18n.post_flow_not_available || 'Este pedido nao esta usando o fluxo complementar da proposta.');
        });
    }

    function resetForm() {
        editLoadToken += 1;
        postFlowRequestToken += 1;
        items = [];
        renderItems();

        $('#eop-document, #eop-name, #eop-email, #eop-phone').val('');
        $('#eop-shipping-postcode, #eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2').val('');
        $('#eop-user-id').val(0);
        $('#eop-shipping').val(0);
        $('#eop-discount').val('');
        $('#eop-default-item-quantity').val(1);
        $('#eop-default-item-discount').val('');
        $('#eop-status').val('completed');
        $('#eop-product-search').val(null).trigger('change');
        $('#eop-customer-status').text('').attr('class', 'eop-status');
        $('#eop-public-link').hide().attr('href', '#');
        $('#eop-success-modal').hide();
        selectedShippingRate = null;
        shippingAddress = {};
        setShippingPanelState(false);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');
        exitEditMode();
        clearDraftState();

        recalcTotals();
    }

    function getShippingAddress() {
        return {
            country: 'BR',
            postcode: sanitizePostcode($('#eop-shipping-postcode').val().trim()),
            state: $('#eop-shipping-state').val().trim(),
            city: $('#eop-shipping-city').val().trim(),
            address: $('#eop-shipping-address').val().trim(),
            number: $('#eop-shipping-number').val().trim(),
            neighborhood: $('#eop-shipping-neighborhood').val().trim(),
            address_2: $('#eop-shipping-address-2').val().trim()
        };
    }

    function renderShippingRates(packages) {
        var html = '';

        packages.forEach(function (pkg, pkgIndex) {
            html += '<div class="eop-shipping-group">';
            html += '<div class="eop-shipping-group__title">' + $('<span>').text(pkg.package_name).html() + '</div>';

            pkg.rates.forEach(function (rate, rateIndex) {
                var details = [];

                if (rate.meta_data) {
                    Object.keys(rate.meta_data).forEach(function (key) {
                        details.push(rate.meta_data[key]);
                    });
                }

                html += '<label class="eop-shipping-rate">';
                html += '<input type="radio" name="eop_shipping_rate_' + pkgIndex + '" value="' + $('<span>').text(JSON.stringify(rate)).html() + '" ' + (pkgIndex === 0 && rateIndex === 0 ? 'checked' : '') + '>';
                html += '<span class="eop-shipping-rate__content">';
                html += '<strong>' + $('<span>').text(rate.label).html() + '</strong>';
                html += '<em>' + formatBRL(rate.cost) + '</em>';
                if (details.length) {
                    html += '<small>' + $('<span>').text(details.join(' | ')).html() + '</small>';
                }
                html += '</span>';
                html += '</label>';
            });

            html += '</div>';
        });

        $('#eop-shipping-rates').html(html);
        setShippingSummary(i18n.shipping_summary_ready || 'Escolha a opcao de frete que melhor atende o cliente.');
        $('input[name^="eop_shipping_rate_"]').first().trigger('change');
    }

    function fillAddressFromPostcode(data) {
        if (data.uf) {
            $('#eop-shipping-state').val(data.uf);
        }

        if (data.localidade) {
            $('#eop-shipping-city').val(data.localidade);
        }

        if (data.logradouro) {
            $('#eop-shipping-address').val(data.logradouro);
        }

        if (data.bairro) {
            $('#eop-shipping-neighborhood').val(data.bairro);
        }
    }

    function lookupPostcode(force) {
        var postcode = sanitizePostcode($('#eop-shipping-postcode').val());

        $('#eop-shipping-postcode').val(formatPostcode(postcode));

        if (postcode.length !== 8) {
            if (force) {
                setShippingAddressStatus(i18n.shipping_postcode_invalid || 'Digite um CEP valido com 8 numeros.', 'error');
            }
            return;
        }

        if (postcodeLookupBusy) {
            return;
        }

        if (postcodeLookupCache[postcode]) {
            fillAddressFromPostcode(postcodeLookupCache[postcode]);
            setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado. Confira o numero e o complemento.', 'success');
            return;
        }

        postcodeLookupBusy = true;
        setShippingAddressStatus(i18n.shipping_postcode_loading || 'Buscando endereco pelo CEP...', 'loading');

        fetch('https://viacep.com.br/ws/' + postcode + '/json/')
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('cep_lookup_failed');
                }

                return response.json();
            })
            .then(function (data) {
                postcodeLookupBusy = false;

                if (!data || data.erro) {
                    setShippingAddressStatus(i18n.shipping_postcode_not_found || 'Nao encontramos esse CEP. Preencha o endereco manualmente.', 'error');
                    return;
                }

                postcodeLookupCache[postcode] = data;
                fillAddressFromPostcode(data);
                setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado. Confira o numero e o complemento.', 'success');
                setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
            })
            .catch(function () {
                postcodeLookupBusy = false;
                setShippingAddressStatus(i18n.shipping_postcode_error || 'Nao foi possivel buscar o CEP agora. Continue manualmente.', 'error');
            });
    }

    function syncNoticeVisibility() {
        var $notices = $('#eop-notices');

        if (!$notices.length) {
            return;
        }

        var hasVisibleNotice = $notices.children('.notice:visible, div.notice:visible').length > 0;
        var hasTextContent = $.trim($notices.text()).length > 0;
        var shouldShow = hasVisibleNotice || hasTextContent;

        $notices.prop('hidden', !shouldShow);

        if (!shouldShow) {
            $notices.empty();
        }
    }

    function showNotice(message, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var html = '<div class="notice ' + cls + ' is-dismissible"><p>' + $('<span>').text(message).html() + '</p></div>';

        $('#eop-notices').html(html);
        syncNoticeVisibility();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $(document).on('click', '#eop-notices .notice-dismiss', function () {
        window.setTimeout(syncNoticeVisibility, 180);
    });

    syncNoticeVisibility();

    $(document).on('click', '#eop-search-customer', function () {
        var doc = $('#eop-document').val().replace(/\D/g, '');

        if (!doc) {
            return;
        }

        var $status = $('#eop-customer-status');
        $status.text('Buscando...').attr('class', 'eop-status searching');

        $.post(eop_vars.ajax_url, {
            action: 'eop_search_customer',
            nonce: eop_vars.nonce,
            document: doc
        }, function (res) {
            if (res.success && res.data.found) {
                $('#eop-user-id').val(res.data.user_id);
                $('#eop-name').val(res.data.name);
                $('#eop-email').val(res.data.email);
                $('#eop-phone').val(res.data.phone);
                $status.text('Cliente encontrado!').attr('class', 'eop-status found');
                return;
            }

            $('#eop-user-id').val(0);
            $status.text('Nao encontrado. Preencha manualmente.').attr('class', 'eop-status not-found');
        }).fail(function () {
            $status.text('Erro na busca.').attr('class', 'eop-status not-found');
        });
    });

    $(document).on('keypress', '#eop-document', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#eop-search-customer').trigger('click');
        }
    });

    $(document).on('select2:select', '#eop-product-search', function (e) {
        var selected = e.params.data;
        var defaultQuantity = getDefaultItemQuantity();
        var defaultDiscount = getDefaultItemDiscount();
        var existing = items.find(function (item) {
            return item.product_id === selected.id;
        });

        if (existing) {
            existing.quantity += defaultQuantity;
        } else {
            items.push({
                product_id: selected.id,
                name: selected.name,
                sku: selected.sku || '',
                price: selected.price,
                image: selected.image || '',
                quantity: defaultQuantity,
                discount_type: defaultDiscount.type,
                discount_value: defaultDiscount.value
            });
        }

        renderItems();
        $(this).val(null).trigger('change');
    });

    $(document).on('change input', '.eop-qty', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var val = parseInt($(this).val(), 10);

        if (val < 1) {
            val = 1;
        }

        items[idx].quantity = val;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-qty-dec', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var current = parseInt(items[idx].quantity, 10) || 1;
        var next = Math.max(1, current - 1);

        items[idx].quantity = next;
        $card.find('.eop-qty').val(next);
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-qty-inc', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var current = parseInt(items[idx].quantity, 10) || 1;
        var next = current + 1;

        items[idx].quantity = next;
        $card.find('.eop-qty').val(next);
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '.eop-remove-item', function (e) {
        var idx;

        e.preventDefault();
        idx = $(this).closest('.eop-item-card').data('idx');

        items.splice(idx, 1);
        renderItems();
    });

    $(document).on('click', '#eop-apply-item-defaults', function () {
        applyItemDefaultsToAll();
    });

    $(document).on('input change', '#eop-shipping, #eop-discount', recalcTotals);

    $(document).on('change input', '.eop-item-discount', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var parsed = parseDiscountInput($(this).val());

        items[idx].discount_type = parsed.type;
        items[idx].discount_value = parsed.value;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $(document).on('click', '#eop-shipping-toggle', function () {
        var isOpen = $(this).attr('aria-expanded') === 'true';

        setShippingPanelState(!isOpen);

        if (!isOpen && !$('#eop-shipping-postcode').val()) {
            setShippingAddressStatus(i18n.shipping_panel_hint || 'Comece pelo CEP. O sistema tenta preencher o endereco automaticamente.', 'info');
        }
    });

    $(document).on('input', '#eop-shipping-postcode', function () {
        var formatted = formatPostcode($(this).val());
        var digits = sanitizePostcode(formatted);

        $(this).val(formatted);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');

        if (digits.length === 8) {
            lookupPostcode(false);
        }
    });

    $(document).on('blur', '#eop-shipping-postcode', function () {
        lookupPostcode(true);
    });

    $(document).on('input change', '#eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2', function () {
        if (selectedShippingRate) {
            clearShippingSelection(true);
        } else {
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
        }
    });

    $(document).on('click', '#eop-calc-shipping', function () {
        var $btn = $(this);
        shippingAddress = getShippingAddress();

        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        if (!shippingAddress.postcode || !shippingAddress.city || !shippingAddress.address || !shippingAddress.number) {
            showNotice(i18n.shipping_missing, 'error');
            return;
        }

        $btn.prop('disabled', true).text(i18n.shipping_loading);
        setShippingAddressStatus(i18n.shipping_loading || 'Calculando frete...', 'loading');
        clearShippingSelection(false);

        $.post(eop_vars.ajax_url, {
            action: 'eop_calculate_shipping',
            nonce: eop_vars.nonce,
            items: JSON.stringify(items.map(function (item) {
                return {
                    product_id: item.product_id,
                    quantity: item.quantity
                };
            })),
            address: JSON.stringify(shippingAddress)
        }, function (res) {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);

            if (!res.success) {
                setShippingAddressStatus('', '');
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }

            setShippingAddressStatus(i18n.shipping_rates_found || 'Opcoes encontradas. Escolha a melhor para o cliente.', 'success');
            renderShippingRates(res.data.rates);
        }).fail(function () {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            setShippingAddressStatus('', '');
            showNotice(i18n.error, 'error');
        });
    });

    $(document).on('change', 'input[name^="eop_shipping_rate_"]', function () {
        var raw = $(this).val();

        try {
            selectedShippingRate = JSON.parse(raw);
            $('#eop-shipping').val(selectedShippingRate.cost || 0);
            shippingAddress = getShippingAddress();
            setShippingSummary((selectedShippingRate.label || 'Frete selecionado') + ' - ' + formatBRL(selectedShippingRate.cost || 0));
            recalcTotals();
        } catch (err) {
            selectedShippingRate = null;
            $('#eop-shipping').val(0);
            setShippingSummary(i18n.shipping_summary_pending || 'Preencha o endereco e escolha uma opcao de frete.');
            recalcTotals();
        }
    });

    $(document).on('click', '#eop-submit', function () {
        var $btn;
        var orderData;
        var requestAction;

        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        $btn = $(this);
        $btn.prop('disabled', true).text(i18n.processing);

        orderData = {
            order_id: currentEditingOrderId || 0,
            customer: {
                user_id: parseInt($('#eop-user-id').val(), 10) || 0,
                name: $('#eop-name').val().trim(),
                email: $('#eop-email').val().trim(),
                phone: $('#eop-phone').val().trim(),
                document: $('#eop-document').val().replace(/\D/g, '')
            },
            items: items.map(function (item) {
                return {
                    product_id: item.product_id,
                    quantity: item.quantity,
                    discount_type: item.discount_type || 'percent',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            }),
            shipping: parseFloat($('#eop-shipping').val()) || 0,
            shipping_rate: selectedShippingRate || null,
            shipping_method: selectedShippingRate ? selectedShippingRate.label : '',
            shipping_address: items.length ? getShippingAddress() : shippingAddress,
            discount: parseDiscountInput($('#eop-discount').val()).value,
            discount_type: parseDiscountInput($('#eop-discount').val()).type,
            status: $('#eop-status').val()
        };

        requestAction = currentEditingOrderId ? 'eop_update_order' : 'eop_create_order';

        $.post(eop_vars.ajax_url, {
            action: requestAction,
            nonce: eop_vars.nonce,
            order_data: JSON.stringify(orderData)
        }, function (res) {
            $btn.prop('disabled', false).text(getCurrentSubmitLabel());

            if (!res.success) {
                showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                return;
            }

            if (currentEditingOrderId) {
                showNotice(res.data && res.data.message ? res.data.message : (i18n.edit_submit || 'Salvar alteracoes'), 'success');
                ordersLoaded = false;
                removeCache(sessionStore, getOrderCacheKey(currentEditingOrderId));
                removeCache(sessionStore, getPostFlowCacheKey(currentEditingOrderId));
                removeCacheByPrefix(sessionStore, getCacheKey('orders-list', ''));
                loadOrdersList(ordersPage || 1);
                exitEditMode();
                setAppView('orders');
                return;
            }

            $('#eop-success-message').text('Pedido #' + res.data.order_id + ' criado com sucesso.');
            $('#eop-pdf-link').attr('href', res.data.pdf_url);
            $('#eop-order-link').attr('href', res.data.order_url);
            if (res.data.public_url) {
                $('#eop-public-link').attr('href', res.data.public_url).show();
                $('#eop-success-message').text('Proposta #' + res.data.order_id + ' criada com sucesso.');
            } else {
                $('#eop-public-link').hide().attr('href', '#');
            }
            $('#eop-success-modal').show();

            if (res.data.pdf_url) {
                window.open(res.data.pdf_url, '_blank');
            }

            ordersLoaded = false;
            clearDraftState();
            removeCacheByPrefix(sessionStore, getCacheKey('orders-list', ''));
        }).fail(function () {
            $btn.prop('disabled', false).text(getCurrentSubmitLabel());
            showNotice(i18n.error, 'error');
        });
    });

    $(document).on('click', '#eop-new-order', function () {
        resetForm();
        setAppView('new-order');
    });

    $(document).on('click', '#eop-cancel-edit', function () {
        resetForm();
        showNotice(i18n.edit_cancel || 'Edicao cancelada.', 'success');
    });

    $(document).on('click', '.eop-pdv-nav__item', function () {
        if ($(this).is('[data-eop-nav-toggle]')) {
            toggleNavGroup($(this));
            return;
        }

        var targetView = $(this).data('eop-view-target');

        if (targetView === 'new-order') {
            resetForm();
            setAppView('new-order');
            return;
        }

        setAppView(targetView, { skipLazy: true });

        if (isLazyViewPending(targetView)) {
            loadLazyView(targetView, { skipHistory: true });
        }
    });

    $(document).on('click', '.eop-admin-spa-nav__submenu-item', function (e) {
        var targetView = $(this).data('eop-view-target');
        var url;

        if (targetView) {
            if (targetView === 'new-order') {
                resetForm();
            }

            setAppView(targetView);
            return;
        }

        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.which === 2) {
            return;
        }

        url = $(this).attr('href');

        if (!url) {
            return;
        }

        e.preventDefault();
        setAppView('pdf', { skipHistory: true, skipLazy: true });

        if (isLazyViewPending('pdf')) {
            loadLazyView('pdf', { url: url, skipHistory: true });
            return;
        }

        loadPdfTab(url, { preservePreview: true });
    });

    $(document).on('submit', '.eop-pdf-admin__sidebar .eop-pdf-admin__form', function () {
        clearPdfTabCache();
        clearLazyViewCache();
    });

    $(document).on('submit', '.eop-settings-form, .eop-admin-license-shell form', function () {
        clearLazyViewCache();
    });

    $(document).on('input change', '.eop-pdf-admin__sidebar .eop-pdf-admin__form :input', function () {
        var $form = $(this).closest('.eop-pdf-admin__form');

        if (typeof $form.data('eopSnapshot') === 'undefined') {
            $form.data('eopSnapshot', getPdfSettingsFormSnapshot($form));
        }
    });

    $(document).on('change', '.eop-pdf-admin__preview-toolbar select', function () {
        var $form = $(this).closest('.eop-pdf-admin__preview-toolbar');
        var targetUrl = buildPdfToolbarUrl($form.get(0));
        var targetView = new URL(targetUrl, window.location.origin).searchParams.get('view') || 'pdf';

        savePendingPdfSettings().done(function () {
            if (targetView !== 'pdf') {
                window.location.href = targetUrl;
                return;
            }

            setAppView('pdf', { skipHistory: true });
            loadPdfTab(targetUrl, { preservePreview: true });
        }).fail(function () {
            showNotice(i18n.error || 'Nao foi possivel salvar as configuracoes do PDF.', 'error');
        });
    });

    $(document).on('submit', '.eop-pdf-admin__preview-toolbar', function (e) {
        var submitter = e.originalEvent && e.originalEvent.submitter ? $(e.originalEvent.submitter) : $();
        var toolbar = this;
        var pdfUrl = submitter.length && submitter.is('.button-primary[formaction]') ? submitter.attr('formaction') : '';
        var targetUrl = buildPdfToolbarUrl(toolbar);
        var targetView = new URL(targetUrl, window.location.origin).searchParams.get('view') || 'pdf';

        e.preventDefault();

        savePendingPdfSettings().done(function () {
            if (pdfUrl) {
                window.location.href = pdfUrl;
                return;
            }

            if (targetView !== 'pdf') {
                window.location.href = targetUrl;
                return;
            }

            setAppView('pdf', { skipHistory: true });
            loadPdfTab(targetUrl, { preservePreview: true });
        }).fail(function () {
            showNotice(i18n.error || 'Nao foi possivel salvar as configuracoes do PDF.', 'error');
        });
    });

    syncPdfSettingsFormSnapshot($(document));

    $(document).on('click', '.eop-order-edit-spa', function () {
        var orderId = parseInt($(this).data('order-id'), 10) || 0;

        if (!orderId) {
            return;
        }

        loadOrderForEditing(orderId);
    });

    $(document).on('click', '#eop-admin-chrome-toggle', function () {
        applyPluginFullscreen(!$('body').hasClass('is-plugin-fullscreen'));
    });

    $(document).on('input change', '#eop-document, #eop-name, #eop-email, #eop-phone, #eop-default-item-quantity, #eop-default-item-discount, #eop-discount, #eop-status, #eop-shipping-postcode, #eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2', function () {
        persistDraftState();
    });

    $(document).on('click', '#eop-orders-refresh', function () {
        removeCacheByPrefix(sessionStore, getCacheKey('orders-list', ''));
        loadOrdersList(1);
    });

    $(document).on('submit', '#eop-orders-filters-form', function (e) {
        e.preventDefault();
        loadOrdersList(1);
    });

    $(document).on('input', '#eop-orders-search', function () {
        clearTimeout(ordersSearchDebounce);
        ordersSearchDebounce = window.setTimeout(function () {
            loadOrdersList(1);
        }, 300);
    });

    $(document).on('keydown', '#eop-orders-search', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(ordersSearchDebounce);
            $('#eop-orders-filters-form').trigger('submit');
        }
    });

    $(document).on('change', '#eop-orders-status-filter', function () {
        loadOrdersList(1);
    });

    $(document).on('change', '#eop-orders-flow-filter', function () {
        loadOrdersList(1);
    });

    $(document).on('click', '.eop-orders-page-btn', function () {
        if ($(this).is(':disabled')) {
            return;
        }

        loadOrdersList($(this).data('page'));
    });

    $(document).on('click', '#eop-success-modal', function (e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    /* Generic accordion toggle */
    $(document).on('click', '.eop-accordion__toggle', function () {
        var $toggle = $(this);
        var $body = $toggle.next('.eop-accordion__body');
        var isOpen = $toggle.attr('aria-expanded') === 'true';
        var $icon = $toggle.find('.eop-accordion__icon').first();

        $toggle.attr('aria-expanded', !isOpen ? 'true' : 'false');
        $body.attr('hidden', isOpen);

        if ($icon.length) {
            $icon.text(isOpen ? '+' : '-');
        }
    });

    $(document).on('click', '[data-eop-pdf-preview-toggle]', function () {
        var $admin = $(this).closest('.eop-pdf-admin');
        setPdfPreviewState($admin, !$admin.hasClass('is-preview-open'));
    });

    $(document).on('click', '[data-eop-pdf-preview-close]', function () {
        setPdfPreviewState($(this).closest('.eop-pdf-admin'), false);
    });

    $(document).on('click', '#eop-post-flow-stage-apply', function () {
        var $button = $(this);
        var orderId = parseInt($button.data('orderId'), 10) || currentEditingOrderId || 0;
        var stageValue = $('#eop-post-flow-stage-select').val() || 'auto';
        var defaultLabel = i18n.post_flow_stage_apply || 'Atualizar etapa';

        $button.prop('disabled', true).text(i18n.post_flow_stage_saving || 'Salvando etapa...');

        updatePostConfirmationStage(orderId, stageValue, null, function () {
            $button.prop('disabled', false).text(defaultLabel);
        });
    });

    $(document).on('click', '.eop-order-card__flow-stage-apply', function () {
        var $button = $(this);
        var $wrapper = $button.closest('.eop-order-card__flow-stage-controls');
        var orderId = parseInt($wrapper.data('orderId'), 10) || 0;
        var stageValue = $wrapper.find('.eop-order-card__flow-stage-select').val() || 'auto';
        var defaultLabel = i18n.post_flow_stage_apply || 'Atualizar etapa';

        $button.prop('disabled', true).text(i18n.post_flow_stage_saving || 'Salvando etapa...');

        updatePostConfirmationStage(orderId, stageValue, null, function () {
            $button.prop('disabled', false).text(defaultLabel);
        });
    });

    $(window).on('popstate', function () {
        var params = new URLSearchParams(window.location.search);
        var view = params.get('view') || eop_vars.initial_view || 'new-order';

        if (view === 'pdf') {
            setAppView('pdf', { replaceState: true, skipHistory: true });
            loadPdfTab(window.location.href, { replaceState: true, skipHistory: true, preservePreview: $('.eop-pdf-admin').hasClass('is-preview-open') });
            return;
        }

        setAppView(view, { replaceState: true, skipHistory: true });
    });

    $(function () {
        var params = new URLSearchParams(window.location.search);
        var bootstrapView = params.get('view') || currentView;
        var bootstrapAction = params.get('action') || '';
        var bootstrapOrderId = parseInt(params.get('order_id') || '0', 10) || 0;

        restorePluginFullscreen();
        initProductSearch($(document));
        syncSidebarState(bootstrapView);
        setPerformanceAuditState(false);
        renderPerformanceAuditPanel();
        registerInitialPerformanceBaseline();

        setAppView(bootstrapView, { replaceState: true, skipLazy: true });

        if (!bootstrapAction && bootstrapOrderId <= 0) {
            restoreDraftState();
        }

        if (isLazyViewPending(bootstrapView)) {
            loadLazyView(bootstrapView, { replaceState: true, skipHistory: true });
        }

        if (bootstrapAction === 'edit' && bootstrapOrderId > 0) {
            loadOrderForEditing(bootstrapOrderId);
        }

        $(document).on('click', '#eop-performance-clear-session', function () {
            clearPerformanceEntries();
        });

        $(document).on('click', '#eop-performance-audit-toggle', function (e) {
            var $panel = $('#eop-performance-audit');

            if (!$panel.length) {
                return;
            }

            e.preventDefault();
            setPerformanceAuditState(!$panel.hasClass('is-open'));
        });

        $(document).on('click', '#eop-performance-audit .eop-performance-audit__header', function (e) {
            if ($(e.target).closest('button, a, input, select, textarea').length) {
                return;
            }

            var $panel = $('#eop-performance-audit');

            if (!$panel.length) {
                return;
            }

            setPerformanceAuditState(!$panel.hasClass('is-open'));
        });
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.eop-pdf-admin.is-preview-open').each(function () {
                setPdfPreviewState($(this), false);
            });
        }
    });
})(jQuery);
