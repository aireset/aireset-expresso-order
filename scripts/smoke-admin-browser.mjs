import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(scriptDir, '..');
const wpRoot = path.resolve(process.env.WP_PATH || path.join(pluginRoot, '..', '..', '..'));
const outputDir = path.resolve(process.env.EOP_SMOKE_OUTPUT || path.join(wpRoot, '..', 'output', 'eop-browser-smoke'));
const headless = process.env.EOP_SMOKE_HEADED !== '1';
const userId = Number.parseInt(process.env.EOP_SMOKE_USER_ID || '0', 10) || 0;
const onlyChecks = (process.env.EOP_SMOKE_ONLY || '')
  .split(',')
  .map((value) => value.trim())
  .filter(Boolean);

fs.mkdirSync(outputDir, { recursive: true });

const auth = readWordPressAuth();
const browser = await chromium.launch({ headless });
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 960 },
});

await context.addCookies(auth.cookies);

const page = await context.newPage();
const consoleErrors = [];
const pageErrors = [];

page.on('console', (message) => {
  if (message.type() === 'error') {
    consoleErrors.push(message.text());
  }
});

page.on('pageerror', (error) => {
  pageErrors.push({
    message: error.message,
    stack: error.stack || '',
  });
});

const checks = [
  {
    label: 'admin-shell',
    url: auth.adminUrl,
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Pedido Expresso'],
  },
  {
    label: 'admin-new-order',
    url: auth.views['new-order'] || withQuery(auth.adminUrl, { view: 'new-order' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Novo pedido'],
  },
  {
    label: 'admin-legacy-lazy-new-order-from-settings',
    url: withQuery(auth.views['settings-general-config'] || withQuery(auth.adminUrl, { view: 'settings-general-config' }), { eop_admin_legacy: '1' }),
    selectors: ['.eop-admin-spa'],
    text: ['Pedido Expresso'],
    interaction: async (currentPage) => {
      await currentPage.locator('[data-eop-view-target="new-order"]').click();
      await currentPage.locator('[data-eop-view="new-order"]').waitFor({ state: 'visible', timeout: 10000 });
      await currentPage.locator('#eop-product-search').waitFor({ state: 'attached', timeout: 10000 });
      await currentPage.waitForFunction(() => Boolean(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2), null, { timeout: 10000 });
      await currentPage.waitForFunction(() => document.querySelector('#eop-product-search.select2-hidden-accessible'), null, { timeout: 10000 });
    },
  },
  {
    label: 'admin-orders',
    url: auth.views.orders || withQuery(auth.adminUrl, { view: 'orders' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Pedidos'],
  },
  {
    label: 'public-new-order',
    url: auth.publicOrderUrl || auth.previewFrames['new-order'],
    selectors: ['[data-eop-view="new-order"]', '#eop-name'],
    text: ['Cliente'],
    interaction: async (currentPage) => {
      const nameField = currentPage.locator('#eop-name');
      if (!(await nameField.isVisible())) {
        await currentPage.locator('.eop-accordion__toggle').first().click();
      }
      await nameField.waitFor({ state: 'visible', timeout: 5000 });
      await currentPage.locator('#eop-name').fill('Smoke Test');
      await currentPage.locator('[data-eop-view-target="orders"]').click();
      await currentPage.locator('[data-eop-view="orders"]').waitFor({ state: 'visible', timeout: 5000 });
    },
  },
  {
    label: 'admin-settings-new-order-style',
    url: auth.views['settings-new-order-style'] || withQuery(auth.adminUrl, { view: 'settings-new-order-style' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Preview'],
  },
  {
    label: 'admin-settings-proposal-link-style',
    url: auth.views['settings-proposal-link-style'] || withQuery(auth.adminUrl, { view: 'settings-proposal-link-style' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Preview'],
  },
  {
    label: 'admin-settings-confirmation-preview',
    url: auth.views['settings-confirmation-preview'] || withQuery(auth.adminUrl, { view: 'settings-confirmation-preview' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Preview'],
  },
  {
    label: 'admin-settings-confirmation-documents',
    url: auth.views['settings-confirmation-documents'] || withQuery(auth.adminUrl, { view: 'settings-confirmation-documents' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Documentos'],
  },
  {
    label: 'admin-settings-upload-products-preview',
    url: auth.views['settings-confirmation-upload-products-preview'] || withQuery(auth.adminUrl, { view: 'settings-confirmation-upload-products-preview' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Preview'],
  },
  {
    label: 'admin-pdf',
    url: auth.views.pdf || withQuery(auth.adminUrl, { view: 'pdf' }),
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['PDF'],
  },
  {
    label: 'admin-legacy-fallback',
    url: withQuery(auth.adminUrl, { eop_admin_legacy: '1' }),
    selectors: ['.eop-admin-spa', '.eop-pdv'],
    text: ['Pedido Expresso'],
  },
  {
    label: 'admin-spa-default',
    url: auth.adminUrl,
    selectors: ['#eop-admin-app', '.eop-admin-spa--react'],
    text: ['Pedido Expresso'],
  },
].filter((check) => onlyChecks.length === 0 || onlyChecks.includes(check.label));

const results = [];

for (const check of checks) {
  if (!check.url) {
    results.push({ label: check.label, ok: false, error: 'URL indisponivel' });
    continue;
  }

  results.push(await runCheck(page, check));
}

await browser.close();

const failed = results.filter((result) => !result.ok);
const report = {
  generatedAt: new Date().toISOString(),
  wpRoot,
  pluginRoot,
  siteUrl: auth.siteUrl,
  adminUrl: auth.adminUrl,
  headless,
  results,
  consoleErrors: consoleErrors.slice(-20),
  pageErrors,
};

const reportPath = path.join(outputDir, 'report.json');
fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`);

console.log(JSON.stringify(report, null, 2));

if (failed.length > 0 || pageErrors.length > 0) {
  console.error(`Smoke browser falhou. Relatorio: ${reportPath}`);
  process.exit(1);
}

console.log(`Smoke browser concluido sem falhas. Relatorio: ${reportPath}`);

async function runCheck(currentPage, check) {
  const startedAt = Date.now();
  const screenshotPath = path.join(outputDir, `${safeFileName(check.label)}.png`);
  const htmlPath = path.join(outputDir, `${safeFileName(check.label)}.html`);

  try {
    const response = await currentPage.goto(check.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await currentPage.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});

    const finalUrl = currentPage.url();
    const html = await currentPage.content();
    fs.writeFileSync(htmlPath, html);
    const title = await currentPage.title();
    const status = response ? response.status() : 0;
    const selectorResults = [];
    const textResults = [];
    const adminVisibleViews = await currentPage.locator('.eop-admin-spa .eop-pdv-view').evaluateAll((views) => views
      .filter((view) => {
        const style = window.getComputedStyle(view);

        return style.display !== 'none' && style.visibility !== 'hidden' && view.getClientRects().length > 0;
      })
      .map((view) => view.getAttribute('data-eop-view') || '')
      .filter(Boolean));
    const reactShellBox = await currentPage.locator('.eop-admin-spa--react').first().boundingBox().catch(() => null);
    const visibleWpAdminNotices = await currentPage.locator(
      'body.eop-admin-react-shell #wpbody-content .notice, body.eop-admin-react-shell #wpbody-content div.notice, body.eop-admin-react-shell #wpbody-content .update-nag, body.eop-admin-react-shell #wpbody-content .updated, body.eop-admin-react-shell #wpbody-content .error, body.eop-admin-react-shell #wpbody-content .fs-notice'
    ).evaluateAll((notices) => notices.filter((notice) => {
      const style = window.getComputedStyle(notice);

      return style.display !== 'none' && style.visibility !== 'hidden' && notice.getClientRects().length > 0;
    }).length).catch(() => 0);

    for (const selector of check.selectors || []) {
      const count = await currentPage.locator(selector).count();
      selectorResults.push({ selector, count });
    }

    const lowerHtml = html.toLowerCase();
    for (const text of check.text || []) {
      textResults.push({ text, found: lowerHtml.includes(String(text).toLowerCase()) });
    }

    if (check.interaction) {
      await check.interaction(currentPage);
    }

    await currentPage.screenshot({ path: screenshotPath, fullPage: true });

    const redirectedToLogin = finalUrl.includes('wp-login.php');
    const hasPhpFatal = /fatal error|critical error|erro crítico|parse error/i.test(html);
    const hasSelector = selectorResults.length === 0 || selectorResults.some((entry) => entry.count > 0);
    const hasText = textResults.length === 0 || textResults.some((entry) => entry.found);
    const hasSingleAdminView = adminVisibleViews.length === 0 || adminVisibleViews.length === 1;
    const hasReactShell = selectorResults.some((entry) => '.eop-admin-spa--react' === entry.selector && entry.count > 0);

    if (hasReactShell) {
      await currentPage.getByText('Carregando contratos REST e dados da view.').waitFor({ state: 'hidden', timeout: 25000 }).catch(() => {});
    }

    const viewLoadingVisible = hasReactShell
      ? await currentPage.getByText('Carregando contratos REST e dados da view.').isVisible().catch(() => false)
      : false;
    const reactShellStartsInViewport = !hasReactShell || !reactShellBox || reactShellBox.x < 240;
    const hasNoVisibleWpAdminNotices = !hasReactShell || visibleWpAdminNotices === 0;
    const ok = status < 400 && !redirectedToLogin && !hasPhpFatal && hasSelector && hasText && hasSingleAdminView && reactShellStartsInViewport && hasNoVisibleWpAdminNotices && !viewLoadingVisible;

    return {
      label: check.label,
      ok,
      status,
      title,
      finalUrl,
      durationMs: Date.now() - startedAt,
      selectors: selectorResults,
      text: textResults,
      adminVisibleViews,
      reactShellBox,
      visibleWpAdminNotices,
      viewLoadingVisible,
      screenshot: screenshotPath,
      html: htmlPath,
      redirectedToLogin,
      hasPhpFatal,
      hasSingleAdminView,
      reactShellStartsInViewport,
      hasNoVisibleWpAdminNotices,
    };
  } catch (error) {
    await currentPage.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});

    return {
      label: check.label,
      ok: false,
      error: error.message,
      durationMs: Date.now() - startedAt,
      screenshot: screenshotPath,
      html: fs.existsSync(htmlPath) ? htmlPath : '',
    };
  }
}

function readWordPressAuth() {
  const wpCli = resolveWpCli();
  const code = `
$user_id = (int) getenv('EOP_SMOKE_USER_ID');
if ($user_id <= 0) {
    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
    $user_id = empty($admins) ? 0 : (int) $admins[0];
}
if ($user_id <= 0 || !get_user_by('id', $user_id)) {
    fwrite(STDERR, "Nenhum administrador encontrado para smoke browser.\\n");
    exit(1);
}
wp_set_current_user($user_id);
$expiration = time() + DAY_IN_SECONDS;
$host = parse_url(home_url(), PHP_URL_HOST);
$secure = 'https' === parse_url(admin_url(), PHP_URL_SCHEME);
$auth_cookie_name = $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
$auth_scheme = $secure ? 'secure_auth' : 'auth';
$cookie_paths = array_values(array_unique(array_filter(array(ADMIN_COOKIE_PATH, COOKIEPATH, SITECOOKIEPATH, '/'))));
$cookies = array();
foreach ($cookie_paths as $cookie_path) {
    $cookies[] = array(
        'name' => $auth_cookie_name,
        'value' => wp_generate_auth_cookie($user_id, $expiration, $auth_scheme),
        'domain' => $host,
        'path' => $cookie_path,
        'secure' => $secure,
        'httpOnly' => true,
        'sameSite' => 'Lax',
        'expires' => $expiration,
    );
}
$cookies[] = array(
    'name' => LOGGED_IN_COOKIE,
    'value' => wp_generate_auth_cookie($user_id, $expiration, 'logged_in'),
    'domain' => $host,
    'path' => COOKIEPATH ?: '/',
    'secure' => $secure,
    'httpOnly' => true,
    'sameSite' => 'Lax',
    'expires' => $expiration,
);
$views = class_exists('EOP_Admin_Page') ? EOP_Admin_Page::get_view_urls() : array();
$preview_frames = array();
if (class_exists('EOP_Admin_Page')) {
    foreach (array('new-order', 'orders', 'settings-proposal-link-style', 'settings-new-order-style', 'settings-confirmation-preview', 'settings-confirmation-upload-products-preview') as $view) {
        $preview_frames[$view] = EOP_Admin_Page::get_preview_frame_url($view);
    }
}
echo wp_json_encode(array(
    'userId' => $user_id,
    'siteUrl' => home_url('/'),
    'adminUrl' => admin_url('admin.php?page=eop-pedido-expresso'),
    'publicOrderUrl' => home_url('/pedido-expresso/'),
    'views' => $views,
    'previewFrames' => $preview_frames,
    'cookies' => $cookies,
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
`;

  const args = [
    ...wpCli.args,
    `--path=${wpRoot}`,
    '--skip-themes',
    'eval',
    code,
  ];
  const result = spawnSync(wpCli.command, args, {
    cwd: path.resolve(wpRoot, '..'),
    encoding: 'utf8',
    env: {
      ...process.env,
      EOP_SMOKE_USER_ID: String(userId),
    },
  });

  if (result.status !== 0) {
    throw new Error(`Falha ao gerar cookies via WP-CLI.\nSTDOUT:\n${result.stdout}\nSTDERR:\n${result.stderr}`);
  }

  const jsonLine = result.stdout
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .findLast((line) => line.startsWith('{'));

  if (!jsonLine) {
    throw new Error(`WP-CLI nao retornou JSON de autenticacao.\nSTDOUT:\n${result.stdout}`);
  }

  const parsed = JSON.parse(jsonLine);
  parsed.views = parsed.views || {};
  parsed.previewFrames = parsed.previewFrames || {};

  return parsed;
}

function resolveWpCli() {
  if (process.env.WP_CLI_BIN) {
    return { command: process.env.WP_CLI_BIN, args: [] };
  }

  const localPhar = path.resolve(wpRoot, '..', 'tools', 'wp-cli.phar');
  if (fs.existsSync(localPhar)) {
    return { command: 'php', args: [localPhar] };
  }

  return { command: 'wp', args: [] };
}

function withQuery(url, params) {
  const next = new URL(url);
  for (const [key, value] of Object.entries(params)) {
    next.searchParams.set(key, value);
  }
  return next.toString();
}

function safeFileName(value) {
  return String(value).replace(/[^a-z0-9._-]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase();
}
