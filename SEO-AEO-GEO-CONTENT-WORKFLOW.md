# SEO, AEO, GEO Content Workflow

Use this workflow for Shynh Super Luxury service pages, posts, and term landing pages.

## 1. Pre-Edit Audit

- Back up the target `wp_posts` row and target `wp_postmeta` rows before changing content.
- Read the live public page with `Accept: text/markdown` when possible.
- Read the database content if admin access is not available.
- List every existing image ID, URL, alt, caption, and featured image.
- Keep the existing page layout and images unless the user explicitly approves a redesign.
- Normalize line endings before matching or rewriting Gutenberg content. WordPress content may use CRLF.

## 2. Answer-First Opening

- The first visible content after H1 must answer the best customer question:
  - What problem is this page solving?
  - Who is it for?
  - What result should the customer understand immediately?
  - What safety or consultation condition matters?
- Put the main entity and benefit in `<strong>`.
- Use `<em>` for caveats, eligibility notes, result variability, or medical-aesthetic safety wording.
- Keep the opening human-first. Do not start with generic brand history if the customer still does not know what problem is solved.

## 3. Content Formula

- H1: one clear service/topic name.
- Opening paragraph: direct answer to the strongest customer intent.
- Quick Answer: 60 to 100 words, near the top, optimized for AEO and machine understanding.
- Core explanation: what it is, how it works, who it fits, expected timeline, aftercare, risks, and consultation.
- Entity signals: include brand, service, technology, ingredient, manufacturer, technique, reviewer, and location/context when visible and true.
- Use `<strong>` for important keywords/entities and `<em>` for nuanced notes. Do not bold every keyword.

## 4. FAQ Rule

- If creating multiple FAQ items in a row, use the theme FAQ block `kp/faq`.
- Do not build long FAQ sections from repeated loose `core/heading` and `core/paragraph` blocks unless the user requests plain article formatting.
- If Shynh SEO outputs FAQ schema from `_shynh_seo_schema_faq`, set the FAQ block `enableSchema` to `false` to avoid duplicate FAQPage JSON-LD.
- FAQ schema must match visible FAQ content. No hidden-only FAQ schema.
- Keep FAQ answers concise, specific, and safe for medical-aesthetic content.

## 5. Shynh SEO Metadata

- Update `_shynh_seo_focus_keyword`, `_shynh_seo_title`, `_shynh_seo_meta_desc`, `_shynh_seo_breadcrumb_title`.
- Turn on `_shynh_seo_geo_aeo_mode` for pages that need GEO/AEO validation.
- Use `_shynh_seo_schema_type` based on visible intent. For medical-aesthetic services, prefer `MedicalWebPage` when supported.
- Fill `_shynh_seo_schema_service_name` for service pages.
- Fill reviewer fields when medical-aesthetic content has been reviewed.
- Keep robots index/follow eligible unless the page intentionally should not rank.

## 6. Medical-Aesthetic Safety

- Do not publish unverified regulatory claims such as FDA approval unless there is a reliable source for the exact market and product.
- Avoid absolute claims: guaranteed, no risk, 100% safe, permanent cure, always effective.
- Include consultation, contraindication, aftercare, and result variability language.
- Mention risks plainly when relevant: swelling, redness, bruising, irritation, or unwanted reactions.

## 7. Image Preservation

- Preserve existing media blocks and featured image by default.
- Improve missing alt text using natural, descriptive Vietnamese.
- Captions should describe the visible image or context, not promise guaranteed results.
- Do not add new images unless the user asks.

## 8. Verification

- Verify database content after update. Do this sequentially after the update, not in parallel with the update.
- Verify public Markdown includes all newly added sections.
- Verify HTML contains the expected meta title, meta description, Open Graph, and JSON-LD.
- Verify FAQ appears visibly when FAQ schema exists.
- Count key section strings to ensure they appear once and are not duplicated.
- Confirm the page returns HTTP 200.

## 9. Common Pitfalls To Avoid

- Do not let schema mention content that users cannot see.
- Do not create duplicate FAQPage schema from both `kp/faq` and Shynh SEO.
- Do not rely on terminal output when the command is using the wrong character set.
- Do not run update and verification in parallel for the same object.
- Do not leave temporary update scripts in the repository after the change is complete.
