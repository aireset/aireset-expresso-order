import { useEffect, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import TextAlign from '@tiptap/extension-text-align';
import { adminApi } from './app/api';
import type { ConfirmationDocument } from './app/types';

type PlaceholderGroup = { label: string; tokens: string[] };

function groupPlaceholders(tokens: string[]): PlaceholderGroup[] {
  const order: string[] = [];
  const contract: string[] = [];
  const shipping: string[] = [];

  tokens.forEach((token) => {
    if (token.indexOf('{shipping_') === 0) {
      shipping.push(token);
    } else if (token.indexOf('{contract_') === 0) {
      contract.push(token);
    } else {
      order.push(token);
    }
  });

  return [
    { label: 'Pedido e cobranca', tokens: order },
    { label: 'Contrato e aceite', tokens: contract },
    { label: 'Entrega', tokens: shipping },
  ].filter((group) => group.tokens.length > 0);
}

function ToolbarButton({
  icon,
  title,
  active,
  onClick,
}: {
  icon: string;
  title: string;
  active?: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      className={`eop-rte__btn ${active ? 'is-active' : ''}`}
      title={title}
      aria-label={title}
      onMouseDown={(event) => event.preventDefault()}
      onClick={onClick}
    >
      <span className={`dashicons dashicons-${icon}`} aria-hidden="true" />
    </button>
  );
}

// Editor rico nativo de React (TipTap / ProseMirror). Produz HTML, sincroniza com
// o estado via onUpdate e tem menu de placeholder categorizado como no legado.
function RichTextEditor({
  value,
  tokens,
  onChange,
}: {
  value: string;
  tokens: string[];
  onChange: (html: string) => void;
}) {
  const [placeholderOpen, setPlaceholderOpen] = useState(false);
  const [openGroup, setOpenGroup] = useState<string | null>(null);
  const [mode, setMode] = useState<'visual' | 'code'>('visual');

  const editor = useEditor({
    extensions: [StarterKit, TextAlign.configure({ types: ['heading', 'paragraph'] })],
    content: value || '',
    immediatelyRender: false,
    onUpdate: ({ editor: current }) => onChange(current.getHTML()),
  });

  // Re-sincroniza o conteudo quando `value` muda por fora (troca de documento,
  // load do servidor) sem sobrescrever digitacao em andamento nem disparar onUpdate.
  useEffect(() => {
    if (!editor) {
      return;
    }
    const incoming = value || '';
    if (!editor.isFocused && incoming !== editor.getHTML()) {
      editor.commands.setContent(incoming, false);
    }
  }, [value, editor]);

  if (!editor) {
    return null;
  }

  function switchMode(next: 'visual' | 'code') {
    if (next === mode || !editor) {
      return;
    }

    if (next === 'code') {
      // Captura o HTML atual do editor visual antes de mostrar o codigo.
      onChange(editor.getHTML());
    } else {
      // Carrega o HTML (possivelmente editado a mao) de volta no editor visual.
      editor.commands.setContent(value || '');
    }

    setMode(next);
  }

  const groups = groupPlaceholders(tokens);
  const blockValue = editor.isActive('heading', { level: 2 })
    ? 'h2'
    : editor.isActive('heading', { level: 3 })
    ? 'h3'
    : 'p';

  function applyLink(current: Editor) {
    const previous = (current.getAttributes('link').href as string | undefined) ?? '';
    const url = window.prompt('URL do link:', previous || 'https://');

    if (url === null) {
      return;
    }

    if (url === '') {
      current.chain().focus().unsetLink().run();
      return;
    }

    current.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }

  return (
    <div className="eop-rte">
      <div className="eop-rte__tabs">
        <button
          type="button"
          className={`eop-rte__tab ${mode === 'visual' ? 'is-active' : ''}`}
          onClick={() => switchMode('visual')}
        >
          Visual
        </button>
        <button
          type="button"
          className={`eop-rte__tab ${mode === 'code' ? 'is-active' : ''}`}
          onClick={() => switchMode('code')}
        >
          Codigo
        </button>
      </div>

      {mode === 'visual' ? (
      <div className="eop-rte__toolbar">
        <select
          className="eop-rte__format"
          value={blockValue}
          onChange={(event) => {
            const next = event.target.value;
            if (next === 'p') {
              editor.chain().focus().setParagraph().run();
            } else {
              editor
                .chain()
                .focus()
                .toggleHeading({ level: next === 'h2' ? 2 : 3 })
                .run();
            }
          }}
        >
          <option value="p">Paragrafo</option>
          <option value="h2">Titulo 2</option>
          <option value="h3">Titulo 3</option>
        </select>

        <ToolbarButton icon="editor-bold" title="Negrito" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()} />
        <ToolbarButton icon="editor-italic" title="Italico" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()} />
        <ToolbarButton icon="editor-underline" title="Sublinhado" active={editor.isActive('underline')} onClick={() => editor.chain().focus().toggleUnderline().run()} />
        <ToolbarButton icon="editor-strikethrough" title="Tachado" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()} />
        <span className="eop-rte__sep" />
        <ToolbarButton icon="editor-ul" title="Lista" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()} />
        <ToolbarButton icon="editor-ol" title="Lista numerada" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()} />
        <ToolbarButton icon="editor-quote" title="Citacao" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()} />
        <span className="eop-rte__sep" />
        <ToolbarButton icon="editor-alignleft" title="Esquerda" active={editor.isActive({ textAlign: 'left' })} onClick={() => editor.chain().focus().setTextAlign('left').run()} />
        <ToolbarButton icon="editor-aligncenter" title="Centro" active={editor.isActive({ textAlign: 'center' })} onClick={() => editor.chain().focus().setTextAlign('center').run()} />
        <ToolbarButton icon="editor-alignright" title="Direita" active={editor.isActive({ textAlign: 'right' })} onClick={() => editor.chain().focus().setTextAlign('right').run()} />
        <span className="eop-rte__sep" />
        <ToolbarButton icon="admin-links" title="Link" active={editor.isActive('link')} onClick={() => applyLink(editor)} />
        <ToolbarButton icon="undo" title="Desfazer" onClick={() => editor.chain().focus().undo().run()} />
        <ToolbarButton icon="redo" title="Refazer" onClick={() => editor.chain().focus().redo().run()} />

        {groups.length ? (
          <div className="eop-rte__placeholder">
            <button
              type="button"
              className="eop-rte__placeholder-toggle"
              onClick={() => setPlaceholderOpen((open) => !open)}
            >
              Inserir placeholder
              <span className="dashicons dashicons-arrow-down-alt2" aria-hidden="true" />
            </button>
            {placeholderOpen ? (
              <div className="eop-rte__placeholder-menu">
                {groups.map((group) => {
                  const expanded = openGroup === group.label;

                  return (
                    <div className="eop-rte__placeholder-group" key={group.label}>
                      <button
                        type="button"
                        className="eop-rte__placeholder-grouptoggle"
                        aria-expanded={expanded}
                        onClick={() => setOpenGroup(expanded ? null : group.label)}
                      >
                        <span>{group.label}</span>
                        <span
                          className={`dashicons dashicons-arrow-${expanded ? 'up' : 'down'}-alt2`}
                          aria-hidden="true"
                        />
                      </button>
                      {expanded
                        ? group.tokens.map((token) => (
                            <button
                              type="button"
                              key={token}
                              className="eop-rte__placeholder-item"
                              onClick={() => {
                                editor.chain().focus().insertContent(token).run();
                                setPlaceholderOpen(false);
                              }}
                            >
                              {token}
                            </button>
                          ))
                        : null}
                    </div>
                  );
                })}
              </div>
            ) : null}
          </div>
        ) : null}
      </div>
      ) : null}

      <div className={`eop-rte__content-wrap ${mode === 'code' ? 'is-hidden' : ''}`}>
        <EditorContent editor={editor} className="eop-rte__content" />
      </div>

      {mode === 'code' ? (
        <textarea
          className="eop-rte__code"
          value={value}
          spellCheck={false}
          onChange={(event) => onChange(event.target.value)}
        />
      ) : null}
    </div>
  );
}

function openDocumentMedia(onSelect: (id: number, name: string, url: string) => void) {
  const wp = (window as unknown as { wp?: { media?: (config: unknown) => unknown } }).wp;

  if (!wp?.media) {
    window.alert('Biblioteca de midia indisponivel.');
    return;
  }

  const frame = wp.media({
    title: 'Selecionar arquivo do documento',
    button: { text: 'Usar este arquivo' },
    multiple: false,
    library: {
      type: [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      ],
    },
  }) as {
    on: (event: string, handler: () => void) => void;
    open: () => void;
    state: () => {
      get: (key: string) => { first: () => { toJSON: () => { id?: number; filename?: string; url?: string } } };
    };
  };

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first().toJSON();

    if (attachment?.id) {
      onSelect(Number(attachment.id), String(attachment.filename ?? ''), String(attachment.url ?? ''));
    }
  });

  frame.open();
}

function emptyDocument(): ConfirmationDocument {
  return {
    key: '',
    title: '',
    description: '',
    source_type: 'editor',
    body: '',
    attachment_id: 0,
    attachment_name: '',
    attachment_url: '',
    button_label: '',
    view_label: '',
  };
}

export default function DocumentsManager() {
  const [documents, setDocuments] = useState<ConfirmationDocument[]>([]);
  const [tokens, setTokens] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [message, setMessage] = useState('');
  const [openIndex, setOpenIndex] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;

    adminApi
      .getConfirmationDocuments()
      .then((payload) => {
        if (cancelled) {
          return;
        }

        setDocuments(payload.documents ?? []);
        setTokens(payload.placeholderTokens ?? []);
        setLoading(false);
      })
      .catch((fetchError) => {
        if (cancelled) {
          return;
        }

        setError(fetchError instanceof Error ? fetchError.message : 'Falha ao carregar documentos.');
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  function patchDocument(index: number, patch: Partial<ConfirmationDocument>) {
    setDocuments((current) => current.map((document, i) => (i === index ? { ...document, ...patch } : document)));
    setSaveState('idle');
    setMessage('');
  }

  function addDocument() {
    setOpenIndex(documents.length);
    setDocuments((current) => [...current, emptyDocument()]);
    setSaveState('idle');
    setMessage('');
  }

  function removeDocument(index: number) {
    setDocuments((current) => current.filter((_, i) => i !== index));
    setOpenIndex(null);
    setSaveState('idle');
    setMessage('');
  }

  async function save() {
    setSaveState('saving');
    setMessage('');

    try {
      const payload = await adminApi.saveConfirmationDocuments(documents);
      setDocuments(payload.documents ?? []);
      setSaveState('saved');
      setMessage('Documentos salvos com sucesso.');
    } catch (saveError) {
      setSaveState('error');
      setMessage(saveError instanceof Error ? saveError.message : 'Falha ao salvar os documentos.');
    }
  }

  if (loading) {
    return (
      <div className="eop-react-block">
        <div className="eop-react-loading" role="status" aria-live="polite">
          <span className="eop-react-loading__spinner" aria-hidden="true" />
          <span>Carregando documentos...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="eop-react-block">
        <p className="eop-react-error">{error}</p>
      </div>
    );
  }

  return (
    <div className="eop-react-block">
      <div className="eop-react-block__head">
        <div>
          <h4>Listagem de documentos</h4>
          <p>
            Cada documento aparece como um card. Escolha se ele sera escrito no editor ou enviado como
            arquivo.
          </p>
        </div>
        <button type="button" className="eop-react-button" onClick={addDocument}>
          Cadastrar documento
        </button>
      </div>

      {message ? (
        <p className={saveState === 'error' ? 'eop-react-error' : 'eop-react-success'}>{message}</p>
      ) : null}

      {documents.length === 0 ? (
        <p>Nenhum documento cadastrado ainda. Clique em "Cadastrar documento" para comecar.</p>
      ) : (
        <div className="eop-react-docs-list">
          {documents.map((document, index) => {
            const isOpen = openIndex === index;

            return (
              <div className={`eop-react-accordion ${isOpen ? 'is-open' : ''}`} key={index}>
                <div className="eop-react-doc__head">
                  <button
                    type="button"
                    className="eop-react-accordion__head"
                    aria-expanded={isOpen}
                    onClick={() => setOpenIndex(isOpen ? null : index)}
                  >
                    <span className="eop-react-accordion__title">{document.title || 'Novo documento'}</span>
                    <span className="eop-react-doc__type-tag">
                      {document.source_type === 'attachment' ? 'Arquivo' : 'Conteudo'}
                    </span>
                    <span
                      className="eop-react-accordion__arrow dashicons dashicons-arrow-down-alt2"
                      aria-hidden="true"
                    />
                  </button>
                </div>

                <div className="eop-react-accordion__body" hidden={!isOpen}>
                  <div className="eop-react-form eop-react-form--settings">
                    <label className="eop-react-form__field">
                      <span className="eop-react-field-label">Titulo do documento</span>
                      <input
                        type="text"
                        value={document.title}
                        onChange={(event) => patchDocument(index, { title: event.target.value })}
                      />
                    </label>

                    <label className="eop-react-form__field">
                      <span className="eop-react-field-label">Tipo do documento</span>
                      <select
                        value={document.source_type}
                        onChange={(event) =>
                          patchDocument(index, { source_type: event.target.value as 'editor' | 'attachment' })
                        }
                      >
                        <option value="editor">Conteudo do documento (HTML)</option>
                        <option value="attachment">Arquivo do documento (PDF/Word)</option>
                      </select>
                    </label>

                    {document.source_type === 'editor' ? (
                      <div className="eop-react-form__field eop-react-form__field--full">
                        <span className="eop-react-field-label">Conteudo do documento</span>
                        {isOpen ? (
                          <RichTextEditor
                            value={document.body}
                            tokens={tokens}
                            onChange={(html) => patchDocument(index, { body: html })}
                          />
                        ) : null}
                      </div>
                    ) : (
                      <div className="eop-react-form__field eop-react-form__field--full">
                        <span className="eop-react-field-label">Arquivo do documento</span>
                        <div className="eop-react-doc__attachment">
                          <span className="eop-react-doc__attachment-name">
                            {document.attachment_id
                              ? document.attachment_name || `Anexo #${document.attachment_id}`
                              : 'Nenhum arquivo anexado ainda.'}
                          </span>
                          <div className="eop-react-doc__attachment-actions">
                            <button
                              type="button"
                              className="eop-react-button"
                              onClick={() =>
                                openDocumentMedia((id, name, url) =>
                                  patchDocument(index, {
                                    attachment_id: id,
                                    attachment_name: name,
                                    attachment_url: url,
                                  })
                                )
                              }
                            >
                              {document.attachment_id ? 'Trocar arquivo' : 'Selecionar arquivo'}
                            </button>
                            {document.attachment_id ? (
                              <button
                                type="button"
                                className="eop-react-link-button"
                                onClick={() =>
                                  patchDocument(index, { attachment_id: 0, attachment_name: '', attachment_url: '' })
                                }
                              >
                                Remover arquivo
                              </button>
                            ) : null}
                          </div>
                        </div>
                      </div>
                    )}

                    <label className="eop-react-form__field eop-react-form__field--full">
                      <span className="eop-react-field-label">Descricao (opcional)</span>
                      <input
                        type="text"
                        value={document.description}
                        onChange={(event) => patchDocument(index, { description: event.target.value })}
                      />
                    </label>

                    <label className="eop-react-form__field">
                      <span className="eop-react-field-label">Texto do botao de download</span>
                      <input
                        type="text"
                        placeholder="Baixar PDF"
                        value={document.button_label}
                        onChange={(event) => patchDocument(index, { button_label: event.target.value })}
                      />
                    </label>

                    <label className="eop-react-form__field">
                      <span className="eop-react-field-label">Texto do botao de visualizar</span>
                      <input
                        type="text"
                        placeholder="Visualizar PDF"
                        value={document.view_label}
                        onChange={(event) => patchDocument(index, { view_label: event.target.value })}
                      />
                    </label>
                  </div>

                  <div className="eop-react-doc__remove">
                    <button type="button" className="eop-react-link-button" onClick={() => removeDocument(index)}>
                      Remover documento
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      <div className="eop-react-doc__footer">
        <small>Arquivos Word sao convertidos em PDF quando o documento for gerado para o pedido.</small>
        <small>
          Use o menu "Inserir placeholder" no editor para adicionar os dados dinamicos sem copiar
          manualmente.
        </small>
        {tokens.length ? <small>Placeholders disponiveis: {tokens.join(', ')}</small> : null}
      </div>

      <div className="eop-react-save-bar">
        <button
          type="button"
          className="eop-react-button"
          onClick={() => void save()}
          disabled={saveState === 'saving'}
        >
          {saveState === 'saving' ? 'Salvando...' : 'Salvar documentos'}
        </button>
      </div>
    </div>
  );
}
