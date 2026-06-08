import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(scriptDir, '..');
const wpRoot = path.resolve(process.env.WP_PATH || path.join(pluginRoot, '..', '..', '..'));
const outputDir = path.resolve(process.env.EOP_PERF_OUTPUT || path.join(wpRoot, '..', 'output', 'eop-performance-baseline'));
const headless = process.env.EOP_PERF_HEADED !== '1';
const runs = Math.max(1, Number.parseInt(process.env.EOP_PERF_RUNS || '3', 10) || 3);
const userId = Number.parseInt(process.env.EOP_SMOKE_USER_ID || process.env.EOP_PERF_USER_ID || '0', 10) || 0;
const onlyTargets = (process.env.EOP_PERF_ONLY || '')
  .split(',')
  .map((value) => value.trim())
  .filter(Boolean);

fs.mkdirSync(outputDir, { recursive: true });

const auth = readWordPressAuth();
const targets = [
  {
    label: 'legacy-shell',
    url: auth.views['settings-general-config'] || withQuery(auth.adminUrl, { view: 'settings-general-config' }),
    selector: '.eop-admin-spa',
  },
  {
    label: 'legacy-new-order',
    url: auth.views['new-order'] || withQuery(auth.adminUrl, { view: 'new-order' }),
    selector: '[data-eop-view="new-order"]',
  },
  {
    label: 'legacy-orders',
    url: auth.views.orders || withQuery(auth.adminUrl, { view: 'orders' }),
    selector: '[data-eop-view="orders"]',
  },
  {
    label: 'legacy-pdf',
    url: auth.views.pdf || withQuery(auth.adminUrl, { view: 'pdf', pdf_tab: 'display' }),
    selector: '.eop-pdf-admin',
  },
  {
    label: 'spa-feature-flag',
    url: withQuery(auth.adminUrl, { eop_admin_spa: '1' }),
    selector: '#eop-admin-app',
  },
  {
    label: 'public-new-order',
    url: auth.publicOrderUrl,
    selector: '[data-eop-view="new-order"]',
  },
].filter((target) => onlyTargets.length === 0 || onlyTargets.includes(target.label));

const browser = await chromium.launch({ headless });
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1440, height: 960 },
});

await context.addCookies(auth.cookies);

const results = [];
for (const target of targets) {
  results.push(await measureTarget(context, target));
}

await browser.close();

const report = {
  generatedAt: new Date().toISOString(),
  pluginVersion: auth.pluginVersion,
  wpRoot,
  pluginRoot,
  siteUrl: auth.siteUrl,
  headless,
  runs,
  results,
};

const reportPath = path.join(outputDir, 'baseline.json');
const markdownPath = path.join(outputDir, 'baseline.md');

fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`);
fs.writeFileSync(markdownPath, renderMarkdown(report));

console.log(JSON.stringify(report, null, 2));
console.log(`Baseline de performance gravado em: ${reportPath}`);
console.log(`Resumo Markdown gravado em: ${markdownPath}`);

async function measureTarget(context, target) {
  const samples = [];

  for (let index = 0; index < runs; index += 1) {
    const page = await context.newPage();
    const startedAt = Date.now();

    try {
      const response = await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.locator(target.selector).first().waitFor({ state: 'attached', timeout: 15000 }).catch(() => {});
      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});

      const metrics = await page.evaluate(() => {
        const nav = performance.getEntriesByType('navigation')[0];
        const resources = performance.getEntriesByType('resource');
        const scripts = resources.filter((entry) => entry.initiatorType === 'script');
        const styles = resources.filter((entry) => entry.initiatorType === 'link' || entry.name.match(/\.css(?:\?|$)/));
        const transferSize = resources.reduce((total, entry) => total + (entry.transferSize || 0), 0);

        return {
          domContentLoadedMs: nav ? Math.round(nav.domContentLoadedEventEnd) : 0,
          loadMs: nav ? Math.round(nav.loadEventEnd) : 0,
          responseEndMs: nav ? Math.round(nav.responseEnd) : 0,
          resourceCount: resources.length,
          scriptCount: scripts.length,
          styleCount: styles.length,
          transferKB: Math.round((transferSize / 1024) * 100) / 100,
          bodyTextLength: document.body ? document.body.innerText.length : 0,
        };
      });

      samples.push({
        ok: response ? response.status() < 400 : true,
        status: response ? response.status() : 0,
        durationMs: Date.now() - startedAt,
        finalUrl: page.url(),
        ...metrics,
      });
    } catch (error) {
      samples.push({
        ok: false,
        status: 0,
        durationMs: Date.now() - startedAt,
        error: error.message,
      });
    } finally {
      await page.close().catch(() => {});
    }
  }

  return {
    label: target.label,
    url: target.url,
    selector: target.selector,
    samples,
    summary: summarizeSamples(samples),
  };
}

function summarizeSamples(samples) {
  const successful = samples.filter((sample) => sample.ok);
  const source = successful.length > 0 ? successful : samples;

  return {
    ok: samples.every((sample) => sample.ok),
    runs: samples.length,
    successfulRuns: successful.length,
    durationMs: summarizeMetric(source, 'durationMs'),
    domContentLoadedMs: summarizeMetric(source, 'domContentLoadedMs'),
    loadMs: summarizeMetric(source, 'loadMs'),
    responseEndMs: summarizeMetric(source, 'responseEndMs'),
    resourceCount: summarizeMetric(source, 'resourceCount'),
    scriptCount: summarizeMetric(source, 'scriptCount'),
    styleCount: summarizeMetric(source, 'styleCount'),
    transferKB: summarizeMetric(source, 'transferKB'),
  };
}

function summarizeMetric(samples, key) {
  const values = samples
    .map((sample) => Number(sample[key]))
    .filter((value) => Number.isFinite(value))
    .sort((a, b) => a - b);

  if (values.length === 0) {
    return null;
  }

  const total = values.reduce((sum, value) => sum + value, 0);

  return {
    min: values[0],
    avg: Math.round((total / values.length) * 100) / 100,
    max: values[values.length - 1],
  };
}

function renderMarkdown(report) {
  const lines = [
    '# Baseline de Performance do Admin',
    '',
    `- Gerado em: ${report.generatedAt}`,
    `- Versao do plugin: ${report.pluginVersion || 'indisponivel'}`,
    `- Runs por alvo: ${report.runs}`,
    '',
    '| Alvo | OK | Duracao avg | DOMContentLoaded avg | Load avg | Recursos avg | Scripts avg | CSS avg | Transfer avg |',
    '|---|---:|---:|---:|---:|---:|---:|---:|---:|',
  ];

  for (const result of report.results) {
    lines.push([
      `| ${result.label}`,
      result.summary.ok ? 'sim' : 'nao',
      formatMetric(result.summary.durationMs, 'ms'),
      formatMetric(result.summary.domContentLoadedMs, 'ms'),
      formatMetric(result.summary.loadMs, 'ms'),
      formatMetric(result.summary.resourceCount, ''),
      formatMetric(result.summary.scriptCount, ''),
      formatMetric(result.summary.styleCount, ''),
      formatMetric(result.summary.transferKB, 'KB'),
    ].join(' | ') + ' |');
  }

  lines.push('');
  lines.push('Use este arquivo para comparar antes/depois do cutover. O script nao cria pedidos e nao salva configuracoes.');
  lines.push('');

  return `${lines.join('\n')}\n`;
}

function formatMetric(metric, suffix) {
  if (!metric) {
    return '-';
  }

  return `${metric.avg}${suffix ? ` ${suffix}` : ''}`;
}

function readWordPressAuth() {
  const wpCli = resolveWpCli();
  const code = `
$user_id = (int) getenv('EOP_PERF_USER_ID');
if ($user_id <= 0) {
    $user_id = (int) getenv('EOP_SMOKE_USER_ID');
}
if ($user_id <= 0) {
    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
    $user_id = empty($admins) ? 0 : (int) $admins[0];
}
if ($user_id <= 0 || !get_user_by('id', $user_id)) {
    fwrite(STDERR, "Nenhum administrador encontrado para baseline de performance.\\n");
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
echo wp_json_encode(array(
    'userId' => $user_id,
    'pluginVersion' => defined('EOP_VERSION') ? EOP_VERSION : '',
    'siteUrl' => home_url('/'),
    'adminUrl' => admin_url('admin.php?page=eop-pedido-expresso'),
    'publicOrderUrl' => home_url('/pedido-expresso/'),
    'views' => class_exists('EOP_Admin_Page') ? EOP_Admin_Page::get_view_urls() : array(),
    'cookies' => $cookies,
), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
`;

  const result = spawnSync(wpCli.command, [
    ...wpCli.args,
    `--path=${wpRoot}`,
    '--skip-themes',
    'eval',
    code,
  ], {
    cwd: path.resolve(wpRoot, '..'),
    encoding: 'utf8',
    env: {
      ...process.env,
      EOP_PERF_USER_ID: String(userId),
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
