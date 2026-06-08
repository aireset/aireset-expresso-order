<?php
defined( 'ABSPATH' ) || exit;

$docs = array(
    'Arquitetura' => array(
        'path' => 'docs/ARCHITECTURE.md',
        'url'  => EOP_PLUGIN_URL . 'docs/ARCHITECTURE.md',
        'text' => __( 'Arquitetura atual, arquitetura alvo, contratos do novo admin e limites do legado.', EOP_TEXT_DOMAIN ),
    ),
    'Roadmap' => array(
        'path' => 'docs/ROADMAP.md',
        'url'  => EOP_PLUGIN_URL . 'docs/ROADMAP.md',
        'text' => __( 'Backlog consolidado, matriz funcional por superficie e criterio de aceite.', EOP_TEXT_DOMAIN ),
    ),
    'Operacoes' => array(
        'path' => 'docs/OPERATIONS.md',
        'url'  => EOP_PLUGIN_URL . 'docs/OPERATIONS.md',
        'text' => __( 'Build, release, smoke tests, empacotamento e rotina operacional.', EOP_TEXT_DOMAIN ),
    ),
    'Legal e IA' => array(
        'path' => 'docs/LEGAL_AND_AI_POLICY.md',
        'url'  => EOP_PLUGIN_URL . 'docs/LEGAL_AND_AI_POLICY.md',
        'text' => __( 'Titularidade, licenca proprietaria, politica para IA e fluxo de autorizacao.', EOP_TEXT_DOMAIN ),
    ),
);
?>
<div class="eop-settings-page eop-settings-page--embedded">
    <div class="eop-settings-sections">
        <section class="eop-settings-card">
            <h2><?php esc_html_e( 'Documentacao canonica do plugin', EOP_TEXT_DOMAIN ); ?></h2>
            <p><?php esc_html_e( 'Esta aba nao e mais documentacao apenas do modulo PDF. Agora ela aponta para a base oficial do plugin inteiro.', EOP_TEXT_DOMAIN ); ?></p>
            <div class="eop-settings-grid">
                <?php foreach ( $docs as $title => $doc ) : ?>
                    <div class="eop-settings-field is-full">
                        <strong><?php echo esc_html( $title ); ?></strong>
                        <p><?php echo esc_html( $doc['text'] ); ?></p>
                        <p><code><?php echo esc_html( $doc['path'] ); ?></code></p>
                        <p><a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Abrir documento', EOP_TEXT_DOMAIN ); ?></a></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="eop-settings-card">
            <h2><?php esc_html_e( 'Governanca do plugin', EOP_TEXT_DOMAIN ); ?></h2>
            <p><?php esc_html_e( 'Ownership, licenca e instrucoes para agentes agora vivem na documentacao canonica e nos arquivos principais da raiz do plugin.', EOP_TEXT_DOMAIN ); ?></p>
            <div class="eop-settings-grid">
                <div class="eop-settings-field is-full">
                    <strong><?php esc_html_e( 'Arquivos principais', EOP_TEXT_DOMAIN ); ?></strong>
                    <p><code>README.md</code>, <code>AGENT.md</code>, <code>LICENSE</code>, <code>readme.txt</code>, <code>.github/copilot-instructions.md</code></p>
                </div>
                <div class="eop-settings-field is-full">
                    <strong><?php esc_html_e( 'Historico arquivado', EOP_TEXT_DOMAIN ); ?></strong>
                    <p><code>docs/archive/2026-05/</code></p>
                </div>
            </div>
        </section>

        <?php if ( class_exists( 'EOP_Admin_SPA' ) ) : ?>
            <section class="eop-settings-card">
                <h2><?php esc_html_e( 'Novo admin SPA', EOP_TEXT_DOMAIN ); ?></h2>
                <p><?php esc_html_e( 'O admin React/Vite e a superficie principal quando o bundle esta compilado. O shell PHP legado permanece como fallback tecnico.', EOP_TEXT_DOMAIN ); ?></p>
                <p><a class="button button-primary" href="<?php echo esc_url( EOP_Admin_SPA::get_legacy_url() ); ?>"><?php esc_html_e( 'Abrir fallback legado', EOP_TEXT_DOMAIN ); ?></a></p>
            </section>
        <?php endif; ?>
    </div>
</div>
