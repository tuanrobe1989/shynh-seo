<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">

	<xsl:output method="html" encoding="UTF-8" indent="yes" />

	<xsl:template match="/">
		<html lang="en">
			<head>
				<meta charset="UTF-8" />
				<meta name="viewport" content="width=device-width, initial-scale=1" />
				<title>Shynh SEO Sitemap</title>
				<style>
					:root {
						color-scheme: light;
						--bg: #f6f3ec;
						--surface: #fffdf8;
						--surface-strong: #171512;
						--text: #171512;
						--muted: #726b60;
						--line: rgba(23, 21, 18, 0.12);
						--accent: #b58a3a;
						--accent-soft: rgba(181, 138, 58, 0.14);
						--shadow: 0 24px 70px rgba(23, 21, 18, 0.10);
					}

					* {
						box-sizing: border-box;
					}

					body {
						margin: 0;
						font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
						color: var(--text);
						background:
							linear-gradient(135deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0) 34%),
							linear-gradient(180deg, var(--bg), #ffffff 72%);
						min-height: 100vh;
					}

					a {
						color: inherit;
						text-decoration: none;
					}

					a:hover {
						color: var(--accent);
					}

					.wrap {
						width: min(1180px, calc(100% - 40px));
						margin: 0 auto;
						padding: 42px 0 56px;
					}

					.topbar {
						display: flex;
						justify-content: space-between;
						gap: 16px;
						align-items: center;
						margin-bottom: 18px;
					}

					.nav-actions {
						display: flex;
						flex-wrap: wrap;
						gap: 10px;
						align-items: center;
					}

					.nav-button {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						min-height: 38px;
						padding: 0 14px;
						border: 1px solid var(--line);
						border-radius: 999px;
						background: rgba(255, 253, 248, 0.82);
						color: var(--text);
						box-shadow: 0 10px 24px rgba(23, 21, 18, 0.06);
						cursor: pointer;
						font: inherit;
						font-size: 13px;
						font-weight: 800;
						transition: color 180ms ease, border-color 180ms ease, background 180ms ease, transform 180ms ease;
					}

					.nav-button:hover {
						border-color: rgba(181, 138, 58, 0.45);
						background: var(--accent-soft);
						color: #6e4f16;
						transform: translateY(-1px);
					}

					.nav-button:focus-visible {
						outline: 3px solid rgba(181, 138, 58, 0.28);
						outline-offset: 2px;
					}

					.nav-button--primary {
						border-color: rgba(181, 138, 58, 0.38);
						background: var(--surface-strong);
						color: #fffaf0;
					}

					.nav-button--primary:hover {
						background: #2a251e;
						color: #fffaf0;
					}

					.nav-label {
						color: var(--muted);
						font-size: 12px;
						font-weight: 800;
						letter-spacing: 0.08em;
						text-transform: uppercase;
					}

					.header {
						display: grid;
						grid-template-columns: minmax(0, 1fr) auto;
						gap: 24px;
						align-items: end;
						padding: 34px;
						border: 1px solid var(--line);
						border-radius: 8px;
						background: rgba(255, 253, 248, 0.86);
						box-shadow: var(--shadow);
					}

					.kicker {
						display: inline-flex;
						width: fit-content;
						align-items: center;
						gap: 9px;
						margin: 0 0 14px;
						padding: 7px 11px;
						border: 1px solid rgba(181, 138, 58, 0.28);
						border-radius: 999px;
						background: var(--accent-soft);
						color: #6e4f16;
						font-size: 12px;
						font-weight: 700;
						letter-spacing: 0.08em;
						text-transform: uppercase;
					}

					.kicker::before {
						content: "";
						width: 7px;
						height: 7px;
						border-radius: 999px;
						background: var(--accent);
					}

					h1 {
						margin: 0;
						font-family: Georgia, "Times New Roman", serif;
						font-size: clamp(34px, 5vw, 62px);
						line-height: 1.02;
						font-weight: 500;
						letter-spacing: 0;
					}

					.lead {
						max-width: 68ch;
						margin: 16px 0 0;
						color: var(--muted);
						font-size: 16px;
						line-height: 1.75;
					}

					.stat {
						min-width: 150px;
						padding: 18px 20px;
						border-radius: 8px;
						background: var(--surface-strong);
						color: #fffaf0;
						text-align: right;
					}

					.stat strong {
						display: block;
						font-size: 34px;
						line-height: 1;
						font-weight: 700;
					}

					.stat span {
						display: block;
						margin-top: 8px;
						color: rgba(255, 250, 240, 0.72);
						font-size: 12px;
						font-weight: 700;
						letter-spacing: 0.08em;
						text-transform: uppercase;
					}

					.panel {
						margin-top: 26px;
						overflow: hidden;
						border: 1px solid var(--line);
						border-radius: 8px;
						background: var(--surface);
						box-shadow: 0 18px 46px rgba(23, 21, 18, 0.06);
					}

					table {
						width: 100%;
						border-collapse: collapse;
					}

					th {
						padding: 16px 22px;
						border-bottom: 1px solid var(--line);
						background: rgba(23, 21, 18, 0.035);
						color: var(--muted);
						font-size: 12px;
						font-weight: 800;
						letter-spacing: 0.08em;
						text-align: left;
						text-transform: uppercase;
						white-space: nowrap;
					}

					td {
						padding: 17px 22px;
						border-bottom: 1px solid rgba(23, 21, 18, 0.08);
						color: var(--text);
						font-size: 14px;
						line-height: 1.55;
						vertical-align: middle;
					}

					tr:last-child td {
						border-bottom: 0;
					}

					tbody tr:hover {
						background: rgba(181, 138, 58, 0.07);
					}

					.url {
						display: block;
						max-width: 760px;
						overflow: hidden;
						color: var(--text);
						font-weight: 700;
						text-overflow: ellipsis;
						white-space: nowrap;
					}

					.meta {
						color: var(--muted);
						font-variant-numeric: tabular-nums;
						white-space: nowrap;
					}

					.badge {
						display: inline-flex;
						align-items: center;
						justify-content: center;
						min-width: 58px;
						padding: 6px 10px;
						border-radius: 999px;
						background: rgba(23, 21, 18, 0.06);
						color: var(--muted);
						font-size: 12px;
						font-weight: 800;
						text-transform: capitalize;
					}

					.footer {
						display: flex;
						justify-content: space-between;
						gap: 18px;
						margin-top: 18px;
						color: var(--muted);
						font-size: 13px;
						line-height: 1.6;
					}

					.footer strong {
						color: var(--text);
					}

					@media (max-width: 760px) {
						.wrap {
							width: min(100% - 24px, 1180px);
							padding-top: 18px;
						}

						.header {
							grid-template-columns: 1fr;
							padding: 24px;
						}

						.stat {
							text-align: left;
						}

						table,
						thead,
						tbody,
						tr,
						th,
						td {
							display: block;
						}

						thead {
							display: none;
						}

						tr {
							padding: 14px 0;
							border-bottom: 1px solid var(--line);
						}

						tr:last-child {
							border-bottom: 0;
						}

						td {
							padding: 7px 18px;
							border-bottom: 0;
						}

						td::before {
							content: attr(data-label);
							display: block;
							margin-bottom: 4px;
							color: var(--muted);
							font-size: 11px;
							font-weight: 800;
							letter-spacing: 0.08em;
							text-transform: uppercase;
						}

						.url {
							white-space: normal;
						}

						.footer {
							display: block;
						}

						.topbar {
							display: block;
						}

						.nav-actions {
							margin-top: 12px;
						}
					}
				</style>
			</head>
			<body>
				<main class="wrap">
					<nav class="topbar" aria-label="Sitemap navigation">
						<div class="nav-label">Điều hướng</div>
						<div class="nav-actions">
							<button class="nav-button nav-button--primary" type="button" onclick="if (window.history.length &gt; 1) {{ window.history.back(); }} else {{ window.location.href = '/'; }}">Quay lại</button>
							<a class="nav-button" href="/">Về website</a>
							<a class="nav-button" href="/sitemap.xml">Sitemap Index</a>
						</div>
					</nav>
					<xsl:choose>
						<xsl:when test="s:sitemapindex">
							<xsl:call-template name="sitemap-index" />
						</xsl:when>
						<xsl:otherwise>
							<xsl:call-template name="url-set" />
						</xsl:otherwise>
					</xsl:choose>
				</main>
			</body>
		</html>
	</xsl:template>

	<xsl:template name="sitemap-index">
		<section class="header">
			<div>
				<p class="kicker">Shynh SEO</p>
				<h1>Sitemap Index</h1>
				<p class="lead">This index lists every generated sub-sitemap. Search engines can use the XML directly, while this view helps editors audit coverage quickly.</p>
			</div>
			<div class="stat">
				<strong><xsl:value-of select="count(s:sitemapindex/s:sitemap)" /></strong>
				<span>Sitemaps</span>
			</div>
		</section>

		<section class="panel">
			<table>
				<thead>
					<tr>
						<th>Sitemap</th>
						<th>Last modified</th>
					</tr>
				</thead>
				<tbody>
					<xsl:for-each select="s:sitemapindex/s:sitemap">
						<tr>
							<td data-label="Sitemap">
								<a class="url" href="{s:loc}">
									<xsl:value-of select="s:loc" />
								</a>
							</td>
							<td class="meta" data-label="Last modified">
								<xsl:choose>
									<xsl:when test="s:lastmod"><xsl:value-of select="s:lastmod" /></xsl:when>
									<xsl:otherwise>Not provided</xsl:otherwise>
								</xsl:choose>
							</td>
						</tr>
					</xsl:for-each>
				</tbody>
			</table>
		</section>

		<div class="footer">
			<p><strong>Format:</strong> XML sitemap index with browser XSL presentation.</p>
			<p><strong>Generated by:</strong> Shynh SEO</p>
		</div>
	</xsl:template>

	<xsl:template name="url-set">
		<section class="header">
			<div>
				<p class="kicker">Shynh SEO</p>
				<h1>URL Sitemap</h1>
				<p class="lead">Indexable URLs from this sitemap are listed below with smart crawl hints based on freshness, update patterns and content importance.</p>
			</div>
			<div class="stat">
				<strong><xsl:value-of select="count(s:urlset/s:url)" /></strong>
				<span>URLs</span>
			</div>
		</section>

		<section class="panel">
			<table>
				<thead>
					<tr>
						<th>URL</th>
						<th>Last modified</th>
						<th>Smart frequency</th>
						<th>Smart priority</th>
					</tr>
				</thead>
				<tbody>
					<xsl:for-each select="s:urlset/s:url">
						<tr>
							<td data-label="URL">
								<a class="url" href="{s:loc}">
									<xsl:value-of select="s:loc" />
								</a>
							</td>
							<td class="meta" data-label="Last modified">
								<xsl:choose>
									<xsl:when test="s:lastmod"><xsl:value-of select="s:lastmod" /></xsl:when>
									<xsl:otherwise>Not provided</xsl:otherwise>
								</xsl:choose>
							</td>
							<td data-label="Smart frequency">
								<span class="badge">
									<xsl:choose>
										<xsl:when test="s:changefreq"><xsl:value-of select="s:changefreq" /></xsl:when>
										<xsl:otherwise>Default</xsl:otherwise>
									</xsl:choose>
								</span>
							</td>
							<td class="meta" data-label="Smart priority">
								<xsl:choose>
									<xsl:when test="s:priority"><xsl:value-of select="s:priority" /></xsl:when>
									<xsl:otherwise>Default</xsl:otherwise>
								</xsl:choose>
							</td>
						</tr>
					</xsl:for-each>
				</tbody>
			</table>
		</section>

		<div class="footer">
			<p><strong>Format:</strong> XML urlset with browser XSL presentation.</p>
			<p><strong>Generated by:</strong> Shynh SEO</p>
		</div>
	</xsl:template>
</xsl:stylesheet>
