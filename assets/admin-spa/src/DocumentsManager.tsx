import { useEffect, useState } from 'react';
import { adminApi } from './app/api';
import type { ConfirmationDocument } from './app/types';

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
          <h4>Documentos do contrato</h4>
          <p>
            Cadastre os documentos exibidos na etapa de assinatura. Cada documento pode ser um conteudo
            escrito (HTML) ou um arquivo enviado (PDF/Word).
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
                      <label className="eop-react-form__field eop-react-form__field--full">
                        <span className="eop-react-field-label">Conteudo do documento</span>
                        <textarea
                          rows={8}
                          value={document.body}
                          onChange={(event) => patchDocument(index, { body: event.target.value })}
                        />
                        {tokens.length ? (
                          <div className="eop-react-doc__tokens">
                            <small>Inserir variavel:</small>
                            {tokens.map((token) => (
                              <button
                                type="button"
                                key={token}
                                className="eop-react-doc__token"
                                onClick={() => patchDocument(index, { body: `${document.body}${token}` })}
                              >
                                {token}
                              </button>
                            ))}
                          </div>
                        ) : null}
                      </label>
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
