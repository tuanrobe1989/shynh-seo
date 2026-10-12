jQuery(document).ready(function($) {
    'use strict';

    // Toggle Floating Drawer
    function toggleDrawer() {
        var $drawer = $('#shynh-seo-ai-drawer');
        $drawer.fadeToggle(200);
    }

    $(document).on('click', '#shynh-seo-ai-floating-trigger', function(e) {
        e.preventDefault();
        toggleDrawer();
    });

    $(document).on('click', '#shynh-seo-ai-drawer-close', function(e) {
        e.preventDefault();
        $('#shynh-seo-ai-drawer').fadeOut(200);
    });

    // Keyboard Shortcut Alt + A to toggle
    $(document).on('keydown', function(e) {
        if (e.altKey && (e.key === 'a' || e.key === 'A' || e.keyCode === 65)) {
            e.preventDefault();
            toggleDrawer();
        }
    });

    // Category Tab Nav Switcher
    $(document).on('click', '.shynh-seo-ai-tab-btn', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        $('.shynh-seo-ai-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.shynh-seo-ai-tab-pane').removeClass('active');
        $('#shynh-tab-' + tabId).addClass('active');
    });

    // Preset Chip Click Handler
    $(document).on('click', '.shynh-seo-ai-chip', function(e) {
        e.preventDefault();
        var promptText = $(this).data('prompt');
        var $textarea = $(this).closest('#shynh-seo-ai-drawer').find('.shynh-seo-ai-user-prompt');
        var currentVal = $textarea.val().trim();

        if (currentVal) {
            $textarea.val(currentVal + ' ' + promptText);
        } else {
            $textarea.val(promptText);
        }
    });

    // Generate Button Click Handler
    $(document).on('click', '.shynh-seo-ai-generate-btn, .shynh-seo-ai-retry-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('#shynh-seo-ai-drawer');
        var $textarea = $card.find('.shynh-seo-ai-user-prompt');
        var targetField = $card.find('.shynh-seo-ai-target-field').val();
        var genMode = $card.find('.shynh-seo-ai-gen-mode').val() || 'preserve_enrich';
        var userPrompt = $textarea.val().trim();
        var $preview = $card.find('.shynh-seo-ai-preview-card');
        var $previewBody = $card.find('.shynh-seo-ai-preview-body');
        var $charCounter = $card.find('.shynh-seo-ai-char-counter');

        var objectType = $card.data('object-type') || 'post';
        var objectId = $card.data('object-id') || 0;
        var taxonomy = $card.data('taxonomy') || '';

        var originalText = $btn.html();
        $btn.prop('disabled', true).html('⏳ OpenAI Đang Xử Lý...');

        var data = {
            action: 'shynh_seo_ai_assistant_generate',
            nonce: shynhSeoAiAssistant.nonce,
            objectType: objectType,
            objectId: objectId,
            taxonomy: taxonomy,
            targetField: targetField,
            genMode: genMode,
            userPrompt: userPrompt,
            currentKeyword: $('#_shynh_seo_focus_keyword').val() || $('#tag-focus-keyword').val() || '',
            currentTitle: $('#_shynh_seo_title').val() || $('#tag-seo-title').val() || '',
            currentDesc: $('#_shynh_seo_meta_desc').val() || $('#tag-meta-desc').val() || ''
        };

        $.post(shynhSeoAiAssistant.ajaxUrl, data, function(response) {
            if (response.success && response.data) {
                var res = response.data;
                $previewBody.empty().data('is-all', false).data('generated-val', '').data('target-field', targetField);

                if (res.isAll && res.allData) {
                    $previewBody.data('is-all', true).data('all-data', res.allData);
                    var html = '<div class="shynh-seo-ai-all-results">';
                    if (res.allData.title) html += '<div><strong>📌 Title:</strong> ' + res.allData.title + '</div>';
                    if (res.allData.meta_desc) html += '<div><strong>📝 Meta Desc:</strong> ' + res.allData.meta_desc + '</div>';
                    if (res.allData.keyword) html += '<div><strong>🔑 Keyword:</strong> ' + res.allData.keyword + '</div>';
                    if (res.allData.quick_answer) html += '<div><strong>⚡ Quick Answer:</strong> ' + res.allData.quick_answer + '</div>';
                    if (res.allData.slug) html += '<div><strong>🔗 Slug:</strong> ' + res.allData.slug + '</div>';
                    if (res.allData.term_description) html += '<div><strong>📄 Term Desc:</strong> ' + res.allData.term_description + '</div>';
                    if (res.allData.post_content) html += '<div><strong>✨ Post Content:</strong> [Sẵn sàng nhúng vào Gutenberg Canvas]</div>';
                    
                    if (res.allData.warnings && res.allData.warnings.length) {
                        html += '<div style="color: #856404; background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-top: 10px; border-radius: 4px;">';
                        html += '<strong>⚠️ AI Block Validator Cảnh Báo:</strong><ul>';
                        res.allData.warnings.forEach(function(w) { html += '<li>' + w + '</li>'; });
                        html += '</ul></div>';
                    }
                    if (res.allData.dropped && res.allData.dropped.length) {
                        html += '<div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-top: 10px; border-radius: 4px;">';
                        html += '<strong>❌ Đã Loại Bỏ Nội Dung Sai Cấu Trúc:</strong><ul>';
                        res.allData.dropped.forEach(function(d) { html += '<li><code>' + d.block + '</code>: ' + d.reason + '</li>'; });
                        html += '</ul></div>';
                    }

                    html += '</div>';
                    $previewBody.html(html);
                    $charCounter.text('Trọn bộ 9 mục (bao gồm Post Content)');
                } else if (res.value) {
                    var val = res.value;
                    var charCount = res.charCount || val.length;
                    $previewBody.text(val).data('generated-val', val).data('target-field', targetField);
                    $charCounter.text(charCount + ' ký tự');
                }

                $preview.slideDown(200);
            } else {
                alert(response.data && response.data.message ? response.data.message : shynhSeoAiAssistant.i18n.error);
            }
        }).fail(function() {
            alert(shynhSeoAiAssistant.i18n.error);
        }).always(function() {
            $btn.prop('disabled', false).html(originalText);
        });
    });

    // Direct Injection into Gutenberg Block Canvas / TinyMCE / Post Editor
    function applyPostContentToEditor(content) {
        if (!content) return;

        // 1. Gutenberg Block Editor API
        if (window.wp && wp.data && wp.data.dispatch('core/editor') && wp.blocks) {
            try {
                var parsedBlocks = wp.blocks.parse(content);
                if (parsedBlocks && parsedBlocks.length) {
                    wp.data.dispatch('core/editor').resetBlocks(parsedBlocks);
                    
                    // Tầng 3: Tự Động Phục Hồi Ở Phía Javascript (Block Recovery API)
                    setTimeout(function() {
                        var allBlocks = wp.data.select('core/block-editor').getBlocks();
                        allBlocks.forEach(function(b) {
                            if (b.isValid === false) {
                                // Tự động phục hồi block lỗi mà không báo động
                                wp.data.dispatch('core/block-editor').recoverBlock(b.clientId);
                            }
                        });
                    }, 500);
                } else {
                    wp.data.dispatch('core/editor').editPost({ content: content });
                }

                var $gutenbergCanvas = $('.editor-styles-wrapper, .block-editor-block-list__layout, .edit-post-layout__content');
                if ($gutenbergCanvas.length) {
                    $gutenbergCanvas.addClass('shynh-seo-input-highlight');
                    setTimeout(function() {
                        $gutenbergCanvas.removeClass('shynh-seo-input-highlight');
                    }, 2000);
                }
                return;
            } catch (err) {
                console.warn('Gutenberg block injection error:', err);
            }
        }

        // 2. TinyMCE Classic Editor API
        if (window.tinymce && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.setContent(content);
            var $mceWrapper = $('#wp-content-wrap');
            if ($mceWrapper.length) {
                $mceWrapper.addClass('shynh-seo-input-highlight');
                setTimeout(function() {
                    $mceWrapper.removeClass('shynh-seo-input-highlight');
                }, 2000);
            }
            return;
        }

        // 3. Plain Textarea #content
        var $contentArea = $('#content');
        if ($contentArea.length) {
            $contentArea.val(content).trigger('input').trigger('change');
            $contentArea.addClass('shynh-seo-input-highlight');
            setTimeout(function() {
                $contentArea.removeClass('shynh-seo-input-highlight');
            }, 2000);
        }
    }

    // Helper to apply single field and trigger pulse glow
    function applyField(targetField, val) {
        if (!val) return;
        var $targetInput = null;

        if (targetField === 'title') {
            $targetInput = $('#_shynh_seo_title, #tag-seo-title, input[name="_shynh_seo_title"]');
        } else if (targetField === 'meta_desc') {
            $targetInput = $('#_shynh_seo_meta_desc, #tag-meta-desc, textarea[name="_shynh_seo_meta_desc"]');
        } else if (targetField === 'keyword') {
            $targetInput = $('#_shynh_seo_focus_keyword, #tag-focus-keyword, input[name="_shynh_seo_focus_keyword"]');
        } else if (targetField === 'quick_answer') {
            $targetInput = $('#_shynh_seo_quick_answer, #tag-quick-answer, textarea[name="_shynh_seo_quick_answer"]');
        } else if (targetField === 'slug') {
            $targetInput = $('#post_name, #slug, input[name="slug"]');
        } else if (targetField === 'term_description') {
            $targetInput = $('#description, textarea[name="description"]');
        } else if (targetField === 'fb_title') {
            $targetInput = $('#_shynh_seo_fb_title, #tag-fb-title, input[name="_shynh_seo_fb_title"]');
        } else if (targetField === 'fb_desc') {
            $targetInput = $('#_shynh_seo_fb_desc, #tag-fb-desc, textarea[name="_shynh_seo_fb_desc"]');
        } else if (targetField === 'post_content') {
            applyPostContentToEditor(val);
            return;
        }

        if ($targetInput && $targetInput.length) {
            $targetInput.val(val).trigger('input').trigger('change');
            $targetInput.addClass('shynh-seo-input-highlight');
            setTimeout(function() {
                $targetInput.removeClass('shynh-seo-input-highlight');
            }, 2000);
        }
    }

    // One-Click Apply Button Handler (Single or Batch All)
    $(document).on('click', '.shynh-seo-ai-apply-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $preview = $btn.closest('.shynh-seo-ai-preview-card');
        var $previewBody = $preview.find('.shynh-seo-ai-preview-body');

        var isAll = $previewBody.data('is-all');
        if (isAll) {
            var allData = $previewBody.data('all-data');
            if (allData) {
                if (allData.title) applyField('title', allData.title);
                if (allData.meta_desc) applyField('meta_desc', allData.meta_desc);
                if (allData.keyword) applyField('keyword', allData.keyword);
                if (allData.quick_answer) applyField('quick_answer', allData.quick_answer);
                if (allData.slug) applyField('slug', allData.slug);
                if (allData.term_description) applyField('term_description', allData.term_description);
                if (allData.fb_title) applyField('fb_title', allData.fb_title);
                if (allData.fb_desc) applyField('fb_desc', allData.fb_desc);
                if (allData.post_content) applyField('post_content', allData.post_content);
            }
        } else {
            var val = $previewBody.data('generated-val');
            var targetField = $previewBody.data('target-field');
            applyField(targetField, val);
        }

        // Feedback on button
        var originalBtnText = $btn.html();
        $btn.html('✅ Đã Áp Dụng Tất Cả!').addClass('button-disabled');
        setTimeout(function() {
            $btn.html(originalBtnText).removeClass('button-disabled');
        }, 2000);
    });
});
