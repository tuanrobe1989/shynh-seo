jQuery(document).ready(function($) {
    if (typeof shynhSeoData === 'undefined') return;

    let debounceTimer;

    const $keywordInput = $('#shynh_seo_focus_keyword');
    const $titleInput   = $('#shynh_seo_title');
    const $descInput    = $('#shynh_seo_meta_desc');
    const $geoAeoMode   = $('#shynh_seo_geo_aeo_mode');
    const $scorePanel   = $('#shynh-seo-score-panel');
    const $badge        = $('#shynh-seo-title-badge');

    const $previewTitle = $('#sgp-title-preview');
    const $previewDesc  = $('#sgp-desc-preview');

    function getLabel(key, fallback) {
        return (shynhSeoData.i18n && shynhSeoData.i18n[key]) ? shynhSeoData.i18n[key] : fallback;
    }

    function getEditorData() {
        let content = '';
        let title   = '';
        let slug    = '';

        if (shynhSeoData.isTerm) {
            content = $('#description').val() || '';
            title   = $('#name').val() || '';
            slug    = $('#slug').val() || '';
        } else if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent();
            title   = wp.data.select('core/editor').getEditedPostAttribute('title');
            slug    = wp.data.select('core/editor').getEditedPostAttribute('slug');
        } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
            content = tinymce.activeEditor.getContent();
            title   = $('#title').val();
            slug    = $('#post_name').val();
        }

        return { content, title, slug };
    }

    function replaceSnippetVars(str, editorTitle) {
        if (!str) return str;
        let vars = shynhSeoData.snippetVars || {};
        let currentTitle = editorTitle || vars['%%title%%'] || '';

        let result = str;
        result = result.replace(/%%title%%/g, currentTitle);
        result = result.replace(/%%sitename%%/g, vars['%%sitename%%'] || '');
        result = result.replace(/%%sitedesc%%/g, vars['%%sitedesc%%'] || '');
        result = result.replace(/%%page%%/g, vars['%%page%%'] || '');
        result = result.replace(/%%excerpt%%/g, vars['%%excerpt%%'] || '');
        result = result.replace(/%%date%%/g, vars['%%date%%'] || '');
        result = result.replace(/%%modified%%/g, vars['%%modified%%'] || '');
        result = result.replace(/%%author%%/g, vars['%%author%%'] || '');
        result = result.replace(/%%category%%/g, vars['%%category%%'] || '');

        let sep = vars['%%sep%%'] || '-';
        result = result.replace(/%%sep%%/g, sep);

        let sepPattern = sep.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        result = result.replace(new RegExp('\\s*' + sepPattern + '\\s*' + sepPattern, 'g'), ' ' + sep + ' ');
        result = result.replace(new RegExp('\\s*' + sepPattern + '\\s*$'), '');
        result = result.replace(new RegExp('^\\s*' + sepPattern + '\\s*'), '');

        return result.trim();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAnalysisError(message) {
        $scorePanel.html('<p class="shynh-seo-analysis-error">' + escapeHtml(message || getLabel('analysisUnavailable', 'Cannot analyze SEO.')) + '</p>');
    }

    function renderRuleItem(result) {
        let status = ['green', 'orange', 'red'].indexOf(result.status) !== -1 ? result.status : 'gray';
        let rec = escapeHtml(result.recommendation || '');
        let details = escapeHtml(result.details || '');
        let action = escapeHtml(result.action_tip || '');
        let title = escapeHtml(result.message || '');

        let html = '<li class="shynh-seo-score-item shynh-seo-score-item--' + status + '" ';
        html += 'data-status="' + status + '" ';
        html += 'data-title="' + title + '" ';
        html += 'data-recommendation="' + rec + '" ';
        html += 'data-details="' + details + '" ';
        html += 'data-action="' + action + '">';
        html += '<span class="shynh-seo-status-dot" aria-hidden="true"></span>';
        html += '<span>' + title + '</span>';
        html += '</li>';
        return html;
    }

    function runAnalysis(forceFe) {
        const editorData = getEditorData();
        const isTerm = !!shynhSeoData.isTerm;
        const termId = isTerm ? (shynhSeoData.termId || $('#tag_ID').val() || $('input[name="tag_ID"]').val() || 0) : 0;
        const postId = !isTerm ? (shynhSeoData.postId || $('#post_ID').val() || 0) : 0;

        const data = {
            action: 'shynh_seo_analyze',
            nonce: shynhSeoData.nonce,
            post_id: postId,
            term_id: termId,
            keyword: $keywordInput.val(),
            title: $titleInput.val() || editorData.title,
            desc: $descInput.val(),
            content: editorData.content,
            slug: editorData.slug,
            geo_aeo_mode: $geoAeoMode.val() || '',
            fetch_fe: forceFe ? 1 : 0
        };

        let previewTitleStr = $titleInput.val() || shynhSeoData.snippetVars['%%title%%'] || editorData.title || getLabel('defaultTitle', 'Post title');
        let previewDescStr  = $descInput.val() || getLabel('defaultDescription', 'Please add a meta description.');

        $previewTitle.text(replaceSnippetVars(previewTitleStr, editorData.title));
        $previewDesc.text(replaceSnippetVars(previewDescStr, editorData.title));

        $scorePanel.html('<div class="shynh-seo-spinner"></div><p>' + escapeHtml(getLabel('analyzing', 'Analyzing...')) + '</p>');

        $.post(shynhSeoData.ajaxUrl, data, function(response) {
            if (response.success) {
                renderResults(response.data);
            } else {
                const message = response.data && response.data.message ? response.data.message : getLabel('analysisError', 'SEO analysis error.');
                showAnalysisError(message);
            }
        }).fail(function(xhr) {
            let message = getLabel('analysisError', 'SEO analysis error.');

            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            console.error('Shynh SEO analysis request failed', xhr);
            showAnalysisError(message);
        });
    }

    function renderResults(data) {
        let html = '';

        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:0 4px;">';
        html += '  <small style="color:#64748b; font-size:11px; font-weight:500;">👁️ Bấm "Đồng bộ FE" để phân tích theo bố cục thực tế ngoài Frontend</small>';
        html += '  <button type="button" class="button button-small shynh-seo-fetch-fe-btn" style="font-size:11px; height:24px; line-height:22px;" title="Nạp lại nội dung ngoài Frontend để phân tích chính xác">👁️ Đồng bộ FE</button>';
        html += '</div>';

        if (data.geo_aeo) {
            html += '<div class="shynh-seo-scores-row">';
            html += '  <div class="shynh-seo-score-card">';
            html += '    <div class="shynh-seo-score-circle shynh-seo-badge--' + data.color + '">' + data.score + '%</div>';
            html += '    <div class="shynh-seo-score-label">' + escapeHtml(getLabel('seoScore', 'Điểm SEO')) + '</div>';
            html += '  </div>';
            html += '  <div class="shynh-seo-score-card">';
            html += '    <div class="shynh-seo-score-circle shynh-seo-badge--' + data.geo_aeo.color + '">' + data.geo_aeo.score + '%</div>';
            html += '    <div class="shynh-seo-score-label">' + escapeHtml(getLabel('geoAeoScore', 'Điểm GEO/AEO')) + '</div>';
            html += '  </div>';
            html += '</div>';

            if (typeof data.overall_ai_search_score !== 'undefined') {
                html += '<p style="text-align:center; font-size:12px; color:var(--shynh-text-muted,#64748b); margin:-6px 0 16px;">' + escapeHtml(getLabel('aiSearchOverall', 'AI Search Overall')) + ': <strong>' + data.overall_ai_search_score + '%</strong></p>';
            }

            html += '<div class="shynh-seo-analysis-grid">';
            
            html += '  <div class="shynh-seo-analysis-col">';
            html += '    <h4 class="shynh-seo-col-title">Tiêu chuẩn SEO</h4>';
            html += '    <ul class="shynh-seo-score-list">';
            for (let key in data.results) {
                html += renderRuleItem(data.results[key]);
            }
            html += '    </ul>';
            html += '  </div>';

            html += '  <div class="shynh-seo-analysis-col">';
            html += '    <h4 class="shynh-seo-col-title">Tiêu chuẩn GEO/AEO</h4>';
            html += '    <ul class="shynh-seo-score-list">';
            for (let geoKey in data.geo_aeo.results) {
                html += renderRuleItem(data.geo_aeo.results[geoKey]);
            }
            html += '    </ul>';
            html += '  </div>';

            html += '</div>';
        } else {
            html += '<div class="shynh-seo-score-overall">';
            html += '<div class="shynh-seo-score-circle shynh-seo-badge--' + data.color + '">' + data.score + '%</div>';
            html += '<h4 style="text-align:center; margin:0 0 16px;">' + escapeHtml(getLabel('seoScore', 'Điểm SEO Tổng Thể')) + '</h4>';
            html += '</div><ul class="shynh-seo-score-list">';

            for (let key in data.results) {
                html += renderRuleItem(data.results[key]);
            }

            html += '</ul>';
        }

        $scorePanel.html(html);

        $badge.removeClass('shynh-seo-badge--green shynh-seo-badge--orange shynh-seo-badge--red shynh-seo-badge--gray')
            .addClass('shynh-seo-badge--' + data.color)
            .text('SEO: ' + data.score + '%');

        const $geoBadge = $('#shynh-seo-geo-title-badge');
        if ($geoBadge.length) {
            if (data.geo_aeo) {
                $geoBadge.removeClass('shynh-seo-badge--green shynh-seo-badge--orange shynh-seo-badge--red shynh-seo-badge--gray')
                    .addClass('shynh-seo-badge--' + data.geo_aeo.color)
                    .text('GEO: ' + data.geo_aeo.score + '%')
                    .show();
            } else {
                $geoBadge.hide();
            }
        }
    }

    function debouncedAnalysis() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runAnalysis, 800);
    }

    $keywordInput.on('input', debouncedAnalysis);
    $titleInput.on('input', debouncedAnalysis);
    $descInput.on('input', debouncedAnalysis);
    $geoAeoMode.on('change', debouncedAnalysis);

    if (shynhSeoData.isTerm) {
        $('#name, #tag-name, #slug, #tag-slug, #description, #tag-description').on('input', debouncedAnalysis);

        var injectTermAiBtn = function() {
            var $nameLabel = $('.form-field label[for="name"], .form-field label[for="tag-name"], label[for="name"], label[for="tag-name"]');
            if ($nameLabel.length && !$nameLabel.find('.shynh-seo-ai-field-btn').length) {
                $nameLabel.append(' <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="term_name" title="Gợi ý tên bằng AI ✨" style="margin-left:8px;">✨ AI Gợi Ý Tên</button>');
            }

            var $slugLabel = $('.form-field label[for="slug"], .form-field label[for="tag-slug"], label[for="slug"], label[for="tag-slug"]');
            if ($slugLabel.length && !$slugLabel.find('.shynh-seo-ai-field-btn').length) {
                $slugLabel.append(' <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="term_slug" title="Sinh đường dẫn bằng AI ✨" style="margin-left:8px;">✨ AI Sinh Slug</button>');
            }

            var $descLabel = $('.term-description-wrap label[for="description"], .term-description-wrap label[for="tag-description"], label[for="description"], label[for="tag-description"]');
            if ($descLabel.length && !$descLabel.find('.shynh-seo-ai-field-btn').length) {
                $descLabel.append(' <button type="button" class="button button-small shynh-seo-ai-field-btn" data-field="term_description" title="Sinh mô tả chuyên mục bằng AI ✨" style="margin-left:8px;">✨ AI Sinh Mô Tả Chuyên Mục</button>');
            }
        };

        injectTermAiBtn();
        $(document).ready(injectTermAiBtn);
    }

    if (typeof wp !== 'undefined' && wp.data) {
        wp.data.subscribe(function() {
            debouncedAnalysis();
        });
    }

    $(document).on('click', '.shynh-seo-ai-field-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var fieldName = $btn.data('field');
        var originalText = $btn.text();

        var fieldMap = {
            'keyword': '#shynh_seo_focus_keyword',
            'title': '#shynh_seo_title',
            'meta_desc': '#shynh_seo_meta_desc',
            'quick_answer': '#shynh_seo_quick_answer',
            'fb_title': '#shynh_seo_fb_title',
            'fb_desc': '#shynh_seo_fb_desc',
            'term_name': '#name, #tag-name',
            'term_slug': '#slug, #tag-slug',
            'term_description': '#description, #tag-description',
            'description': '#description, #tag-description'
        };

        var targetSelector = fieldMap[fieldName] || '#shynh_seo_' + fieldName;
        var $target = $(targetSelector);

        if (!$target.length) {
            return;
        }

        $btn.prop('disabled', true).text('✨ Đang sinh...');

        var getUrlParam = function(param) {
            var searchParams = new URLSearchParams(window.location.search);
            return searchParams.get(param) || '';
        };

        var isTerm = !!shynhSeoData.isTerm;
        var termId = shynhSeoData.termId || $('#tag_ID').val() || $('input[name="tag_ID"]').val() || 0;
        var objectId = isTerm ? parseInt(termId, 10) : (shynhSeoData.postId || $('#post_ID').val() || 0);
        var taxonomy = shynhSeoData.taxonomy || $('#taxonomy').val() || $('input[name="taxonomy"]').val() || '';
        var parentId = $('#parent').val() || 0;
        var fromTag = getUrlParam('from_tag') || $('input[name="from_tag"]').val() || 0;
        var lang = getUrlParam('new_lang') || $('#post_lang_choice').val() || $('select[name="post_lang_choice"]').val() || '';
        var nonce = (typeof shynhSeoAiSync !== 'undefined' && shynhSeoAiSync.nonce) ? shynhSeoAiSync.nonce : (shynhSeoData.nonce || '');

        $.post(shynhSeoData.ajaxUrl, {
            action: 'shynh_seo_ai_suggest_field',
            nonce: nonce,
            fieldName: fieldName,
            objectId: objectId,
            objectType: isTerm ? 'term' : 'post',
            taxonomy: taxonomy,
            parentId: parentId,
            fromTag: fromTag,
            lang: lang,
            currentKeyword: $keywordInput.val() || '',
            currentTitle: $titleInput.val() || '',
            currentDesc: $descInput.val() || '',
            currentValue: $target.val() || ''
        }).done(function(response) {
            if (response && response.success && response.data) {
                var val = response.data.value || (typeof response.data === 'string' ? response.data : '');
                if (val) {
                    $target.val(val).trigger('input');
                }
                if (response.data.slug) {
                    var $slugField = $('#slug, #tag-slug');
                    if ($slugField.length && (!$slugField.val() || fieldName === 'term_name')) {
                        $slugField.val(response.data.slug).trigger('input');
                    }
                }
            } else if (response && response.data && response.data.message) {
                alert(response.data.message);
            }
        }).fail(function() {
            alert('Không thể kết nối AI gợi ý.');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    $(document).on('click', '.shynh-seo-fetch-fe-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text('⏳ Đang nạp FE...');
        runAnalysis(true);
    });

    // Popover Card Manager
    var $popoverCard = null;

    function getPopoverCard() {
        if (!$popoverCard || !$popoverCard.length) {
            $popoverCard = $('<div class="shynh-seo-popover-card"></div>').appendTo('body');
        }
        return $popoverCard;
    }

    function showPopover($item) {
        var status = $item.data('status') || 'gray';
        var title = $item.data('title') || '';
        var rec = $item.data('recommendation') || '';
        var details = $item.data('details') || '';
        var action = $item.data('action') || '';

        if (!rec && !details && !action) {
            return;
        }

        var badgeLabel = status === 'green' ? 'Đạt Chuẩn' : (status === 'orange' ? 'Cần Cải Thiện' : 'Chưa Đạt');
        var card = getPopoverCard();

        card.attr('class', 'shynh-seo-popover-card shynh-seo-popover-card--' + status);

        var html = '<div class="shynh-seo-popover-header">';
        html += '  <span>' + title + '</span>';
        html += '  <span class="shynh-seo-popover-badge shynh-seo-popover-badge--' + status + '">' + badgeLabel + '</span>';
        html += '</div>';

        if (rec) {
            html += '<div class="shynh-seo-popover-section">';
            html += '  <div class="shynh-seo-popover-label">🎯 Tiêu chuẩn & Lời khuyên:</div>';
            html += '  <div class="shynh-seo-popover-text">' + rec + '</div>';
            html += '</div>';
        }

        if (details) {
            var formattedDetails = details.replace(/\n/g, '<br>');
            html += '<div class="shynh-seo-popover-section">';
            html += '  <div class="shynh-seo-popover-label">🔍 Dữ liệu thực tế quét được từ bài:</div>';
            html += '  <div class="shynh-seo-popover-details">' + formattedDetails + '</div>';
            html += '</div>';
        }

        if (action) {
            html += '<div class="shynh-seo-popover-action">💡 <strong>Hướng dẫn:</strong> ' + action + '</div>';
        }

        card.html(html);

        // Smart Positioning
        var offset = $item.offset();
        var itemHeight = $item.outerHeight();
        var cardWidth = 320;
        var windowWidth = $(window).width();
        var windowScrollTop = $(window).scrollTop();

        var top = offset.top - card.outerHeight() - 8;
        var left = offset.left;

        if (top < windowScrollTop + 10) {
            top = offset.top + itemHeight + 8;
        }

        if (left + cardWidth > windowWidth - 20) {
            left = windowWidth - cardWidth - 20;
        }
        if (left < 10) {
            left = 10;
        }

        card.css({ top: top + 'px', left: left + 'px' }).addClass('is-visible');
    }

    function hidePopover() {
        if ($popoverCard) {
            $popoverCard.removeClass('is-visible');
        }
    }

    $(document).on('mouseenter click', '.shynh-seo-score-item', function(e) {
        showPopover($(this));
    });

    $(document).on('mouseleave', '.shynh-seo-score-item', function(e) {
        hidePopover();
    });

    $(document).on('scroll', function() {
        hidePopover();
    });

    setTimeout(runAnalysis, 1000);
});
