(function($) {
    'use strict';

    var activeContext = null;

    function ensureModal() {
        var $modal = $('#shynh-seo-ai-sync-modal');
        if ($modal.length) {
            return $modal;
        }

        $('body').append(
            '<div id="shynh-seo-ai-sync-modal" class="shynh-seo-ai-sync-modal" aria-hidden="true">' +
                '<div class="shynh-seo-ai-sync-modal__panel" role="dialog" aria-modal="true">' +
                    '<div class="shynh-seo-ai-sync-modal__header">' +
                        '<div><strong>AI English SEO Sync Wrap-up</strong><p></p></div>' +
                        '<button type="button" class="button-link shynh-seo-ai-sync-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<div class="shynh-seo-ai-sync-modal__body"></div>' +
                    '<div class="shynh-seo-ai-sync-modal__footer">' +
                        '<a class="button shynh-seo-ai-sync-edit-link" href="#" target="_blank" rel="noopener" style="display:none;">' + escapeHtml(shynhSeoAiSync.i18n.openDraft || 'Open English draft') + '</a>' +
                        '<button type="button" class="button shynh-seo-ai-sync-close">' + escapeHtml(shynhSeoAiSync.i18n.close || 'Close') + '</button>' +
                        '<button type="button" class="button button-primary shynh-seo-ai-sync-create">' + escapeHtml(shynhSeoAiSync.i18n.createDraft || 'Create English Draft') + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        return $('#shynh-seo-ai-sync-modal');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeGenerated(data) {
        var generated = data.generated || {};
        var source = data.source || {};
        var seo = source.shynhSeo || {};

        if (!generated.title) {
            generated.title = source.title || source.name || '';
        }
        if (!generated.slug) {
            generated.slug = source.slug || '';
        }
        if (!generated.h1) {
            generated.h1 = generated.title;
        }
        if (!generated.seo_title && data.yoastEnabled) {
            generated.seo_title = seo.seo_title || generated.title;
        }
        if (!generated.meta_description && data.yoastEnabled) {
            generated.meta_description = seo.meta_desc || '';
        }
        if (!generated.focus_keyword && data.yoastEnabled) {
            generated.focus_keyword = seo.focus_keyword || '';
        }
        if (!generated.content) {
            generated.content = '';
        }
        if (!Array.isArray(generated.faqs)) {
            generated.faqs = [];
        }
        if (!Array.isArray(generated.image_suggestions)) {
            generated.image_suggestions = [];
        }
        if (!generated.image_suggestions.length && Array.isArray(source.images)) {
            generated.image_suggestions = source.images.map(function(image) {
                return {
                    source_id: image.id || 0,
                    source: image.src || '',
                    alt: image.alt || '',
                    title: image.title || '',
                    caption: image.caption || '',
                    reason: 'Original image preserved from source content.',
                    apply: false
                };
            });
        }
        if (!Array.isArray(generated.additional_image_suggestions)) {
            generated.additional_image_suggestions = [];
        }
        if (!Array.isArray(generated.terms)) {
            generated.terms = [];
        }

        return generated;
    }

    function field(label, key, value, type, help) {
        var control = type === 'textarea'
            ? '<textarea data-key="' + escapeHtml(key) + '" rows="4">' + escapeHtml(value || '') + '</textarea>'
            : '<input type="text" data-key="' + escapeHtml(key) + '" value="' + escapeHtml(value || '') + '" />';

        return '<label class="shynh-seo-ai-sync-field"><span>' + escapeHtml(label) + '</span>' + control + (help ? '<em>' + escapeHtml(help) + '</em>' : '') + '</label>';
    }

    function renderList(items, emptyText, formatter) {
        if (!items || !items.length) {
            return '<p class="description">' + escapeHtml(emptyText) + '</p>';
        }
        return '<ul>' + items.map(formatter).join('') + '</ul>';
    }

    function renderImageSuggestions(items) {
        if (!items || !items.length) {
            return '<p class="description">No images detected.</p>';
        }

        return '<div class="shynh-seo-ai-sync-image-list">' + items.map(function(image, index) {
            return '<div class="shynh-seo-ai-sync-image-row" data-image-index="' + index + '">' +
                '<label class="shynh-seo-ai-sync-image-apply"><input type="checkbox" data-image-key="apply" ' + (image.apply ? 'checked' : '') + '> Apply metadata</label>' +
                '<div class="shynh-seo-ai-sync-image-meta">' +
                    '<strong>' + escapeHtml(image.title || image.alt || image.source || ('Image ' + (index + 1))) + '</strong>' +
                    (image.source ? '<a href="' + escapeHtml(image.source) + '" target="_blank" rel="noopener">Open</a>' : '') +
                '</div>' +
                '<input type="hidden" data-image-key="source_id" value="' + escapeHtml(image.source_id || image.id || '') + '">' +
                '<input type="hidden" data-image-key="source" value="' + escapeHtml(image.source || '') + '">' +
                '<label class="shynh-seo-ai-sync-field"><span>Alt</span><input type="text" data-image-key="alt" value="' + escapeHtml(image.alt || '') + '"></label>' +
                '<label class="shynh-seo-ai-sync-field"><span>Title</span><input type="text" data-image-key="title" value="' + escapeHtml(image.title || '') + '"></label>' +
                '<label class="shynh-seo-ai-sync-field"><span>Caption</span><textarea rows="2" data-image-key="caption">' + escapeHtml(image.caption || '') + '</textarea></label>' +
                (image.reason ? '<p class="description">' + escapeHtml(image.reason) + '</p>' : '') +
            '</div>';
        }).join('') + '</div>';
    }

    function renderAdditionalImages(items) {
        if (!items || !items.length) {
            return '<p class="description">No additional image opportunities suggested.</p>';
        }

        return '<ul>' + items.map(function(item) {
            return '<li><strong>' + escapeHtml(item.section_heading || item.section || 'Suggested image') + '</strong><br>' +
                '<span>' + escapeHtml(item.need || item.reason || '') + '</span>' +
                (item.preferred_source ? '<br><code>' + escapeHtml(item.preferred_source) + '</code>' : '') +
            '</li>';
        }).join('') + '</ul>';
    }

    function renderModal(data) {
        var $modal = ensureModal();
        var generated = normalizeGenerated(data);
        var source = data.source || {};
        var yoastHtml = data.yoastEnabled
            ? field('English Focus Keyword', 'focus_keyword', generated.focus_keyword) +
              field('SEO Title', 'seo_title', generated.seo_title) +
              field('Meta Description', 'meta_description', generated.meta_description, 'textarea')
            : '<div class="shynh-seo-ai-sync-note">Yoast SEO output is OFF. Plugin will not generate or write Yoast keyword/title/meta data.</div>';

        var termsHtml = renderList(source.terms || [], 'No assigned supported terms found.', function(term) {
            return '<li><strong>' + escapeHtml(term.name) + '</strong> <code>' + escapeHtml(term.taxonomy) + '</code>' +
                (term.english_id ? ' <span>English #' + escapeHtml(term.english_id) + '</span>' : ' <span>Needs English term</span>') + '</li>';
        });

        var headingHtml = renderList(source.headings || [], 'No headings detected.', function(heading) {
            return '<li><code>H' + escapeHtml(heading.level) + '</code> ' + escapeHtml(heading.text) + '</li>';
        });

        var imageHtml = renderImageSuggestions(generated.image_suggestions || []);
        var additionalImagesHtml = renderAdditionalImages(generated.additional_image_suggestions || []);
        var imagePolicy = source.imagePolicy || {};

        activeContext = {
            source: source,
            generated: generated,
            yoastEnabled: !!data.yoastEnabled,
            model: data.model || {}
        };

        var modelNote = data.model && data.model.model
            ? ' Model: ' + data.model.model + ' (' + (data.model.resolvedMode || data.model.mode || 'auto') + ').'
            : '';
        var reviewNotice = (shynhSeoAiSync.i18n && shynhSeoAiSync.i18n.reviewNotice) ? shynhSeoAiSync.i18n.reviewNotice : 'Vui lòng rà soát nội dung bên dưới. Nhấn "Duyệt & Tạo bài nháp Tiếng Anh" ở góc dưới để hoàn tất.';
        
        var warningsHtml = '';
        if (data.generated && data.generated.warnings && data.generated.warnings.length) {
            warningsHtml = '<div style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; font-size: 13px; line-height: 1.5;">' +
                '<strong>⚠️ AI Block Validator Cảnh Báo:</strong><ul style="margin-top: 5px; margin-bottom: 0;">' +
                data.generated.warnings.map(function(w) { return '<li>' + escapeHtml(w) + '</li>'; }).join('') +
                '</ul></div>';
        }
        var droppedHtml = '';
        if (data.generated && data.generated.dropped && data.generated.dropped.length) {
            droppedHtml = '<div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px 16px; margin-bottom: 16px; border-radius: 6px; font-size: 13px; line-height: 1.5;">' +
                '<strong>❌ Đã Loại Bỏ Nội Dung Sai Cấu Trúc:</strong><ul style="margin-top: 5px; margin-bottom: 0;">' +
                data.generated.dropped.map(function(d) { return '<li><code>' + escapeHtml(d.block) + '</code>: ' + escapeHtml(d.reason) + '</li>'; }).join('') +
                '</ul></div>';
        }

        $modal.find('.shynh-seo-ai-sync-modal__header p').text((data.message || '') + modelNote);
        $modal.find('.shynh-seo-ai-sync-modal__body').html(
            '<div class="shynh-seo-ai-sync-notice" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:12px 16px;margin-bottom:16px;color:#1e40af;font-size:13px;line-height:1.5;">' +
                '<strong>Quy trình kiểm duyệt (Human-in-the-loop):</strong> ' + escapeHtml(reviewNotice) +
            '</div>' +
            warningsHtml +
            droppedHtml +
            '<div class="shynh-seo-ai-sync-grid">' +
                '<section><h3>English Draft Review</h3>' +
                    field('English Title', 'title', generated.title) +
                    field('Slug', 'slug', generated.slug) +
                    field('H1', 'h1', generated.h1) +
                    field('Excerpt', 'excerpt', generated.excerpt, 'textarea') +
                    field('Quick Answer', 'quick_answer', generated.quick_answer, 'textarea', 'Recommended near the intro for AEO.') +
                    field('Content HTML', 'content', generated.content, 'textarea') +
                '</section>' +
                '<section><h3>SEO Fields</h3>' + yoastHtml + '</section>' +
                '<section><h3>Source Structure</h3>' + headingHtml + '</section>' +
                '<section><h3>Terms / Categories</h3>' + termsHtml + '</section>' +
                '<section><h3>Images</h3>' +
                    '<div class="shynh-seo-ai-sync-note">Original content images are ' + (imagePolicy.preserve_original_images ? 'preserved' : 'not forced') + '. Tick metadata only when you want to write alt/title/caption to Media Library.</div>' +
                    imageHtml +
                '</section>' +
                '<section><h3>Additional Images</h3>' + additionalImagesHtml + '</section>' +
            '</div>'
        );

        $modal.addClass('is-open').attr('aria-hidden', 'false');
    }

    function collectPayload() {
        var payload = $.extend(true, {}, activeContext ? activeContext.generated : {});
        $('#shynh-seo-ai-sync-modal [data-key]').each(function() {
            payload[$(this).data('key')] = $(this).val();
        });

        payload.image_suggestions = [];
        $('#shynh-seo-ai-sync-modal .shynh-seo-ai-sync-image-row').each(function() {
            var item = {};
            $(this).find('[data-image-key]').each(function() {
                var key = $(this).data('image-key');
                item[key] = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
            });
            payload.image_suggestions.push(item);
        });

        if (!payload.content && payload.quick_answer) {
            payload.content = '<p>' + payload.quick_answer + '</p>';
        }

        return payload;
    }

    function getBoxContext($button) {
        var $box = $button.closest('.shynh-seo-ai-sync-box');
        return {
            objectType: $box.data('object-type') || 'post',
            objectId: $box.data('object-id') || 0,
            taxonomy: $box.data('taxonomy') || '',
            modelMode: $box.find('.shynh-seo-ai-sync-model-mode').val() || 'auto',
            customModel: $box.find('.shynh-seo-ai-sync-custom-model').val() || ''
        };
    }

    $(document).on('change', '.shynh-seo-ai-sync-model-mode', function() {
        var isCustom = $(this).val() === 'custom';
        $(this).closest('.shynh-seo-ai-sync-models').find('.shynh-seo-ai-sync-custom-model').toggle(isCustom);
    });

    $(document).on('click', '.shynh-seo-ai-sync-open', function(e) {
        e.preventDefault();

        var $button = $(this);
        var context = getBoxContext($button);
        $button.prop('disabled', true).text(shynhSeoAiSync.i18n.loading);

        $.post(shynhSeoAiSync.ajaxUrl, {
            action: 'shynh_seo_ai_sync_wrapup',
            nonce: shynhSeoAiSync.nonce,
            objectType: context.objectType,
            objectId: context.objectId,
            taxonomy: context.taxonomy,
            modelMode: context.modelMode,
            customModel: context.customModel
        }).done(function(response) {
            if (!response || !response.success) {
                alert(response && response.data && response.data.message ? response.data.message : 'AI Sync error');
                return;
            }
            renderModal(response.data);
        }).fail(function() {
            alert('AI Sync request failed.');
        }).always(function() {
            var defaultLabel = context.objectType === 'term' ? 'Generate English Term Wrap-up' : 'Generate English Wrap-up';
            var label = (shynhSeoAiSync.i18n && shynhSeoAiSync.i18n.generateWrapup)
                ? (context.objectType === 'term' ? shynhSeoAiSync.i18n.generateTermWrapup : shynhSeoAiSync.i18n.generateWrapup)
                : defaultLabel;
            $button.prop('disabled', false).text(label);
        });
    });

    $(document).on('click', '.shynh-seo-ai-sync-close', function(e) {
        e.preventDefault();
        ensureModal().removeClass('is-open').attr('aria-hidden', 'true');
    });

    $(document).on('click', '.shynh-seo-ai-sync-create', function(e) {
        e.preventDefault();

        if (!activeContext || !activeContext.source) {
            return;
        }

        var payload = collectPayload();
        if (!$.trim(payload.title || '') || !$.trim(payload.content || '')) {
            alert(shynhSeoAiSync.i18n.requiredTitle);
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text(shynhSeoAiSync.i18n.creating);

        $.post(shynhSeoAiSync.ajaxUrl, {
            action: 'shynh_seo_ai_sync_create_draft',
            nonce: shynhSeoAiSync.nonce,
            objectType: activeContext.source.objectType,
            objectId: activeContext.source.id,
            taxonomy: activeContext.source.taxonomy || '',
            payload: JSON.stringify(payload)
        }).done(function(response) {
            if (!response || !response.success) {
                alert(response && response.data && response.data.message ? response.data.message : 'Create draft error');
                return;
            }

            var data = response.data || {};
            $('#shynh-seo-ai-sync-modal .shynh-seo-ai-sync-modal__header p').text(data.message || '');
            if (data.editUrl) {
                $('#shynh-seo-ai-sync-modal .shynh-seo-ai-sync-edit-link').attr('href', data.editUrl).show();
            }
        }).fail(function() {
            alert('Create draft request failed.');
        }).always(function() {
            $button.prop('disabled', false).text(shynhSeoAiSync.i18n.createDraft);
        });
    });
})(jQuery);
