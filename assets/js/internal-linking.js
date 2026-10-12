jQuery(function($) {
    if (typeof shynhSeoLinking === 'undefined') {
        return;
    }

    const context = shynhSeoLinking.relatedContext || {};
    const state = {
        internalItems: parseInternalItems(),
        internalSearchTimer: null,
        keywordSearchTimer: null
    };

    const $keywordInput = $('#shynh_seo_focus_keyword');
    const $suggestionPanel = $('#shynh-seo-linking-panel');
    const $linkedPostsPanel = $('#shynh-seo-linked-posts');
    const $internalField = $('#shynh_seo_internal_link_posts');
    const $selectedList = $('#shynh-seo-related-selected-list');
    const $searchInput = $('#shynh-seo-related-search-input');
    const $results = $('#shynh-seo-related-search-results');
    const $postTypeFilter = $('#shynh-seo-related-post-type-filter');
    const $taxFilter = $('#shynh-seo-related-tax-filter');

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function parseInternalItems() {
        try {
            const parsed = JSON.parse($('#shynh_seo_internal_link_posts').val() || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function normalizeItem(item) {
        return {
            post_id: parseInt(item.post_id || item.id, 10),
            title: item.title || item.original_title || '',
            url: item.url || '',
            post_type: item.post_type || '',
            language: item.language || '',
            date: item.date || '',
            color: item.color || ''
        };
    }

    function syncInternalField() {
        const payload = state.internalItems.map(function(item) {
            return {
                post_id: parseInt(item.post_id, 10),
                title: item.title || '',
                url: item.url || ''
            };
        });
        $internalField.val(JSON.stringify(payload));
    }

    function renderFilters() {
        const selectedPostType = $postTypeFilter.val() || '';
        const selectedTax = $taxFilter.val() || '';

        if ($postTypeFilter.length) {
            $postTypeFilter.empty();
            $postTypeFilter.append('<option value="">Tất cả post type</option>');
            (context.postTypes || []).forEach(function(postType) {
                $postTypeFilter.append('<option value="' + escapeHtml(postType.slug) + '">' + escapeHtml(postType.label || postType.slug) + '</option>');
            });
            if (selectedPostType) {
                $postTypeFilter.val(selectedPostType);
            }
            $postTypeFilter.prop('disabled', false);
        }

        if ($taxFilter.length) {
            const currentPostType = $postTypeFilter.val() || '';
            $taxFilter.empty();
            $taxFilter.append('<option value="">Chưa chọn taxonomy</option>');
            (context.taxonomies || []).filter(function(item) {
                return !currentPostType || !item.post_types || item.post_types.includes(currentPostType);
            }).forEach(function(item) {
                $taxFilter.append(
                    '<option value="' + escapeHtml(item.taxonomy + ':' + item.term_id) + '">' +
                    escapeHtml(item.label) +
                    '</option>'
                );
            });
            if (selectedTax && $taxFilter.find('option[value="' + selectedTax.replace(/"/g, '\\"') + '"]').length) {
                $taxFilter.val(selectedTax);
            }
        }
    }

    function renderSelected() {
        $selectedList.empty();

        if (!state.internalItems.length) {
            $selectedList.html('<em class="shynh-seo-related-empty">Chưa chọn internal link nào.</em>');
            syncInternalField();
            return;
        }

        state.internalItems.forEach(function(rawItem, index) {
            const item = normalizeItem(rawItem);
            const title = item.title || ('Post #' + item.post_id);
            const row = [
                '<div class="shynh-seo-related-selected-item" data-index="' + index + '">',
                    '<div class="shynh-seo-related-selected-title">',
                        '<strong>' + escapeHtml(title) + '</strong>',
                        item.url ? '<a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(item.url) + '</a>' : '',
                    '</div>',
                    '<button type="button" class="button-link-delete shynh-seo-related-remove">Xóa</button>',
                '</div>'
            ].join('');

            $selectedList.append(row);
        });

        syncInternalField();
    }

    function addInternalLink(post) {
        const item = normalizeItem(post);
        if (!item.post_id) {
            return;
        }

        const exists = state.internalItems.some(function(current) {
            return parseInt(current.post_id, 10) === item.post_id;
        });

        if (!exists) {
            state.internalItems.push(item);
            renderSelected();
        }
    }

    function getSelectedTaxFilter() {
        const raw = $taxFilter.val() || '';
        const parts = raw ? raw.split(':') : [];
        return {
            taxonomy: parts[0] || '',
            term_id: parts[1] || ''
        };
    }

    function renderSuggestionRows(posts) {
        if (!posts.length) {
            $results.html('<p>Không có bài nào.</p>');
            return;
        }

        let html = '<ul class="shynh-seo-related-result-list">';
        posts.forEach(function(post) {
            const color = post.color || 'red';
            html += [
                '<li class="shynh-seo-related-result">',
                    '<div class="shynh-seo-related-result-main">',
                        '<strong><span class="shynh-seo-related-dot ' + escapeHtml(color) + '"></span>' + escapeHtml(post.title) + '</strong>',
                        '<span>' + escapeHtml(post.post_type || '') + ' · Ngày: ' + escapeHtml(post.date || '') + '</span>',
                    '</div>',
                    '<button type="button" class="button shynh-seo-related-add" data-post="' + escapeHtml(JSON.stringify(post)) + '">Thêm</button>',
                '</li>'
            ].join('');
        });
        html += '</ul>';
        $results.html(html);
    }

    function fetchInternalSuggestions() {
        const tax = getSelectedTaxFilter();
        $results.html('<div class="shynh-seo-spinner"></div><p>Đang tìm gợi ý...</p>');

        $.post(shynhSeoLinking.ajaxUrl, {
            action: 'shynh_seo_search_related_posts',
            nonce: shynhSeoLinking.nonce,
            post_id: shynhSeoLinking.postId,
            search: $searchInput.val() || '',
            post_type: $postTypeFilter.val() || '',
            taxonomy: tax.taxonomy,
            term_id: tax.term_id
        }, function(response) {
            renderSuggestionRows(response.success ? response.data : []);
        });
    }

    function fetchKeywordSuggestions() {
        const keyword = $keywordInput.val();
        if (!$suggestionPanel.length) {
            return;
        }

        if (!String(keyword || '').trim()) {
            $suggestionPanel.html('<p>Nhập Focus Keyword để xem gợi ý...</p>');
            return;
        }

        $suggestionPanel.html('<div class="shynh-seo-spinner"></div><p>Đang tìm gợi ý...</p>');

        $.post(shynhSeoLinking.ajaxUrl, {
            action: 'shynh_seo_suggest_links',
            nonce: shynhSeoLinking.nonce,
            post_id: shynhSeoLinking.postId,
            keyword: keyword
        }, function(response) {
            if (!response.success || !response.data.length) {
                $suggestionPanel.html('<p>Không tìm thấy bài viết nào có keyword liên quan.</p>');
                return;
            }

            let html = '<ul class="shynh-seo-link-list">';
            response.data.forEach(function(post) {
                html += [
                    '<li class="shynh-seo-linking-item">',
                        '<div class="info">',
                            '<strong>' + escapeHtml(post.title) + '</strong>',
                            '<span class="badge">' + escapeHtml(post.post_type || '') + '</span>',
                            '<div class="keyword">Keyword: <span>' + escapeHtml(post.keyword || '') + '</span></div>',
                        '</div>',
                        '<button type="button" class="button shynh-seo-copy-link-btn" data-url="' + escapeHtml(post.url) + '" data-title="' + escapeHtml(post.title) + '">Copy Link</button>',
                        '<button type="button" class="button shynh-seo-related-add" data-post="' + escapeHtml(JSON.stringify(post)) + '">Thêm</button>',
                    '</li>'
                ].join('');
            });
            html += '</ul>';
            $suggestionPanel.html(html);
        });
    }

    function fetchLinkedPosts() {
        if (!$linkedPostsPanel.length) {
            return;
        }

        $.post(shynhSeoLinking.ajaxUrl, {
            action: 'shynh_seo_get_linked_posts',
            nonce: shynhSeoLinking.nonce,
            post_id: shynhSeoLinking.postId
        }, function(response) {
            if (!response.success || !response.data.length) {
                $linkedPostsPanel.html('<p>Chưa có bài viết nào trỏ link tới bài này.</p>');
                return;
            }

            let html = '<ul class="shynh-seo-link-list">';
            response.data.forEach(function(post) {
                html += '<li class="shynh-seo-linking-item"><div class="info"><strong><a href="' + escapeHtml(post.url) + '" target="_blank">' + escapeHtml(post.title) + '</a></strong><span class="badge">' + escapeHtml(post.post_type || '') + '</span></div></li>';
            });
            html += '</ul>';
            $linkedPostsPanel.html(html);
        });
    }

    $(document).on('click', '.shynh-seo-related-add', function() {
        try {
            addInternalLink(JSON.parse($(this).attr('data-post') || '{}'));
        } catch (e) {}
    });

    $(document).on('click', '.shynh-seo-related-remove', function() {
        const index = parseInt($(this).closest('[data-index]').data('index'), 10);
        if (!Number.isNaN(index)) {
            state.internalItems.splice(index, 1);
            renderSelected();
        }
    });

    $(document).on('click', '.shynh-seo-copy-link-btn', function() {
        const htmlLink = '<a href="' + $(this).data('url') + '">' + $(this).data('title') + '</a>';
        navigator.clipboard.writeText(htmlLink);
        const $btn = $(this);
        const original = $btn.text();
        $btn.text('Đã copy!');
        setTimeout(function() {
            $btn.text(original);
        }, 1600);
    });

    $searchInput.on('input', function() {
        clearTimeout(state.internalSearchTimer);
        state.internalSearchTimer = setTimeout(fetchInternalSuggestions, 500);
    });

    $('#shynh-seo-related-search-btn').on('click', fetchInternalSuggestions);
    $taxFilter.on('change', fetchInternalSuggestions);
    $postTypeFilter.on('change', function() {
        renderFilters();
        fetchInternalSuggestions();
    });

    $keywordInput.on('input', function() {
        clearTimeout(state.keywordSearchTimer);
        state.keywordSearchTimer = setTimeout(fetchKeywordSuggestions, 800);
    });

    renderFilters();
    renderSelected();
    fetchInternalSuggestions();
    fetchKeywordSuggestions();
    fetchLinkedPosts();
});
