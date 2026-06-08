/* global jQuery, eop_orders_vars */
(function ($) {
    'use strict';

    var items = [];
    var i18n = eop_orders_vars.i18n || {};
    var discountMode = eop_orders_vars.discount_mode || 'both';
    var selectedShippingRate = null;
    var shippingAddress = {};
    var postcodeLookupCache = {};
    var postcodeLookupBusy = false;

    function getAdminRestUrl(path) {
        var base = String(eop_orders_vars.admin_rest_url || '').replace(/\/+$/, '');
        var cleanPath = String(path || '').replace(/^\/+/, '');

        if (!base || !cleanPath || !eop_orders_vars.rest_nonce || typeof window.fetch !== 'function') {
            return '';
        }

        return base + '/' + cleanPath;
    }

    function requestAdminRest(path, options) {
        var settings = options || {};
        var url = getAdminRestUrl(path);
        var headers = {
            'Accept': 'application/json',
            'X-WP-Nonce': String(eop_orders_vars.rest_nonce || '')
        };

        if (!url) {
            return Promise.reject(new Error('rest_unavailable'));
        }

        if (settings.query) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + $.param(settings.query);
        }

        if (settings.body) {
            headers['Content-Type'] = 'application/json';
        }

        return window.fetch(url, {
            method: settings.method || 'GET',
            headers: headers,
            credentials: 'same-origin',
            body: settings.body ? JSON.stringify(settings.body) : undefined
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    throw {
                        status: response.status,
                        data: data,
                        message: data && data.message ? data.message : 'rest_error'
                    };
                }

                return data;
            });
        });
    }

    function getRestErrorMessage(error, fallback) {
        if (error && error.data && error.data.message) {
            return error.data.message;
        }

        if (error && error.message && error.message !== 'rest_unavailable') {
            return error.message;
        }

        return fallback || i18n.error || 'Erro ao processar a requisicao.';
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

    /* ========================================
     *  Utilities
     * ======================================== */

    function formatBRL(value) {
        return 'R$ ' + parseFloat(value || 0).toFixed(2).replace('.', ',');
    }

    function parseDiscountInput(rawValue) {
        var raw = String(rawValue || '').trim().replace(',', '.');
        var hasPercent = raw.indexOf('%') !== -1;
        var numeric = parseFloat(raw.replace(/[^0-9.\-]/g, ''));

        if (isNaN(numeric) || numeric <= 0) {
            return { type: discountMode === 'percent' ? 'percent' : 'fixed', value: 0 };
        }

        return {
            type: discountMode === 'percent' ? 'percent' : (discountMode === 'fixed' ? 'fixed' : (hasPercent ? 'percent' : 'fixed')),
            value: Math.max(0, numeric)
        };
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

    function formatDiscountInput(type, value) {
        var numeric = parseFloat(value) || 0;

        if (numeric <= 0) {
            return '';
        }

        return type === 'percent' ? (String(numeric).replace('.', ',') + '%') : String(numeric).replace('.', ',');
    }

    function sanitizePostcode(value) {
        return (value || '').replace(/\D/g, '').slice(0, 8);
    }

    function formatPostcode(value) {
        var digits = sanitizePostcode(value);
        if (digits.length <= 5) return digits;
        return digits.slice(0, 5) + '-' + digits.slice(5);
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

    /* ========================================
     *  Discount helpers (same as admin.js)
     * ======================================== */

    function calcItemDiscount(item) {
        var lineTotal = item.price * item.quantity;
        var discVal = parseFloat(item.discount_value) || 0;
        if (discVal <= 0) return 0;
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
            $body.append('<div class="eop-items-empty">' + (i18n.no_items || 'Nenhum produto adicionado.') + '</div>');
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
            } else if (discountMode === 'fixed') {
                discSuffix = '<span class="eop-item-discount-suffix">R$</span>';
                discPlaceholder = '0,00';
            } else {
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
                            '<span class="eop-item-card__label">' + $('<span>').text(i18n.label_price || 'Preco').html() + '</span>' +
                            '<span class="eop-item-card__value">' + formatBRL(item.price) + '</span>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + $('<span>').text(i18n.label_quantity || 'Qtd').html() + '</span>' +
                            '<input type="number" class="eop-qty" min="1" value="' + item.quantity + '" inputmode="numeric" />' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + $('<span>').text(i18n.label_discount || 'Desconto').html() + '</span>' +
                            '<div class="eop-item-discount-group">' +
                                '<input type="text" class="eop-item-discount" value="' + $('<span>').text(discVal > 0 ? formatDiscountInput(discType, discVal) : '').html() + '" placeholder="' + $('<span>').text(discPlaceholder).html() + '" inputmode="decimal" />' +
                                discSuffix +
                            '</div>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + $('<span>').text(i18n.label_discounted_unit_price || 'Valor c/ desconto').html() + '</span>' +
                            '<span class="eop-item-card__value eop-item-card__discounted-unit-price">' + formatBRL(discountedUnitPrice) + '</span>' +
                        '</div>' +
                        '<div class="eop-item-card__field">' +
                            '<span class="eop-item-card__label">' + $('<span>').text(i18n.label_subtotal || 'Subtotal').html() + '</span>' +
                            '<span class="eop-item-card__value eop-item-card__subtotal">' + formatBRL(sub) + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $body.append(card);
        });

        recalcTotals();
    }

    /* ========================================
     *  Shipping panel helpers
     * ======================================== */

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
        if (!ui.panel.length || !ui.toggle.length) return;
        ui.panel.attr('hidden', !isOpen);
        ui.toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
        if (ui.icon.length) ui.icon.text(isOpen ? '−' : '+');
    }

    function setShippingSummary(message) {
        var ui = getShippingPanelElements();
        if (ui.summary.length) ui.summary.text(message);
    }

    function setShippingAddressStatus(message, type) {
        var ui = getShippingPanelElements();
        var cssClass = 'eop-shipping-panel__status';
        if (!ui.status.length) return;
        if (type) cssClass += ' is-' + type;
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

    function fillAddressFromPostcode(data) {
        if (data.uf) $('#eop-shipping-state').val(data.uf);
        if (data.localidade) $('#eop-shipping-city').val(data.localidade);
        if (data.logradouro) $('#eop-shipping-address').val(data.logradouro);
        if (data.bairro) $('#eop-shipping-neighborhood').val(data.bairro);
    }

    function lookupPostcode(force) {
        var postcode = sanitizePostcode($('#eop-shipping-postcode').val());
        $('#eop-shipping-postcode').val(formatPostcode(postcode));

        if (postcode.length !== 8) {
            if (force) setShippingAddressStatus(i18n.shipping_postcode_invalid || 'Digite um CEP valido com 8 numeros.', 'error');
            return;
        }
        if (postcodeLookupBusy) return;
        if (postcodeLookupCache[postcode]) {
            fillAddressFromPostcode(postcodeLookupCache[postcode]);
            setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado.', 'success');
            return;
        }

        postcodeLookupBusy = true;
        setShippingAddressStatus(i18n.shipping_postcode_loading || 'Buscando endereco pelo CEP...', 'loading');

        fetch('https://viacep.com.br/ws/' + postcode + '/json/')
            .then(function (r) { if (!r.ok) throw new Error('fail'); return r.json(); })
            .then(function (data) {
                postcodeLookupBusy = false;
                if (!data || data.erro) {
                    setShippingAddressStatus(i18n.shipping_postcode_not_found || 'CEP nao encontrado.', 'error');
                    return;
                }
                postcodeLookupCache[postcode] = data;
                fillAddressFromPostcode(data);
                setShippingAddressStatus(i18n.shipping_postcode_found || 'Endereco encontrado.', 'success');
            })
            .catch(function () {
                postcodeLookupBusy = false;
                setShippingAddressStatus(i18n.shipping_postcode_error || 'Erro ao buscar CEP.', 'error');
            });
    }

    function renderShippingRates(packages) {
        var html = '';
        packages.forEach(function (pkg, pkgIndex) {
            html += '<div class="eop-shipping-group">';
            html += '<div class="eop-shipping-group__title">' + $('<span>').text(pkg.package_name).html() + '</div>';
            pkg.rates.forEach(function (rate, rateIndex) {
                var details = [];
                if (rate.meta_data) {
                    Object.keys(rate.meta_data).forEach(function (key) { details.push(rate.meta_data[key]); });
                }
                html += '<label class="eop-shipping-rate">';
                html += '<input type="radio" name="eop_shipping_rate_' + pkgIndex + '" value="' + $('<span>').text(JSON.stringify(rate)).html() + '" ' + (pkgIndex === 0 && rateIndex === 0 ? 'checked' : '') + '>';
                html += '<span class="eop-shipping-rate__content">';
                html += '<strong>' + $('<span>').text(rate.label).html() + '</strong>';
                html += '<em>' + formatBRL(rate.cost) + '</em>';
                if (details.length) html += '<small>' + $('<span>').text(details.join(' | ')).html() + '</small>';
                html += '</span></label>';
            });
            html += '</div>';
        });
        $('#eop-shipping-rates').html(html);
        setShippingSummary(i18n.shipping_summary_ready || 'Escolha a opcao de frete.');
        $('input[name^="eop_shipping_rate_"]').first().trigger('change');
    }

    /* ========================================
     *  Load order data on edit page
     * ======================================== */

    function loadOrder() {
        var orderId = $('#eop-edit-order-id').val();
        if (!orderId) return;

        function applyLoadedOrder(d) {
            var customer = d.customer || {};

            // Customer
            $('#eop-document').val(customer.document || '');
            $('#eop-name').val(customer.name || '');
            $('#eop-email').val(customer.email || '');
            $('#eop-phone').val(customer.phone || '');
            $('#eop-user-id').val(customer.user_id || 0);

            // Items
            items = (d.items || []).map(function (item) {
                return {
                    product_id: item.product_id,
                    name: item.name,
                    sku: item.sku || '',
                    price: parseFloat(item.price) || 0,
                    image: item.image || '',
                    quantity: parseInt(item.quantity, 10) || 1,
                    discount_type: item.discount_type || 'fixed',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            });
            renderItems();

            // Shipping
            $('#eop-shipping').val(d.shipping || 0);
            if (d.shipping_method) {
                setShippingSummary(d.shipping_method + ' • ' + formatBRL(d.shipping));
            }

            // Shipping address
            var addr = d.shipping_address || {};
            if (addr.postcode) $('#eop-shipping-postcode').val(formatPostcode(addr.postcode));
            if (addr.state) $('#eop-shipping-state').val(addr.state);
            if (addr.city) $('#eop-shipping-city').val(addr.city);
            if (addr.address) $('#eop-shipping-address').val(addr.address);
            if (addr.number) $('#eop-shipping-number').val(addr.number);
            if (addr.neighborhood) $('#eop-shipping-neighborhood').val(addr.neighborhood);
            if (addr.address_2) $('#eop-shipping-address-2').val(addr.address_2);

            // Discount
            $('#eop-discount').val(formatDiscountInput(d.discount_type || 'fixed', d.discount || 0));

            // Status
            $('#eop-status').val(d.status || 'pending');

            recalcTotals();
        }

        function loadOrderViaAjax() {
            $.post(eop_orders_vars.ajax_url, {
                action: 'eop_load_order',
                nonce: eop_orders_vars.nonce,
                order_id: orderId
            }, function (res) {
                if (!res.success) {
                    showNotice(res.data && res.data.message ? res.data.message : 'Erro ao carregar pedido.', 'error');
                    return;
                }

                applyLoadedOrder(res.data || {});
            }).fail(function () {
                showNotice('Erro ao carregar pedido.', 'error');
            });
        }

        requestAdminRest('orders/' + encodeURIComponent(orderId)).then(function (data) {
            var d = data && data.order ? data.order : (data || {});
            applyLoadedOrder(d);
        }).catch(function (error) {
            if (error && error.message === 'rest_unavailable') {
                loadOrderViaAjax();
                return;
            }

            showNotice(getRestErrorMessage(error, 'Erro ao carregar pedido.'), 'error');
        });
    }

    /* ========================================
     *  Event bindings (edit page only)
     * ======================================== */

    if (!$('#eop-edit-order-id').length) {
        return; // Not on edit page
    }

    syncDefaultDiscountFieldUi();

    // Load order on page load
    loadOrder();

    // Customer search
    $('#eop-search-customer').on('click', function () {
        var doc = $('#eop-document').val().replace(/\D/g, '');
        if (!doc) return;

        var $status = $('#eop-customer-status');
        $status.text('Buscando...').attr('class', 'eop-status searching');

        function applyCustomerResult(data) {
            if (data && data.found) {
                $('#eop-user-id').val(data.user_id);
                $('#eop-name').val(data.name);
                $('#eop-email').val(data.email);
                $('#eop-phone').val(data.phone);
                $status.text('Cliente encontrado!').attr('class', 'eop-status found');
                return;
            }

            $('#eop-user-id').val(0);
            $status.text('Nao encontrado. Preencha manualmente.').attr('class', 'eop-status not-found');
        }

        function searchCustomerViaAjax() {
            $.post(eop_orders_vars.ajax_url, {
                action: 'eop_search_customer',
                nonce: eop_orders_vars.nonce,
                document: doc
            }, function (res) {
                if (res.success) {
                    applyCustomerResult(res.data || {});
                    return;
                }

                $status.text('Erro na busca.').attr('class', 'eop-status not-found');
            }).fail(function () {
                $status.text('Erro na busca.').attr('class', 'eop-status not-found');
            });
        }

        requestAdminRest('customers/search', {
            query: { document: doc }
        }).then(function (data) {
            applyCustomerResult(data || {});
        }).catch(function (error) {
            if (error && error.message === 'rest_unavailable') {
                searchCustomerViaAjax();
                return;
            }

            $status.text(getRestErrorMessage(error, 'Erro na busca.')).attr('class', 'eop-status not-found');
        });
    });

    $('#eop-document').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#eop-search-customer').trigger('click');
        }
    });

    // Product search
    $('#eop-product-search').select2({
        placeholder: i18n.search_product,
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return { term: params.term };
            },
            transport: function (params, success, failure) {
                var aborted = false;

                function fallbackToAjax() {
                    if (aborted) {
                        return;
                    }

                    return $.ajax({
                        url: eop_orders_vars.ajax_url,
                        dataType: 'json',
                        data: {
                            action: 'eop_search_products',
                            nonce: eop_orders_vars.nonce,
                            term: params.data ? params.data.term : ''
                        }
                    }).then(function (data) {
                        if (!aborted) {
                            success(data);
                        }
                    }, failure);
                }

                requestAdminRest('products', {
                    query: {
                        term: params.data ? params.data.term : ''
                    }
                }).then(function (data) {
                    if (!aborted) {
                        success(data);
                    }
                }).catch(function () {
                    fallbackToAjax();
                });

                return {
                    abort: function () {
                        aborted = true;
                    }
                };
            },
            processResults: function (data) { return data; },
            cache: true
        }
    });

    $('#eop-product-search').on('select2:select', function (e) {
        var selected = e.params.data;
        var defaultQuantity = getDefaultItemQuantity();
        var defaultDiscount = getDefaultItemDiscount();
        var existing = items.find(function (item) { return item.product_id === selected.id; });

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

    // Qty change
    $(document).on('change input', '.eop-qty', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var val = parseInt($(this).val(), 10);
        if (val < 1) val = 1;
        items[idx].quantity = val;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    // Remove item
    $(document).on('click', '.eop-remove-item', function (e) {
        e.preventDefault();
        var idx = $(this).closest('.eop-item-card').data('idx');
        items.splice(idx, 1);
        renderItems();
    });

    // Item discount
    $(document).on('change input', '.eop-item-discount', function () {
        var $card = $(this).closest('.eop-item-card');
        var idx = $card.data('idx');
        var parsed = parseDiscountInput($card.find('.eop-item-discount').val());
        items[idx].discount_type = parsed.type;
        items[idx].discount_value = parsed.value;
        updateCardSubtotal($card, idx);
        recalcTotals();
    });

    $('#eop-apply-item-defaults').on('click', function () {
        applyItemDefaultsToAll();
    });

    // General discount & shipping
    $('#eop-shipping, #eop-discount').on('input change', recalcTotals);

    // Shipping toggle
    $('#eop-shipping-toggle').on('click', function () {
        var isOpen = $(this).attr('aria-expanded') === 'true';
        setShippingPanelState(!isOpen);
        if (!isOpen && !$('#eop-shipping-postcode').val()) {
            setShippingAddressStatus(i18n.shipping_panel_hint || 'Comece pelo CEP.', 'info');
        }
    });

    // CEP lookup
    $('#eop-shipping-postcode').on('input', function () {
        var formatted = formatPostcode($(this).val());
        var digits = sanitizePostcode(formatted);
        $(this).val(formatted);
        clearShippingSelection(true);
        setShippingAddressStatus('', '');
        if (digits.length === 8) lookupPostcode(false);
    });

    $('#eop-shipping-postcode').on('blur', function () { lookupPostcode(true); });

    // Address change
    $('#eop-shipping-state, #eop-shipping-city, #eop-shipping-address, #eop-shipping-number, #eop-shipping-neighborhood, #eop-shipping-address-2').on('input change', function () {
        if (selectedShippingRate) {
            clearShippingSelection(true);
        }
    });

    // Calc shipping
    $('#eop-calc-shipping').on('click', function () {
        var $btn = $(this);
        shippingAddress = getShippingAddress();

        if (!items.length) { showNotice(i18n.missing_products, 'error'); return; }
        if (!shippingAddress.postcode || !shippingAddress.city || !shippingAddress.address || !shippingAddress.number) {
            showNotice(i18n.shipping_missing, 'error');
            return;
        }

        $btn.prop('disabled', true).text(i18n.shipping_loading);
        setShippingAddressStatus(i18n.shipping_loading || 'Calculando frete...', 'loading');
        clearShippingSelection(false);

        function applyShippingRates(data) {
            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            setShippingAddressStatus(i18n.shipping_rates_found || 'Opcoes encontradas.', 'success');
            renderShippingRates(data.rates || []);
        }

        function calculateShippingViaAjax() {
            $.post(eop_orders_vars.ajax_url, {
                action: 'eop_calculate_shipping',
                nonce: eop_orders_vars.nonce,
                items: JSON.stringify(items.map(function (item) { return { product_id: item.product_id, quantity: item.quantity }; })),
                address: JSON.stringify(shippingAddress)
            }, function (res) {
                if (!res.success) {
                    $btn.prop('disabled', false).text(i18n.shipping_calculate);
                    setShippingAddressStatus('', '');
                    showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                    return;
                }

                applyShippingRates(res.data || {});
            }).fail(function () {
                $btn.prop('disabled', false).text(i18n.shipping_calculate);
                setShippingAddressStatus('', '');
                showNotice(i18n.error, 'error');
            });
        }

        requestAdminRest('shipping/rates', {
            method: 'POST',
            body: {
                items: items.map(function (item) { return { product_id: item.product_id, quantity: item.quantity }; }),
                address: shippingAddress
            }
        }).then(applyShippingRates).catch(function (error) {
            if (error && error.message === 'rest_unavailable') {
                calculateShippingViaAjax();
                return;
            }

            $btn.prop('disabled', false).text(i18n.shipping_calculate);
            setShippingAddressStatus('', '');
            showNotice(getRestErrorMessage(error, i18n.error), 'error');
        });
    });

    // Shipping rate selection
    $(document).on('change', 'input[name^="eop_shipping_rate_"]', function () {
        try {
            selectedShippingRate = JSON.parse($(this).val());
            $('#eop-shipping').val(selectedShippingRate.cost || 0);
            shippingAddress = getShippingAddress();
            setShippingSummary((selectedShippingRate.label || 'Frete') + ' • ' + formatBRL(selectedShippingRate.cost || 0));
            recalcTotals();
        } catch (err) {
            selectedShippingRate = null;
            $('#eop-shipping').val(0);
            recalcTotals();
        }
    });

    // Save order
    $('#eop-save-order').on('click', function () {
        if (!items.length) {
            showNotice(i18n.missing_products, 'error');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(i18n.processing);

        var orderData = {
            order_id: parseInt($('#eop-edit-order-id').val(), 10),
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
                    discount_type: item.discount_type || 'fixed',
                    discount_value: parseFloat(item.discount_value) || 0
                };
            }),
            shipping: parseFloat($('#eop-shipping').val()) || 0,
            shipping_method: selectedShippingRate ? selectedShippingRate.label : '',
            shipping_address: getShippingAddress(),
            discount: parseDiscountInput($('#eop-discount').val()).value,
            discount_type: parseDiscountInput($('#eop-discount').val()).type,
            status: $('#eop-status').val(),
            note: $('#eop-note').val().trim()
        };

        function handleOrderSaved() {
            $btn.prop('disabled', false).text(i18n.save_label);
            showNotice(i18n.saved || 'Pedido atualizado com sucesso!', 'success');
            $('#eop-note').val('');
        }

        function saveOrderViaAjax() {
            $.post(eop_orders_vars.ajax_url, {
                action: 'eop_update_order',
                nonce: eop_orders_vars.nonce,
                order_data: JSON.stringify(orderData)
            }, function (res) {
                if (!res.success) {
                    $btn.prop('disabled', false).text(i18n.save_label);
                    showNotice(res.data && res.data.message ? res.data.message : i18n.error, 'error');
                    return;
                }

                handleOrderSaved(res.data || {});
            }).fail(function () {
                $btn.prop('disabled', false).text(i18n.save_label);
                showNotice(i18n.error, 'error');
            });
        }

        requestAdminRest('orders/' + encodeURIComponent(orderData.order_id), {
            method: 'PUT',
            body: { order: orderData }
        }).then(function (data) {
            handleOrderSaved(data || {});
        }).catch(function (error) {
            if (error && error.message === 'rest_unavailable') {
                saveOrderViaAjax();
                return;
            }

            $btn.prop('disabled', false).text(i18n.save_label);
            showNotice(getRestErrorMessage(error, i18n.error), 'error');
        });
    });

    $(document).on('click', '.eop-accordion__toggle', function () {
        var $toggle = $(this);
        var $body = $toggle.next('.eop-accordion__body');
        var isOpen = $toggle.attr('aria-expanded') === 'true';
        var $icon = $toggle.find('.eop-accordion__icon').first();

        $toggle.attr('aria-expanded', !isOpen ? 'true' : 'false');
        $body.attr('hidden', isOpen);

        if ($icon.length) {
            $icon.text(isOpen ? '+' : '−');
        }
    });

})(jQuery);
