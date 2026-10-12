# Shynh SEO AI Handoff Notes

This document is the working handoff/spec for AI agents and developers continuing the Shynh SEO plugin.

Last major implemented scope:

- GEO/AEO validation for posts and terms.
- FAQ Builder with JSON-LD output.
- FAQ import from full content.
- Per-post and per-term GEO/AEO override.
- Schema support for post and term contexts.
- AI English SEO Sync scaffold for Polylang, Yoast, Shynh SEO, posts, and terms.
- AI Sync image-preservation workflow: original media blocks and featured image are preserved by default.
- Table of Contents block was added in the theme, not inside this plugin.

Required theme block reference:

- Before implementing any Shynh SEO feature that inspects, rewrites, syncs, scores, renders, extracts, or generates Gutenberg content, read `app/public/wp-content/themes/kenone/docs/AI_BLOCKS_REFERENCE.md`.
- The plugin must understand Kenone dynamic blocks, InnerBlocks, media attributes, render callbacks, frontend behavior, FAQ/schema-visible content, table of contents placement, and image preservation rules before changing content workflows.
- If a Shynh SEO feature requires a new theme block or changes an existing block contract, update `AI_BLOCKS_REFERENCE.md` in the same change set.

## Product Principle

Shynh SEO is not only a classic SEO meta plugin.

The current direction is:

- Classic SEO scoring and metadata.
- GEO: Generative Engine Optimization readiness.
- AEO: Answer Engine Optimization readiness.
- Schema and entity support.
- Human-reviewed medical-aesthetic content workflow.
- AI-assisted English localization workflow on top of Polylang and Yoast.

The plugin must not replace Polylang or Yoast. It works on top of them.

## Critical Non-Negotiable Rules

1. Do not hard-code business post types or taxonomies.
   Use public post types and public taxonomies dynamically.
   Current examples from the business are `post`, `page`, `dich-vu`, `tin-tuc`, `category`, `loai-dich-vu`, and `loai-tin-tuc`, but code must keep auto-detect behavior.

2. Yoast SEO output is opt-in only.
   `ai_sync_write_yoast` defaults to `false`.
   When it is false, do not read Yoast source fields into AI payload, do not ask AI to generate Yoast fields, and do not write `_yoast_wpseo_*` metadata.

3. Never auto-publish by default.
   AI English Sync must create or update Draft only unless a future explicit, reviewed publish mechanism is added.

4. AI English content must always go through a human review popup before draft creation.
   The popup must show important fields: focus keyword, SEO title, meta description, slug, H1, quick answer, content, terms/categories, images, and reviewer notes.

5. Source images must be preserved by default.
   The AI must not remove, replace, or rewrite source images. The plugin preserves source media blocks and copies the featured image unless an editor explicitly changes settings.

6. Image metadata must be review-first.
   AI can suggest English alt/title/caption, but metadata is written only when the editor checks an image in the popup or `ai_sync_auto_apply_image_metadata` is enabled.

7. Additional images are opt-in.
   Suggestions for new images require `ai_sync_suggest_additional_images`. AI-generated images require `ai_sync_allow_ai_generated_images`.

8. GEO/AEO and Schema must support posts and terms.
   Category and custom taxonomy terms are first-class SEO objects.

9. Schema should reflect visible content.
   FAQPage schema should come from FAQ Builder items that editors can see/manage, not from hidden raw JSON alone.

10. Medical-aesthetic content needs safety language and review signals.
   Avoid risky absolute claims like guaranteed results, permanent cure, no risk, 100% safe, etc.

11. Admin text must support Vietnamese user experience.
   Previous mojibake/font issues happened when labels were broken. Keep UTF-8 strings valid.

12. Every completed code task must be tested before handoff.
   Run the relevant syntax/build checks and smoke-test affected site URLs, including `http://she.local/` for local work. Fix HTTP redirects, PHP errors, blank pages, and visible broken behavior before reporting completion.

## Core Files

- `shynh-seo.php`
  Plugin header, version constant, bootstrap.

- `inc/class-seo-core.php`
  Module loader, default settings, shared helpers, post type/taxonomy coverage helpers.

- `inc/class-seo-settings.php`
  Admin settings tabs:
  General, Sitemap, Social, Robots, GEO/AEO, AI Sync, Schema.

- `inc/class-seo-analysis.php`
  SEO scoring and GEO/AEO scoring.

- `inc/class-seo-metabox.php`
  Post editor SEO metabox, schema fields, FAQ Builder, per-post GEO/AEO override.

- `inc/class-seo-taxonomy-meta.php`
  Term editor SEO fields, schema fields, FAQ Builder, per-term GEO/AEO override.

- `inc/class-seo-schema.php`
  JSON-LD output for posts and terms.

- `inc/class-seo-ai-english-sync.php`
  AI English SEO Sync workflow for post and term localization.

- `assets/js/seo-analysis.js`
  Admin live analysis, FAQ import, FAQ drag/drop behavior.

- `assets/js/ai-english-sync.js`
  AI Sync popup review and draft creation AJAX calls.

- `assets/css/admin.css`
  Admin UI styles for score items, FAQ Builder, AI Sync modal, settings.

## Default Settings Added

Defined in `Shynh_SEO_Core::get_default_settings()`.

### GEO/AEO

- `geo_aeo_enabled`: global GEO/AEO validator toggle.
- `geo_aeo_auto_include_post_types`: auto-include public post types.
- `geo_aeo_auto_include_taxonomies`: auto-include public taxonomies.
- `geo_aeo_post_types`: manual selected post types when auto mode is off.
- `geo_aeo_taxonomies`: manual selected taxonomies when auto mode is off.
- `geo_aeo_excluded_post_types`: excluded post types while auto mode is on.
- `geo_aeo_excluded_taxonomies`: excluded taxonomies while auto mode is on.
- `geo_aeo_min_score`: minimum score, default `80`.
- `geo_aeo_require_quick_answer`: require answer-first block.
- `geo_aeo_require_faq`: require FAQ coverage.
- `geo_aeo_require_entity_signals`: require entity/brand signals.
- `geo_aeo_require_schema`: require schema support.
- `geo_aeo_require_medical_disclaimer`: require safety/risk/consultation/aftercare language.
- `geo_aeo_require_review_status`: require reviewed status and reviewer.
- `geo_aeo_require_search_eligibility`: require index/snippet eligibility.
- `geo_aeo_require_experience_signals`: require experience/expert signal.
- `geo_aeo_require_intent_coverage`: require helpful intent coverage.
- `geo_aeo_require_media_support`: require meaningful image/video support.
- `geo_aeo_require_schema_visibility`: schema must match visible content.
- `geo_aeo_require_trust_signals`: require trust/contact/authority signals.
- `geo_aeo_target_entities`: custom entity list, one per line.

### Schema

- `schema_enabled`: global JSON-LD output.
- `schema_auto_detect_type`: detect schema type automatically.
- `schema_enable_faq`: output FAQPage schema from FAQ Builder.
- `schema_enable_service`: output Service schema.
- `schema_enable_medical_web_page`: output MedicalWebPage schema.
- `schema_enable_collection_page`: output CollectionPage for terms.
- `schema_require_human_review`: medical content should be reviewed before full schema confidence.
- `org_name`: organization/entity name.
- `org_logo`: organization logo.

### AI English SEO Sync

- `ai_sync_enabled`: global AI Sync toggle.
- `ai_sync_auto_include_post_types`: auto-include public post types.
- `ai_sync_auto_include_taxonomies`: auto-include public taxonomies.
- `ai_sync_post_types`: manual selected post types when auto mode is off.
- `ai_sync_taxonomies`: manual selected taxonomies when auto mode is off.
- `ai_sync_excluded_post_types`: excluded post types while auto mode is on.
- `ai_sync_excluded_taxonomies`: excluded taxonomies while auto mode is on.
- `ai_sync_openai_api_key`: OpenAI API key.
- `ai_sync_openai_model`: default model, default currently `gpt-5.4-mini`.
- `ai_sync_openai_model_economy`: economy model for short/low-risk objects, default `gpt-5.4-mini`.
- `ai_sync_openai_model_important`: stronger model for important content, default `gpt-5.4`.
- `ai_sync_target_language`: Polylang language code, default `en`.
- `ai_sync_target_locale`: content locale, default `en_US`.
- `ai_sync_write_yoast`: default `false`. Do not generate/write Yoast unless enabled.
- `ai_sync_write_shynh_seo`: default `true`.
- `ai_sync_translate_terms`: default `true`.
- `ai_sync_link_polylang`: default `true`.
- `ai_sync_auto_publish`: default `false`.
- `ai_sync_preserve_images`: preserve original content media blocks, default `true`.
- `ai_sync_copy_featured_image`: copy featured image to translated post, default `true`.
- `ai_sync_suggest_image_metadata`: ask AI for English alt/title/caption suggestions, default `true`.
- `ai_sync_auto_apply_image_metadata`: write image metadata automatically after review, default `false`.
- `ai_sync_suggest_additional_images`: allow AI to suggest new image opportunities, default `false`.
- `ai_sync_allow_ai_generated_images`: allow AI-generated image suggestions only when no site asset fits, default `false`.

## GEO vs AEO Definitions Used

### GEO

GEO means Generative Engine Optimization.

The goal is for content to be easy for AI search and generative answer systems to understand, cite, and summarize.

Current GEO signals include:

- Clear brand/entity mentions.
- Organization/entity metadata.
- Service or medical-aesthetic schema support.
- Trust signals such as reviewer, contact, organization, social profiles, authoritativeness.
- Search eligibility: not blocked by noindex/nosnippet when the page is meant to rank.
- Media support through images/videos with meaningful metadata.
- Visible content matching schema.

### AEO

AEO means Answer Engine Optimization.

The goal is for content to answer user questions directly and clearly.

Current AEO signals include:

- Quick Answer near the intro.
- FAQ-style questions and answers.
- Concise answer-first paragraphs.
- Intent coverage across headings and body content.
- Safety, risk, consultation, and aftercare wording for medical-aesthetic content.

## GEO/AEO Validator Behavior

The validator runs only when enabled globally or forced on at object level.

Object-level override:

- Post meta: `_shynh_seo_geo_aeo_mode`
- Term meta: `_shynh_seo_geo_aeo_mode`
- Values:
  - Empty: follow global settings.
  - `on`: force GEO/AEO for this object.
  - `off`: disable GEO/AEO for this object.

The score displays green/orange/red items and an overall AI Search score.

Rules currently checked:

- Quick Answer exists near the intro or in recognized answer-first content.
- FAQ-style questions exist in full content and/or FAQ Builder.
- Configured brand/entity signals are present.
- Schema support is configured.
- Safety/risk/consultation/aftercare language exists.
- Reviewer name and review status exist.
- Page is eligible for search snippets when appropriate.
- Experience/expert signals exist.
- Intent coverage is broad enough.
- Media support exists.
- Schema is consistent with visible content.
- Trust/contact/authority signals exist.
- Risky absolute claims are detected.

## Quick Answer Rule

Quick Answer should be a short, direct answer near the intro.

Important editorial guidance:

- It does not need to be a heading.
- It should not break heading hierarchy.
- It can be a short paragraph, list, or dedicated block near the top.
- The Table of Contents can include a "Direct Answer" anchor, but the content should still keep a clean heading order.

For future improvements, Quick Answer should be flexible:

- Detect answer-first paragraph after H1/intro.
- Detect a block class/anchor like `quick-answer`, `direct-answer`, or `answer`.
- Detect short ordered lists that directly answer the query.
- Avoid requiring one exact text label.

## FAQ Builder Rule

FAQ Builder exists because raw JSON is too hard for editors.

Current editor flow:

- Editors add question and answer rows.
- Plugin stores hidden JSON in `_shynh_seo_schema_faq`.
- Schema reads `_shynh_seo_schema_faq` and outputs FAQPage JSON-LD when enabled.
- Rows support drag/drop ordering.
- FAQ order should follow the natural user journey:
  1. Broad/basic questions.
  2. Eligibility or who it is for.
  3. Process/procedure.
  4. Safety/risk/recovery.
  5. Price/duration/result.
  6. Booking/consultation.

Important:

- FAQ import must scan the full content, not only a special FAQ block.
- Questions can appear in headings, strong text, paragraphs, lists, accordion blocks, or custom FAQ blocks.
- If an answer is not obvious, import the question with an empty answer so editor can fill it.
- FAQ schema should only include questions with actual answers.
- Nested FAQ hierarchy is not currently modeled in schema; keep FAQPage flat unless future UI adds parent/child editorial grouping.

## Schema Behavior

Schema output should be automatic when enabled and content qualifies.

Current intent:

- FAQPage: from FAQ Builder items.
- Service: for service/detail pages.
- MedicalWebPage: for medical-aesthetic content, especially when reviewed.
- CollectionPage: for term/category archives.
- Organization: from configured organization data.

Schema Type field:

- Allows editor to choose or override schema intent.
- If auto-detect is enabled, plugin may infer based on post type, taxonomy, content, and metadata.
- If a specific schema type is selected, it should guide JSON-LD output.

Schema must not invent facts not visible in content or configured metadata.

## AI English SEO Sync Workflow

File: `inc/class-seo-ai-english-sync.php`

Settings tab: `AI Sync`.

Admin JS: `assets/js/ai-english-sync.js`.

Workflow for a Vietnamese post:

1. Editor opens the Vietnamese post.
2. Plugin detects Polylang, Yoast, source language, and English translation status.
3. Editor clicks `Generate English Wrap-up`.
4. Editor chooses an AI model mode:
   - `Auto detect importance`.
   - `Default`.
   - `Important`.
   - `Economy`.
   - `Custom model`.
5. Plugin resolves the model:
   - Auto mode checks object type, service/page context, medical/service schema, length, images, assigned terms, and medical-aesthetic keywords.
   - Important content uses `ai_sync_openai_model_important`.
   - Short/simple content uses `ai_sync_openai_model_economy`.
   - Default content uses `ai_sync_openai_model`.
   - Custom mode uses the editor-entered model for that generation only.
6. Plugin collects source data:
   - Post title, slug, excerpt, content.
   - Plain text, headings, images.
   - Media blocks and featured image.
   - Image policy settings.
   - Shynh SEO meta.
   - Yoast meta only if `ai_sync_write_yoast` is enabled.
   - Assigned categories/terms and their translation status.
7. Plugin sends structured payload to OpenAI if API key is configured.
8. AI returns JSON with:
   - `title`
   - `slug`
   - `h1`
   - `excerpt`
   - `content`
   - `focus_keyword`
   - `seo_title`
   - `meta_description`
   - `quick_answer`
   - `faqs`
   - `image_suggestions`
   - `terms`
   - `reviewer_notes`
9. Popup opens for human review and shows selected/resolved model.
10. Editor can edit key fields in popup.
11. Editor clicks `Create English Draft`.
12. Plugin creates or updates English draft.
13. Plugin preserves source media blocks if AI output is missing them.
14. Plugin copies featured image when enabled.
15. Plugin applies image metadata only for checked suggestions or auto-apply setting.
16. Plugin links source and target with Polylang when enabled.
17. Plugin writes Shynh SEO metadata when enabled.
18. Plugin writes Yoast metadata only when `ai_sync_write_yoast` is enabled.

## Image Preservation Workflow

Default image behavior is conservative.

When translating a post:

- Keep original image/media blocks by default.
- Keep image/gallery/cover/media-text/video/audio/embed/file blocks.
- Copy source featured image to target draft when target has none.
- If Polylang has a translated attachment for the featured image, use that translated attachment.
- AI should write localized text around image positions, not recreate image HTML from scratch.
- If generated content lacks a source media block, plugin merges the missing media block back into the draft.

Image metadata behavior:

- AI may return `image_suggestions`.
- Each suggestion can include `source_id`, `source`, `alt`, `title`, `caption`, and `reason`.
- Popup shows editable alt/title/caption fields.
- Metadata is not written unless editor checks `Apply metadata` for that image or auto-apply is enabled.
- Be careful: attachment alt/title/caption can be global when the same media item is reused between languages.

Additional image behavior:

- AI may return `additional_image_suggestions` only when allowed by settings.
- Source priority is Media Library, related content, brand assets, then AI-generated image only if enabled.
- Do not auto-insert additional images without a future explicit editor approval flow.

Workflow for a term/category:

1. Editor opens term edit screen.
2. Plugin shows AI English SEO Sync box for supported taxonomies.
3. Plugin collects term name, slug, description, Shynh SEO meta, optional Yoast meta.
4. AI/popup review follows the same rules.
5. Plugin creates or updates target language term.
6. Plugin links term translations with Polylang when enabled.

## AI Prompt Rules

AI must:

- Localize, not translate word by word.
- Use natural American English.
- Preserve brand meaning.
- Preserve medical-aesthetic accuracy.
- Avoid unsupported medical claims.
- Include safety/risk/consultation/aftercare language where relevant.
- Create answer-first content for AEO.
- Keep heading hierarchy clean.
- Return JSON only.
- Return fields expected by the popup.
- Leave Yoast-specific fields empty when Yoast output is disabled.
- Preserve images and only suggest image metadata unless additional image settings are enabled.

## Yoast Integration Rule

Yoast is optional and opt-in.

When `ai_sync_write_yoast` is false:

- Do not read `_yoast_wpseo_focuskw`.
- Do not read `_yoast_wpseo_title`.
- Do not read `_yoast_wpseo_metadesc`.
- Do not ask AI to generate Yoast SEO data.
- Do not write `_yoast_wpseo_focuskw`.
- Do not write `_yoast_wpseo_title`.
- Do not write `_yoast_wpseo_metadesc`.

When true:

- The popup should show English focus keyword, SEO title, and meta description.
- On draft creation, write Yoast post or term meta.

## Polylang Integration Rule

Polylang is the translation linker.

For posts:

- Read language with `pll_get_post_language`.
- Find translation with `pll_get_post`.
- Set target language with `pll_set_post_language`.
- Save translation map with `pll_save_post_translations`.

For terms:

- Read language with `pll_get_term_language`.
- Find translation with `pll_get_term`.
- Set target language with `pll_set_term_language`.
- Save translation map with `pll_save_term_translations`.

Target language is configurable with `ai_sync_target_language`.

## Term/Category Support Rule

Terms must be treated as SEO content objects, not just post relationships.

Supported term data:

- Name.
- Slug.
- Description.
- Taxonomy.
- Shynh SEO meta.
- Optional Yoast meta.
- Schema FAQ.
- Review status.
- Polylang language and translation mapping.

When translating a post, assigned terms should be checked:

- If target language term exists, assign it to target post.
- If it does not exist and AI returned term payload, create/update the translated term.
- Do not hard-code `category`; use supported public taxonomies.

## Table of Contents Note

The Table of Contents block was added in the theme:

- `app/public/wp-content/themes/kenone/blocks/table-of-contents/build/block.json`
- `render.php` outputs `[toc]`.
- Existing theme TOC system still handles actual rendering.

SEO/AEO placement guidance:

- TOC should usually appear after the intro or after the direct answer.
- If the editor does not insert the TOC block, the old system behavior should remain.
- Do not force TOC before the answer-first section if it hurts AEO clarity.

## Data and Meta Keys

Post/term SEO:

- `_shynh_seo_focus_keyword`
- `_shynh_seo_title`
- `_shynh_seo_meta_desc`
- `_shynh_seo_canonical`
- `_shynh_seo_robots_index`
- `_shynh_seo_robots_follow`
- `_shynh_seo_geo_aeo_mode`
- `_shynh_seo_schema_type`
- `_shynh_seo_schema_faq`
- `_shynh_seo_schema_service_name`
- `_shynh_seo_schema_medical_reviewed`
- `_shynh_seo_schema_reviewed_by`
- `_shynh_seo_schema_reviewed_date`

Post-only:

- `_shynh_seo_primary_category`
- `_shynh_seo_breadcrumb_title`
- `_shynh_seo_cornerstone`
- `_shynh_seo_internal_links`
- `_shynh_seo_internal_link_index`
- `_shynh_seo_bold_internal_links`
- `_shynh_seo_heading_count`
- `_shynh_seo_heading_summary`

Scores:

- `_shynh_seo_score`
- `_shynh_seo_geo_aeo_score`
- `_shynh_seo_ai_search_score`

Yoast, only when enabled:

- `_yoast_wpseo_focuskw`
- `_yoast_wpseo_title`
- `_yoast_wpseo_metadesc`

## Known Follow-Up Work

- Add stronger JSON Schema validation for OpenAI response instead of simple JSON mode.
- Add model selection guidance in settings if the site standardizes a model list.
- Add preview rendering of generated English content inside popup.
- Add editable FAQ rows inside the AI Sync popup, not only content/SEO fields.
- Add editable image alt/caption/title suggestions and write them to translated media or post context.
- Add explicit "Publish after review" confirmation if auto-publish is ever enabled.
- Add better Quick Answer detection using block attributes/class names.
- Add automated tests or WP-CLI smoke tests for post and term translation flows.
- Add nonce/capability checks for any future REST endpoints.

## Verification Commands

Use these after edits:

```bash
php -l app/public/wp-content/plugins/shynh-seo/shynh-seo.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-core.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-settings.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-analysis.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-metabox.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-taxonomy-meta.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-schema.php
php -l app/public/wp-content/plugins/shynh-seo/inc/class-seo-ai-english-sync.php
node --check app/public/wp-content/plugins/shynh-seo/assets/js/seo-analysis.js
node --check app/public/wp-content/plugins/shynh-seo/assets/js/ai-english-sync.js
```

## Developer Style Notes

- Keep admin UI simple, premium, and editor-friendly.
- Avoid raw JSON input for editors when a builder UI can do the job.
- Do not create nested card-heavy admin layouts.
- Do not introduce unrelated refactors while changing SEO workflow logic.
- Keep all generated schema grounded in visible content and saved metadata.
- Keep default behavior conservative.
