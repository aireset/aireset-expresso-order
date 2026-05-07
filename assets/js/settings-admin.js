/* global jQuery, eop_settings_vars */
(function ($) {
    'use strict';

    var mediaFrame = null;
    var documentPdfFrame = null;
    var mediaUploaderBound = false;
    var colorisConfigured = false;
    var colorSwatches = ['#067bc2', '#84bcda', '#80e377', '#ecc30b', '#f37748', '#d56062'];

    function getContractPlaceholders() {
        var placeholders = getSettingsVar('contract_placeholders', []);

        if (!Array.isArray(placeholders)) {
            return [];
        }

        return placeholders.filter(function (placeholder) {
            return typeof placeholder === 'string' && placeholder.length > 0;
        });
    }

    function getContractPlaceholderGroups() {
        var groups = {
            order: {
                label: getSettingsVar('document_placeholder_group_order', 'Pedido e cobrança'),
                tokens: []
            },
            contract: {
                label: getSettingsVar('document_placeholder_group_contract', 'Contrato e aceite'),
                tokens: []
            },
            shipping: {
                label: getSettingsVar('document_placeholder_group_shipping', 'Entrega'),
                tokens: []
            }
        };

        getContractPlaceholders().forEach(function (token) {
            if (token.indexOf('{shipping_') === 0) {
                groups.shipping.tokens.push(token);
                return;
            }

            if (token.indexOf('{contract_') === 0) {
                groups.contract.tokens.push(token);
                return;
            }

            groups.order.tokens.push(token);
        });

        return Object.keys(groups).map(function (key) {
            return groups[key];
        }).filter(function (group) {
            return group.tokens.length > 0;
        });
    }

    function insertPlaceholderIntoEditor(editor, placeholder) {
        if (!placeholder || !editor || typeof editor.insertContent !== 'function') {
            return;
        }

        editor.focus();
        editor.insertContent(placeholder);
        editor.nodeChanged();
    }

    function buildPlaceholderMenuItems(editor, modern) {
        var groups = getContractPlaceholderGroups();
        var emptyLabel = getSettingsVar('document_placeholder_empty', 'Nenhum placeholder disponível.');

        if (!groups.length) {
            return modern
                ? [{
                    type: 'menuitem',
                    text: emptyLabel,
                    onAction: function () {}
                }]
                : [{
                    text: emptyLabel,
                    disabled: true
                }];
        }

        return groups.map(function (group) {
            if (modern) {
                return {
                    type: 'nestedmenuitem',
                    text: group.label,
                    getSubmenuItems: function () {
                        return group.tokens.map(function (token) {
                            return {
                                type: 'menuitem',
                                text: token,
                                onAction: function () {
                                    insertPlaceholderIntoEditor(editor, token);
                                }
                            };
                        });
                    }
                };
            }

            return {
                text: group.label,
                menu: group.tokens.map(function (token) {
                    return {
                        text: token,
                        onclick: function () {
                            insertPlaceholderIntoEditor(editor, token);
                        }
                    };
                })
            };
        });
    }

    function registerPlaceholderMenu(editor) {
        var buttonId = 'eopplaceholders';
        var label = getSettingsVar('document_placeholder_menu_label', 'Inserir placeholder');

        if (editor.ui && editor.ui.registry && typeof editor.ui.registry.addMenuButton === 'function') {
            editor.ui.registry.addMenuButton(buttonId, {
                text: label,
                fetch: function (callback) {
                    callback(buildPlaceholderMenuItems(editor, true));
                }
            });

            return;
        }

        if (typeof editor.addButton === 'function') {
            editor.addButton(buttonId, {
                type: 'menubutton',
                text: label,
                icon: false,
                menu: buildPlaceholderMenuItems(editor, false)
            });
        }
    }

    function getSettingsVar(key, fallback) {
        if (window.eop_settings_vars && Object.prototype.hasOwnProperty.call(window.eop_settings_vars, key)) {
            return window.eop_settings_vars[key];
        }

        return fallback;
    }

    function createPreviewMarkup(url) {
        return '<img src="' + url + '" alt="" />';
    }

    function createEmptyMarkup() {
        var emptyText = getSettingsVar('no_logo', 'Nenhum logo selecionado ainda.');
        return '<span class="eop-settings-media__empty">' + emptyText + '</span>';
    }

    function getProposalPreviewCard($context) {
        var $scope = $context && $context.length ? $context : $(document);

        if ($scope.hasClass('eop-proposal-preview-card')) {
            return $scope;
        }

        if ($scope.hasClass('eop-settings-form')) {
            return $scope.find('.eop-proposal-preview-card').first();
        }

        return $scope.closest('.eop-proposal-preview-card');
    }

    function getProposalPreviewFrame($context) {
        return getProposalPreviewCard($context).find('.eop-proposal-preview-render').first();
    }

    function getProposalPreviewRoot($context) {
        var $frame = getProposalPreviewFrame($context);

        if (!$frame.length || !$frame[0].contentDocument) {
            return $();
        }

        return $($frame[0].contentDocument).find('[data-eop-proposal-preview-root]').first();
    }

    function setProposalPreviewViewport($card, viewport) {
        var activeViewport = viewport === 'mobile' ? 'mobile' : 'desktop';

        if (!$card.length) {
            return activeViewport;
        }

        $card.attr('data-preview-viewport', activeViewport);
        $card.find('.eop-proposal-preview-viewport').each(function () {
            var $button = $(this);
            var buttonViewport = String($button.attr('data-preview-viewport') || 'desktop');
            var isActive = buttonViewport === activeViewport;

            $button.toggleClass('is-active', isActive);
            $button.attr('aria-pressed', isActive ? 'true' : 'false');
        });

        return activeViewport;
    }

    function setPreviewText($root, selector, value, fallback) {
        var text = $.trim(String(value || ''));
        var $target = $root.find(selector).first();

        if (!$target.length) {
            return;
        }

        $target.text(text || fallback || '');
    }

    function setPreviewLogo($root, url) {
        var hasUrl = Boolean(url);
        var $wrap = $root.find('[data-preview-logo-wrap]').first();

        if ($wrap.length) {
            $wrap.toggleClass('is-empty', !hasUrl);
            $wrap.empty();

            if (hasUrl) {
                $('<img>', {
                    'data-preview-logo': 'true',
                    src: url,
                    alt: ''
                }).appendTo($wrap);
            } else {
                $('<span>', {
                    class: 'eop-proposal-brand__fallback',
                    'data-preview-logo-fallback': 'true',
                    text: 'Logo opcional'
                }).appendTo($wrap);
            }
        }
    }

    function getFieldValue($scope, name, fallback) {
        var $field = $scope.find('[name="eop_settings[' + name + ']"]').first();

        if (!$field.length) {
            return fallback || '';
        }

        return String($field.val() || fallback || '');
    }

    function colorToRgba(color, alpha, fallback) {
        var value = String(color || '').trim();
        var normalized;
        var r;
        var g;
        var b;
        var resolvedAlpha = Math.max(0, Math.min(1, Number(alpha)));

        if (!value || value.charAt(0) !== '#') {
            return fallback || value;
        }

        normalized = value.replace('#', '');

        if (normalized.length === 3) {
            normalized = normalized.split('').map(function (part) {
                return part + part;
            }).join('');
        }

        if (normalized.length !== 6) {
            return fallback || value;
        }

        r = parseInt(normalized.slice(0, 2), 16);
        g = parseInt(normalized.slice(2, 4), 16);
        b = parseInt(normalized.slice(4, 6), 16);

        if (Number.isNaN(r) || Number.isNaN(g) || Number.isNaN(b)) {
            return fallback || value;
        }

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + resolvedAlpha + ')';
    }

    function toPreviewFontFamily(value, fallback) {
        var raw = String(value || '').trim();
        var family;

        if (!raw) {
            return fallback || "'Segoe UI', sans-serif";
        }

        family = raw.indexOf(':') !== -1 ? raw.split(':')[0].trim() : raw;

        if (!family) {
            return fallback || "'Segoe UI', sans-serif";
        }

        if (family.indexOf(',') !== -1 || family.indexOf('"') !== -1 || family.indexOf("'") !== -1) {
            return family;
        }

        return "'" + family.replace(/'/g, "\\'") + "', sans-serif";
    }

    function updateProposalPreview($form) {
        var $scope = $form && $form.length ? $form : $(document);
        var $card = getProposalPreviewCard($scope);
        var $root;
        var pageBg;
        var heroBg;
        var panelBg;
        var sideBg;
        var accent;
        var textColor;
        var mutedColor;
        var borderColor;
        var fontFamily;
        var radius;
        var maxWidth;
        var titleSize;
        var textSize;

        if (!$card.length) {
            return;
        }

        $root = getProposalPreviewRoot($card);

        if (!$root.length) {
            return;
        }

        pageBg = getFieldValue($scope, 'proposal_background_color', '#f5f7ff');
        heroBg = getFieldValue($scope, 'primary_color', '#00034b');
        panelBg = getFieldValue($scope, 'proposal_card_color', '#ffffff');
        sideBg = getFieldValue($scope, 'surface_color', '#f6f8fc');
        accent = getFieldValue($scope, 'primary_color', '#d78a2f');
        textColor = getFieldValue($scope, 'proposal_text_color', '#172033');
        mutedColor = getFieldValue($scope, 'proposal_muted_color', '#5b6474');
        borderColor = getFieldValue($scope, 'border_color', '#dbe3f0');
        fontFamily = toPreviewFontFamily(getFieldValue($scope, 'font_family', 'Montserrat:400,700'), "'Segoe UI', sans-serif");
        radius = parseInt(getFieldValue($scope, 'border_radius', '18'), 10);
        maxWidth = parseInt(getFieldValue($scope, 'proposal_max_width', '1120'), 10);
        titleSize = parseInt(getFieldValue($scope, 'proposal_title_size', '40'), 10);
        textSize = parseInt(getFieldValue($scope, 'proposal_text_size', '16'), 10);

        if (!$root[0].style) {
            return;
        }

        $root[0].style.setProperty('--eop-preview-page-bg', pageBg);
        $root[0].style.setProperty('--eop-preview-hero-bg', heroBg);
        $root[0].style.setProperty('--eop-preview-panel-bg', panelBg);
        $root[0].style.setProperty('--eop-preview-side-bg', sideBg);
        $root[0].style.setProperty('--eop-preview-accent', accent);
        $root[0].style.setProperty('--eop-preview-text', textColor);
        $root[0].style.setProperty('--eop-preview-muted', mutedColor);
        $root[0].style.setProperty('--eop-preview-border-soft', colorToRgba(borderColor, 0.18, borderColor));
        $root[0].style.setProperty('--eop-preview-brand-bg', colorToRgba(panelBg, 0.12, panelBg));
        $root[0].style.setProperty('--eop-preview-panel-soft', colorToRgba(panelBg, 0.18, panelBg));
        $root[0].style.setProperty('--eop-preview-accent-soft', colorToRgba(accent, 0.12, accent));
        $root[0].style.setProperty('--eop-preview-accent-border', colorToRgba(accent, 0.28, accent));
        $root[0].style.setProperty('--eop-preview-accent-glow', colorToRgba(accent, 0.28, accent));
        $root[0].style.setProperty('--eop-preview-accent-shadow', colorToRgba(accent, 0.2, accent));
        $root[0].style.setProperty('--eop-preview-font-family', fontFamily);
        $root[0].style.setProperty('--eop-preview-radius', (isNaN(radius) ? 18 : radius) + 'px');
        $root[0].style.setProperty('--eop-preview-max-width', (isNaN(maxWidth) ? 1120 : maxWidth) + 'px');
        $root[0].style.setProperty('--eop-preview-title-size', (isNaN(titleSize) ? 40 : titleSize) + 'px');
        $root[0].style.setProperty('--eop-preview-text-size', (isNaN(textSize) ? 16 : textSize) + 'px');

        setPreviewLogo($root, getFieldValue($scope, 'brand_logo_url', ''));
        setPreviewText($root, '[data-preview-status]', getSettingsVar('preview_status', 'Preview ao vivo'), 'Preview ao vivo');
        setPreviewText($root, '[data-preview-stage]', getSettingsVar('preview_stage', 'Layout real'), 'Layout real');
        setPreviewText($root, '[data-preview-title]', getFieldValue($scope, 'proposal_title', ''), getSettingsVar('proposal_title_fallback', 'Sua proposta esta pronta'));
        setPreviewText($root, '[data-preview-description]', getFieldValue($scope, 'proposal_description', ''), getSettingsVar('proposal_description_fallback', 'Revise os itens e siga para a conclusao.'));
        setPreviewText($root, '[data-preview-total-label]', getFieldValue($scope, 'customer_experience_total_label', '') || 'Investimento aprovado', 'Investimento aprovado');
        setPreviewText($root, '[data-preview-total-note]', getFieldValue($scope, 'customer_experience_total_note', '') || 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.', 'Assim que a etapa atual for concluida, o pedido segue para o time responsavel.');
        setPreviewText($root, '[data-preview-items-eyebrow]', getFieldValue($scope, 'customer_experience_items_eyebrow', '') || 'Resumo visual', 'Resumo visual');
        setPreviewText($root, '[data-preview-items-title]', getFieldValue($scope, 'customer_experience_items_title', '') || 'Itens', 'Itens');
        setPreviewText($root, '[data-preview-summary-eyebrow]', getFieldValue($scope, 'customer_experience_summary_eyebrow', '') || 'Contexto rapido', 'Contexto rapido');
        setPreviewText($root, '[data-preview-summary-title]', getFieldValue($scope, 'customer_experience_summary_title', '') || 'Visao do pedido', 'Visao do pedido');
        setPreviewText($root, '[data-preview-financial-eyebrow]', getFieldValue($scope, 'customer_experience_financial_eyebrow', '') || 'Fechamento', 'Fechamento');
        setPreviewText($root, '[data-preview-financial-title]', getFieldValue($scope, 'customer_experience_financial_title', '') || 'Resumo', 'Resumo');
        setPreviewText($root, '[data-preview-actions-eyebrow]', getFieldValue($scope, 'customer_experience_actions_eyebrow', '') || 'Proxima acao', 'Proxima acao');
        setPreviewText($root, '[data-preview-actions-title]', getFieldValue($scope, 'customer_experience_actions_title', '') || 'Como seguir agora', 'Como seguir agora');
        setPreviewText($root, '[data-preview-confirm-button]', getFieldValue($scope, 'proposal_button_label', '') || 'Confirmar proposta', 'Confirmar proposta');
        setPreviewText($root, '[data-preview-pay-button]', getFieldValue($scope, 'proposal_pay_button_label', '') || 'Ir para pagamento', 'Ir para pagamento');
    }

    function initProposalPreview(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        $scope.find('.eop-proposal-preview-card').each(function () {
            var $card = $(this);
            var $form = $card.closest('.eop-settings-form');
            var viewport = String($card.attr('data-preview-viewport') || 'desktop');

            setProposalPreviewViewport($card, viewport);

            $card.find('.eop-proposal-preview-render').off('load.eopProposalPreview').on('load.eopProposalPreview', function () {
                updateProposalPreview($form);
            });

            updateProposalPreview($form);
        });
    }

    function setMediaOnWrap($wrap, url) {
        var hasUrl = Boolean(url);
        var $hiddenInput = $wrap.find('input[type="hidden"]').first();
        var $urlInput = $wrap.find('[data-media-url]');
        var $preview = $wrap.find('[data-media-preview]');
        var $selectButton = $wrap.find('[data-media-select]');
        var $removeButton = $wrap.find('[data-media-remove]');

        $hiddenInput.val(url);
        $urlInput.val(url);
        $preview
            .toggleClass('has-image', hasUrl)
            .html(hasUrl ? createPreviewMarkup(url) : createEmptyMarkup());
        $hiddenInput.trigger('input').trigger('change');
        $urlInput.trigger('input').trigger('change');
        $removeButton.toggleClass('is-hidden', !hasUrl);
        $selectButton.text(hasUrl ? getSettingsVar('change_logo', 'Trocar logo') : getSettingsVar('select_logo', 'Selecionar logo'));
    }

    function buildDocumentEditorSettings() {
        return {
            tinymce: {
                wpautop: true,
                browser_spellcheck: true,
                resize: true,
                menubar: 'edit insert view format',
                branding: false,
                elementpath: false,
                statusbar: true,
                wpautoresize_on: true,
                block_formats: 'Parágrafo=p;Título 2=h2;Título 3=h3;Título 4=h4;Citação=blockquote;Pré-formatado=pre',
                toolbar1: 'formatselect,styleselect,|,bold,italic,underline,strikethrough,|,forecolor,backcolor,|,alignleft,aligncenter,alignright,alignjustify,|,link,unlink,|,eopplaceholders',
                toolbar2: 'bullist,numlist,outdent,indent,blockquote,hr,|,pastetext,removeformat,charmap,|,undo,redo,fullscreen',
                height: 360,
                setup: function (editor) {
                    registerPlaceholderMenu(editor);
                }
            },
            quicktags: {
                buttons: 'strong,em,link,block,ul,ol,li,code,close'
            },
            mediaButtons: false
        };
    }

    function initSignatureEditors(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        if (typeof window.wp === 'undefined' || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
            return;
        }

        $scope.find('textarea[data-signature-document-editor]').each(function () {
            var textarea = this;
            var $textarea = $(textarea);
            var $document = $textarea.closest('[data-signature-document]');
            var id = String(textarea.id || '');
            var source = $document.length ? String($document.find('[data-signature-document-source]').val() || 'editor') : 'editor';

            if (!id || $textarea.data('editorInitialized') || source !== 'editor') {
                return;
            }

            window.wp.editor.initialize(id, buildDocumentEditorSettings());
            $textarea.data('editorInitialized', true);
        });
    }

    function destroySignatureEditor($textarea) {
        var id = String($textarea.attr('id') || '');

        if (!id || typeof window.wp === 'undefined' || !window.wp.editor || typeof window.wp.editor.remove !== 'function') {
            return;
        }

        window.wp.editor.remove(id);
    }

    function updateSignatureDocumentPanels($document) {
        var source = String($document.find('[data-signature-document-source]').val() || 'editor');

        $document.find('[data-signature-document-panel="editor"]').toggleClass('is-hidden', source !== 'editor');
        $document.find('[data-signature-document-panel="attachment"]').toggleClass('is-hidden', source !== 'attachment');
    }

    function refreshSignatureDocumentsEmptyState($root) {
        var $list = $root.find('[data-signature-documents-list]').first();
        var $empty = $root.find('[data-signature-documents-empty]').first();

        if (!$empty.length) {
            return;
        }

        $empty.toggleClass('is-hidden', $list.find('[data-signature-document]').length > 0);
    }

    function refreshSignatureDocumentSummary($document) {
        var title = $.trim(String($document.find('[data-signature-document-title]').first().val() || ''));
        var sourceLabel = $.trim(String($document.find('[data-signature-document-source] option:selected').text() || ''));

        $document.find('[data-signature-document-heading]').first().text(title || getSettingsVar('document_default_title', 'Novo documento'));
        $document.find('[data-signature-document-source-label]').first().text(sourceLabel || getSettingsVar('document_source_editor', 'Conteudo do documento'));
    }

    function setSignatureDocumentExpanded($document, expanded, collapseOthers) {
        var shouldExpand = Boolean(expanded);
        var $toggle = $document.find('[data-signature-document-toggle]').first();
        var $body = $document.find('[data-signature-document-body]').first();

        if (shouldExpand && collapseOthers !== false) {
            $document.closest('[data-signature-documents]').find('[data-signature-document]').not($document).each(function () {
                setSignatureDocumentExpanded($(this), false, false);
            });
        }

        $document.attr('data-expanded', shouldExpand ? 'true' : 'false');
        $document.toggleClass('is-expanded', shouldExpand);
        $document.toggleClass('is-collapsed', !shouldExpand);
        $body.toggleClass('is-hidden', !shouldExpand);
        $toggle.attr('aria-expanded', shouldExpand ? 'true' : 'false');
        $toggle.text(shouldExpand ? getSettingsVar('document_close', 'Fechar') : getSettingsVar('document_edit', 'Editar'));

        if (shouldExpand) {
            initSignatureEditors($document);
            updateSignatureDocumentPanels($document);
        }
    }

    function bindMediaUploader() {
        if (mediaUploaderBound) {
            return;
        }

        function bindSignatureDocumentMedia() {
            $(document).on('click', '[data-signature-document-attachment-select]', function (event) {
                var $button = $(this);
                var $document = $button.closest('[data-signature-document]');
                var $hidden = $document.find('[data-signature-document-attachment-id]').first();
                var $name = $document.find('[data-signature-document-attachment-name]').first();
                var $remove = $document.find('[data-signature-document-attachment-remove]').first();

                event.preventDefault();

                if (typeof wp === 'undefined' || !wp.media) {
                    return;
                }

                documentPdfFrame = wp.media({
                    title: getSettingsVar('document_media_title', 'Selecionar arquivo do documento'),
                    button: {
                        text: getSettingsVar('document_media_button', 'Usar este arquivo')
                    },
                    multiple: false,
                    library: {
                        type: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                    }
                });

                documentPdfFrame.on('select', function () {
                    var selection = documentPdfFrame.state().get('selection').first();
                    var attachment = selection ? selection.toJSON() : null;
                    var hasAttachment = Boolean(attachment && attachment.id);

                    $hidden.val(hasAttachment ? attachment.id : '');
                    $name.text(hasAttachment && attachment.filename ? attachment.filename : getSettingsVar('document_pdf_empty', 'Nenhum arquivo anexado ainda.'));
                    $button.text(hasAttachment ? getSettingsVar('document_change_pdf', 'Trocar arquivo') : getSettingsVar('document_select_pdf', 'Selecionar arquivo'));
                    $remove.toggleClass('is-hidden', !hasAttachment);
                });

                documentPdfFrame.open();
            });

            $(document).on('click', '[data-signature-document-attachment-remove]', function (event) {
                var $button = $(this);
                var $document = $button.closest('[data-signature-document]');

                event.preventDefault();

                $document.find('[data-signature-document-attachment-id]').val('');
                $document.find('[data-signature-document-attachment-name]').text(getSettingsVar('document_pdf_empty', 'Nenhum arquivo anexado ainda.'));
                $document.find('[data-signature-document-attachment-select]').text(getSettingsVar('document_select_pdf', 'Selecionar arquivo'));
                $button.addClass('is-hidden');
            });
        }

        function bindSignatureDocuments() {
            $(document).on('click', '[data-signature-document-add]', function (event) {
                var $root = $(this).closest('[data-signature-documents]');
                var $list = $root.find('[data-signature-documents-list]').first();
                var $template = $('#eop-signature-document-template');
                var nextIndex = parseInt($root.attr('data-next-index') || '0', 10);
                var markup;
                var $document;

                event.preventDefault();

                if (!$template.length) {
                    return;
                }

                markup = String($template.html() || '').replace(/__INDEX__/g, String(nextIndex));
                $document = $(markup);
                $list.append($document);
                $root.attr('data-next-index', String(nextIndex + 1));
                refreshSignatureDocumentSummary($document);
                updateSignatureDocumentPanels($document);
                setSignatureDocumentExpanded($document, true);
                refreshSignatureDocumentsEmptyState($root);
            });

            $(document).on('click', '[data-signature-document-remove]', function (event) {
                var $document = $(this).closest('[data-signature-document]');
                var $root = $document.closest('[data-signature-documents]');

                event.preventDefault();
                $document.find('textarea[data-signature-document-editor]').each(function () {
                    destroySignatureEditor($(this));
                });
                $document.remove();
                refreshSignatureDocumentsEmptyState($root);
            });

            $(document).on('change', '[data-signature-document-source]', function () {
                var $document = $(this).closest('[data-signature-document]');

                refreshSignatureDocumentSummary($document);
                updateSignatureDocumentPanels($document);

                if ($document.attr('data-expanded') === 'true') {
                    initSignatureEditors($document);
                }
            });

            $(document).on('input', '[data-signature-document-title]', function () {
                refreshSignatureDocumentSummary($(this).closest('[data-signature-document]'));
            });

            $(document).on('click', '[data-signature-document-toggle]', function (event) {
                var $document = $(this).closest('[data-signature-document]');

                event.preventDefault();
                setSignatureDocumentExpanded($document, $document.attr('data-expanded') !== 'true');
            });
        }

        mediaUploaderBound = true;

        $(document).on('click', '[data-media-select]', function (event) {
            var $button = $(this);
            var $wrap = $button.closest('.eop-settings-media');

            event.preventDefault();

            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }

            mediaFrame = wp.media({
                title: getSettingsVar('media_title', 'Selecionar logo'),
                button: {
                    text: getSettingsVar('media_button', 'Usar esta imagem')
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaFrame.on('select', function () {
                var selection = mediaFrame.state().get('selection').first();
                var attachment = selection ? selection.toJSON() : null;
                var url = attachment && attachment.url ? attachment.url : '';

                setMediaOnWrap($wrap, url);
            });

            mediaFrame.open();
        });

        $(document).on('click', '[data-media-remove]', function (event) {
            event.preventDefault();
            setMediaOnWrap($(this).closest('.eop-settings-media'), '');
        });

        bindSignatureDocumentMedia();
        bindSignatureDocuments();
    }

    function initSettingsAjaxSelectors(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        if (!$.fn.select2) {
            return;
        }

        $scope.find('.eop-settings-product-selector, .eop-settings-category-selector').each(function () {
            var $select = $(this);
            var targetSelector = String($select.data('target-input') || '');
            var $target = targetSelector ? $(targetSelector) : $();
            var searchAction = String($select.data('search-action') || 'eop_search_products');
            var placeholder = String($select.data('placeholder') || getSettingsVar('locked_placeholder', 'Busque produtos por nome ou SKU...'));
            var noResults = String($select.data('no-results') || getSettingsVar('locked_no_results', 'Nenhum produto encontrado.'));
            var minimumInputLength = parseInt($select.data('minimum-input-length'), 10);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            if (isNaN(minimumInputLength)) {
                minimumInputLength = 3;
            }

            $select.select2({
                width: '100%',
                multiple: true,
                allowClear: true,
                placeholder: placeholder,
                minimumInputLength: minimumInputLength,
                language: {
                    noResults: function () {
                        return noResults;
                    }
                },
                ajax: {
                    url: getSettingsVar('ajax_url', ''),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: searchAction,
                            nonce: getSettingsVar('nonce', ''),
                            term: params.term || ''
                        };
                    },
                    processResults: function (data) {
                        return data && data.results ? data : { results: [] };
                    },
                    cache: true
                }
            });

            if ($target.length) {
                $select.on('change', function () {
                    var value = ($select.val() || []).filter(Boolean).join(',');
                    $target.val(value);
                });
            }
        });
    }

    function hideExternalNotices() {
        var selectors = [
            '#wpbody-content > .notice',
            '#wpbody-content > .update-nag',
            '#wpbody-content > .updated',
            '#wpbody-content > .error',
            '.eop-admin-spa .notice',
            '.eop-admin-spa .update-nag',
            '.eop-admin-spa .updated',
            '.eop-admin-spa .error',
            '.eop-admin-spa .fs-notice'
        ].join(', ');

        $(selectors).each(function () {
            if (!$(this).closest('#eop-notices, .eop-settings-page, .el-license-container').length) {
                $(this).hide();
            }
        });
    }

    function injectPdfHelpTooltips($scope) {
        var helpMap = (window.eop_settings_vars && eop_settings_vars.pdf_help_map) || {};
        var statusLabels = (window.eop_settings_vars && eop_settings_vars.help_statuses) || {};
        var buttonLabel = (window.eop_settings_vars && eop_settings_vars.help_label) || 'Ajuda da configuracao';
        var $root = $scope && $scope.length ? $scope : $(document);

        $.each(helpMap, function (fieldId, config) {
            var $label = $root.find('label[for="' + fieldId + '"]').first();
            var $tooltip;
            var $button;
            var $bubble;
            var statusText = config && config.status && statusLabels[config.status] ? statusLabels[config.status] : '';

            if (!$label.length || $label.find('.eop-help-tip').length) {
                return;
            }

            $tooltip = $('<span>', {
                class: 'eop-help-tip'
            });

            $button = $('<button>', {
                type: 'button',
                class: 'eop-help-tip__button',
                'aria-label': buttonLabel,
                text: '?'
            });

            $bubble = $('<span>', {
                class: 'eop-help-tip__bubble',
                role: 'tooltip'
            });

            if (config && config.label) {
                $('<strong>', {
                    class: 'eop-help-tip__title',
                    text: config.label
                }).appendTo($bubble);
            }

            if (statusText) {
                $('<span>', {
                    class: 'eop-help-tip__status eop-help-tip__status--' + String(config.status || ''),
                    text: statusText
                }).appendTo($bubble);
            }

            if (config && config.help) {
                $('<span>', {
                    class: 'eop-help-tip__text',
                    text: config.help
                }).appendTo($bubble);
            }

            if (config && config.effect) {
                $('<span>', {
                    class: 'eop-help-tip__effect',
                    text: config.effect
                }).appendTo($bubble);
            }

            $tooltip.append($button, $bubble);
            $label.append($tooltip);
        });
    }

    function injectSettingsHelpTooltips($scope) {
        var helpMap = (window.eop_settings_vars && eop_settings_vars.confirmation_general_help_map) || {};
        var buttonLabel = (window.eop_settings_vars && eop_settings_vars.help_label) || 'Ajuda da configuracao';
        var $root = $scope && $scope.length ? $scope : $(document);

        $root.find('[data-eop-help-key]').each(function () {
            var $label = $(this);
            var helpKey = String($label.data('eop-help-key') || '');
            var config = helpMap[helpKey] || null;
            var $tooltip;
            var $button;
            var $bubble;

            if (!config || $label.find('.eop-help-tip').length) {
                return;
            }

            $tooltip = $('<span>', {
                class: 'eop-help-tip'
            });

            $button = $('<button>', {
                type: 'button',
                class: 'eop-help-tip__button',
                'aria-label': buttonLabel,
                text: '?'
            });

            $bubble = $('<span>', {
                class: 'eop-help-tip__bubble',
                role: 'tooltip'
            });

            if (config.label) {
                $('<strong>', {
                    class: 'eop-help-tip__title',
                    text: config.label
                }).appendTo($bubble);
            }

            if (config.help) {
                $('<span>', {
                    class: 'eop-help-tip__text',
                    text: config.help
                }).appendTo($bubble);
            }

            if (config.effect) {
                $('<span>', {
                    class: 'eop-help-tip__effect',
                    text: config.effect
                }).appendTo($bubble);
            }

            $tooltip.append($button, $bubble);
            $label.append($tooltip);
        });
    }

    function initColorFields($scope) {
        var clearLabel = (window.eop_settings_vars && eop_settings_vars.color_clear) || 'Limpar';
        var closeLabel = (window.eop_settings_vars && eop_settings_vars.color_close) || 'Fechar';
        var $root = $scope && $scope.length ? $scope : $(document);
        var $fields = $root.find('.eop-color-field');

        if (!$fields.length) {
            return;
        }

        if (typeof window.Coloris !== 'undefined') {
            if (!colorisConfigured) {
                window.Coloris({
                    el: '.eop-color-field'
                });

                window.Coloris.setInstance('.eop-color-field', {
                    theme: 'pill',
                    themeMode: 'dark',
                    formatToggle: true,
                    closeButton: true,
                    closeLabel: closeLabel,
                    clearButton: true,
                    clearLabel: clearLabel,
                    swatchesOnly: false,
                    swatches: colorSwatches
                });

                colorisConfigured = true;
            }

            if (typeof window.Coloris.wrap === 'function') {
                window.Coloris.wrap('.eop-color-field');
            }

            $fields.each(function () {
                var $input = $(this);
                var $wrapper = $input.parent('.clr-field');

                if ($wrapper.length) {
                    $wrapper.css('color', $input.val() || 'transparent');
                }
            });

            return;
        }

        if ($.fn.wpColorPicker) {
            $fields.each(function () {
                var $input = $(this);

                if ($input.hasClass('wp-color-picker')) {
                    return;
                }

                $input.wpColorPicker();
            });
        }
    }

    function setColorFieldValue($input, value) {
        var nextValue = String(value || '');

        $input.val(nextValue).trigger('input').trigger('change');

        if ($input.parent('.clr-field').length) {
            $input.parent('.clr-field').css('color', nextValue || 'transparent');
        }
    }

    function isBinaryToggleSelect($select) {
        var values = [];

        if (!$select.length || $select.prop('multiple') || $select.data('eopBinarySwitch')) {
            return false;
        }

        $select.find('option').each(function () {
            values.push(String($(this).attr('value') || '').toLowerCase());
        });

        if (values.length !== 2) {
            return false;
        }

        values.sort();

        return values[0] === 'no' && values[1] === 'yes';
    }

    function syncBinarySelectSwitch($button, $select, $status) {
        var isEnabled = String($select.val() || 'no') === 'yes';

        $button.toggleClass('is-enabled', isEnabled);
        $button.attr('aria-checked', isEnabled ? 'true' : 'false');
        $status.text(isEnabled ? 'Ativado' : 'Desativado');
    }

    function enhanceBinarySelect($select) {
        var fieldLabel;
        var $field;
        var $shell;
        var $button;
        var $status;

        if (!isBinaryToggleSelect($select)) {
            return;
        }

        $field = $select.closest('.eop-settings-field');
        fieldLabel = $.trim($field.find('label, > span').first().text()) || 'Alternar configuracao';

        $shell = $('<div class="eop-settings-switch-shell eop-settings-switch-shell--select"></div>');
        $button = $(
            '<button type="button" class="eop-settings-switcher" role="switch" aria-checked="false">' +
                '<span class="eop-settings-switcher__label eop-settings-switcher__label--off">Off</span>' +
                '<span class="eop-settings-switcher__thumb" aria-hidden="true"></span>' +
                '<span class="eop-settings-switcher__label eop-settings-switcher__label--on">On</span>' +
            '</button>'
        );
        $status = $('<span class="eop-settings-switcher__status" aria-live="polite"></span>');

        $button.attr('aria-label', fieldLabel);
        $shell.append($button, $status);
        $select.after($shell);
        $select.addClass('eop-settings-binary-source').attr('aria-hidden', 'true').data('eopBinarySwitch', true);

        syncBinarySelectSwitch($button, $select, $status);

        $button.on('click', function () {
            var nextValue = String($select.val() || 'no') === 'yes' ? 'no' : 'yes';

            $select.val(nextValue).trigger('change');
        });

        $select.on('change', function () {
            syncBinarySelectSwitch($button, $select, $status);
        });
    }

    function initBinarySwitches(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        $scope.find('select').each(function () {
            enhanceBinarySelect($(this));
        });
    }

    function mountColorDefaultButtons($scope) {
        var defaultLabel = (window.eop_settings_vars && eop_settings_vars.color_default) || 'Padrao';
        var $root = $scope && $scope.length ? $scope : $(document);

        $root.find('.eop-color-field').each(function () {
            var $input = $(this);
            var defaultColor = String($input.data('default-color') || '');
            var $pickerShell = $input.parent('.clr-field').length ? $input.parent('.clr-field') : $input;
            var $control;

            if (!defaultColor) {
                return;
            }

            if (!$pickerShell.parent().hasClass('eop-color-control')) {
                $pickerShell.wrap('<div class="eop-color-control"></div>');
            }

            $control = $pickerShell.parent();

            if (!$control.find('.eop-color-default').length) {
                $('<button>', {
                    type: 'button',
                    class: 'button button-secondary eop-color-default',
                    text: defaultLabel,
                    'data-default-color': defaultColor,
                    'aria-label': defaultLabel
                }).appendTo($control);
            }
        });
    }

    function initSettingsUi(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        hideExternalNotices();
        injectPdfHelpTooltips($scope);
		injectSettingsHelpTooltips($scope);
        initColorFields($scope);
        mountColorDefaultButtons($scope);
        initSettingsAjaxSelectors($scope);
        initProposalPreview($scope);
        initBinarySwitches($scope);
        $scope.find('[data-signature-document]').each(function () {
            var $document = $(this);

			refreshSignatureDocumentSummary($document);
			updateSignatureDocumentPanels($document);
			setSignatureDocumentExpanded($document, $document.attr('data-expanded') === 'true', false);
        });
        $scope.find('[data-signature-documents]').each(function () {
            refreshSignatureDocumentsEmptyState($(this));
        });
    }

    $(function () {
        initSettingsUi($(document));
        window.setTimeout(hideExternalNotices, 120);

        $(document).on('click', '.eop-color-default', function (event) {
            var $button = $(this);
            var $input = $button.closest('.eop-color-control').find('.eop-color-field').first();
            var defaultColor = String($button.data('default-color') || '');

            event.preventDefault();

            if (!$input.length || !defaultColor) {
                return;
            }

            setColorFieldValue($input, defaultColor);
        });

        $(document).on('click', '.eop-settings-switcher[data-target-name]', function () {
            var $button = $(this);
            var targetName = $button.data('target-name');
            var enabledValue = String($button.data('enabled-value') || 'yes');
            var disabledValue = String($button.data('disabled-value') || 'no');
            var $input = $('input[type="hidden"][name="' + targetName + '"]');
            var isEnabled = $button.hasClass('is-enabled');
            var nextEnabled = !isEnabled;

            if (!$input.length) {
                return;
            }

            $button.toggleClass('is-enabled', nextEnabled);
            $button.attr('aria-checked', nextEnabled ? 'true' : 'false');
            $input.val(nextEnabled ? enabledValue : disabledValue).trigger('input').trigger('change');
            $button
                .siblings('.eop-settings-switcher__status')
                .text(nextEnabled ? 'Ativado' : 'Desativado');
        });

        $(document).on('input change', '.eop-settings-form input, .eop-settings-form textarea, .eop-settings-form select', function () {
            var $field = $(this);
            var $form = $field.closest('.eop-settings-form');

            if (!$form.length) {
                return;
            }

            updateProposalPreview($form);
        });

        $(document).on('click', '.eop-proposal-preview-viewport', function (event) {
            var $button = $(this);
            var $card = $button.closest('.eop-proposal-preview-card');
            var viewport = String($button.attr('data-preview-viewport') || 'desktop');
            var $form;

            event.preventDefault();

            viewport = setProposalPreviewViewport($card, viewport);
            $form = $card.closest('.eop-settings-form');
            updateProposalPreview($form);
        });

        if (eop_settings_vars && eop_settings_vars.has_fontselect && $.fn.fontselect) {
            $('.select_font').fontselect({
                placeholder: eop_settings_vars.font_placeholder || 'Escolha uma fonte Google'
            });
        }

        bindMediaUploader();

        $(document).on('eop:settings-ui:init', function (event, scope) {
            initSettingsUi(scope || document);
        });
    });
})(jQuery);
