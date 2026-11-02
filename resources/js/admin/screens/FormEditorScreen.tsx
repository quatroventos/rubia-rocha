import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { api, firstError } from '../api';
import { AppShell } from '../components/AppShell';
import { ConfirmDialog } from '../components/ConfirmDialog';

type OptionRow = {
  id?: number;
  key: string;
  label: string;
  value: string;
  email?: string;
  sort: number;
};

type FormField = {
  key: string;
  name: string;
  label: string;
  type: string;
  required?: boolean;
  system?: boolean;
  draft?: boolean;
  hint?: string;
  options?: OptionRow[];
};

type FormSection = {
  key: string;
  label: string;
  fields: FormField[];
};

type FormDetail = {
  key: string;
  label: string;
  path: string;
  submit_label: string;
  lgpd_label: string;
  sections: FormSection[];
};

const TYPES = [
  { value: 'text', label: 'Texto' },
  { value: 'email', label: 'E-mail' },
  { value: 'tel', label: 'Telefone' },
  { value: 'url', label: 'URL' },
  { value: 'textarea', label: 'Texto longo' },
  { value: 'select', label: 'Seleção' },
  { value: 'checkbox', label: 'Checkbox' },
];

function uid(): string {
  return `new-${crypto.randomUUID()}`;
}

function typeLabel(type: string): string {
  return TYPES.find((item) => item.value === type)?.label
    ?? (type === 'file' ? 'Arquivo' : type);
}

function hydrate(data: Omit<FormDetail, 'sections'> & { sections: Array<Omit<FormSection, 'key' | 'fields'> & { fields: Array<Omit<FormField, 'key'> & { options?: Omit<OptionRow, 'key'>[] }> }> }): FormDetail {
  return {
    ...data,
    sections: data.sections.map((section) => ({
      ...section,
      key: uid(),
      fields: section.fields.map((field) => ({
        ...field,
        key: uid(),
        options: (field.options ?? []).map((option) => ({
          ...option,
          key: option.id ? `id-${option.id}` : uid(),
        })),
      })),
    })),
  };
}

function slugName(label: string): string {
  const slug = label
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
    .slice(0, 40);

  return slug || 'campo';
}

function uniqueName(form: FormDetail, base: string, ignoreKey?: string): string {
  const taken = new Set(
    form.sections.flatMap((section) => section.fields.filter((field) => field.key !== ignoreKey).map((field) => field.name)),
  );
  taken.add('empresa_site');
  taken.add('_token');
  let name = base || 'campo';
  let index = 2;
  while (taken.has(name)) {
    name = `${base}_${index}`;
    index += 1;
  }
  return name;
}

export function FormEditorScreen() {
  const { form: formKey } = useParams();
  const navigate = useNavigate();
  const [form, setForm] = useState<FormDetail | null>(null);
  const [active, setActive] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [pendingRemove, setPendingRemove] = useState<
    | { kind: 'section'; sectionKey: string }
    | { kind: 'field'; sectionKey: string; fieldKey: string }
    | { kind: 'option'; sectionKey: string; fieldKey: string; optionKey: string }
    | null
  >(null);

  useEffect(() => {
    if (!formKey) {
      return;
    }

    void api<{ data: Parameters<typeof hydrate>[0] }>(`/forms/${formKey}`).then((payload) => {
      const next = hydrate(payload.data);
      setForm(next);
      setActive(next.sections[0]?.fields[0]?.name ?? null);
    }).catch(() => navigate('/formularios'));
  }, [formKey, navigate]);

  function patchForm(updater: (current: FormDetail) => FormDetail) {
    setForm((current) => current ? updater(current) : current);
  }

  function updateSection(sectionKey: string, patch: Partial<FormSection>) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? { ...section, ...patch } : section),
    }));
  }

  function updateField(sectionKey: string, fieldKey: string, patch: Partial<FormField>) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => {
        if (section.key !== sectionKey) {
          return section;
        }

        return {
          ...section,
          fields: section.fields.map((field) => {
            if (field.key !== fieldKey) {
              return field;
            }

            const next = { ...field, ...patch };
            if (patch.type === 'select' && (next.options ?? []).length === 0) {
              next.options = [];
            }
            if (patch.type && patch.type !== 'select') {
              next.options = [];
            }
            return next;
          }),
        };
      }),
    }));
  }

  function moveField(sectionKey: string, fieldKey: string, direction: -1 | 1) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => {
        if (section.key !== sectionKey) {
          return section;
        }
        const fields = [...section.fields];
        const index = fields.findIndex((field) => field.key === fieldKey);
        const next = index + direction;
        if (index < 0 || next < 0 || next >= fields.length) {
          return section;
        }
        [fields[index], fields[next]] = [fields[next], fields[index]];
        return { ...section, fields };
      }),
    }));
  }

  function addField(sectionKey: string) {
    if (!form) {
      return;
    }
    const name = uniqueName(form, 'novo_campo');
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: [
          ...section.fields,
          {
            key: uid(),
            name,
            label: 'Novo campo',
            type: 'text',
            required: false,
            system: false,
            draft: true,
            options: [],
          },
        ],
      } : section),
    }));
    setActive(name);
  }

  function removeField(sectionKey: string, fieldKey: string) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: section.fields.filter((field) => field.key !== fieldKey || field.system),
      } : section),
    }));
  }

  function addSection() {
    patchForm((current) => ({
      ...current,
      sections: [
        ...current.sections,
        { key: uid(), label: 'Nova seção', fields: [] },
      ],
    }));
  }

  function removeSection(sectionKey: string) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.filter((section) => {
        if (section.key !== sectionKey) {
          return true;
        }
        return section.fields.some((field) => field.system);
      }),
    }));
  }

  function updateOption(sectionKey: string, fieldKey: string, optionKey: string, patch: Partial<OptionRow>) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: section.fields.map((field) => field.key === fieldKey ? {
          ...field,
          options: (field.options ?? []).map((option) => option.key === optionKey ? { ...option, ...patch } : option),
        } : field),
      } : section),
    }));
  }

  function moveOption(sectionKey: string, fieldKey: string, optionKey: string, direction: -1 | 1) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: section.fields.map((field) => {
          if (field.key !== fieldKey) {
            return field;
          }
          const list = [...(field.options ?? [])];
          const index = list.findIndex((option) => option.key === optionKey);
          const next = index + direction;
          if (index < 0 || next < 0 || next >= list.length) {
            return field;
          }
          [list[index], list[next]] = [list[next], list[index]];
          return { ...field, options: list.map((option, sort) => ({ ...option, sort: sort + 1 })) };
        }),
      } : section),
    }));
  }

  function addOption(sectionKey: string, fieldKey: string, fieldName: string) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: section.fields.map((field) => field.key === fieldKey ? {
          ...field,
          options: [
            ...(field.options ?? []),
            { key: uid(), label: '', value: '', email: '', sort: (field.options ?? []).length + 1 },
          ],
        } : field),
      } : section),
    }));
    setActive(fieldName);
  }

  function removeOption(sectionKey: string, fieldKey: string, optionKey: string) {
    patchForm((current) => ({
      ...current,
      sections: current.sections.map((section) => section.key === sectionKey ? {
        ...section,
        fields: section.fields.map((field) => field.key === fieldKey ? {
          ...field,
          options: (field.options ?? []).filter((option) => option.key !== optionKey),
        } : field),
      } : section),
    }));
  }

  function confirmPendingRemove() {
    if (!pendingRemove) {
      return;
    }

    if (pendingRemove.kind === 'section') {
      removeSection(pendingRemove.sectionKey);
    } else if (pendingRemove.kind === 'field') {
      removeField(pendingRemove.sectionKey, pendingRemove.fieldKey);
    } else {
      removeOption(pendingRemove.sectionKey, pendingRemove.fieldKey, pendingRemove.optionKey);
    }

    setPendingRemove(null);
  }

  function removeMessage(): string {
    if (!pendingRemove) {
      return '';
    }
    if (pendingRemove.kind === 'section') {
      return 'A seção e os campos dela saem do formulário. Salve para gravar a alteração.';
    }
    if (pendingRemove.kind === 'field') {
      return 'O campo sai do formulário. Salve para gravar a alteração.';
    }
    return 'A opção sai da lista. Salve para gravar a alteração.';
  }

  async function save() {
    if (!formKey || !form) {
      return;
    }
    setError('');
    setSaved(false);
    try {
      const payload = await api<{ data: Parameters<typeof hydrate>[0] }>(`/forms/${formKey}`, {
        method: 'PUT',
        body: JSON.stringify({
          submit_label: form.submit_label,
          lgpd_label: form.lgpd_label,
          sections: form.sections.map((section) => ({
            label: section.label,
            fields: section.fields.map((field) => ({
              name: field.name,
              label: field.label,
              type: field.type,
              required: Boolean(field.required),
              hint: field.hint ?? null,
              options: field.type === 'select'
                ? (field.options ?? [])
                  .filter((option) => option.label.trim())
                  .map((option, index) => ({
                    id: option.id ?? null,
                    label: option.label.trim(),
                    value: (option.value || option.label).trim(),
                    email: (option.email ?? '').trim() || null,
                    sort: index + 1,
                  }))
                : [],
            })),
          })),
        }),
      });
      const next = hydrate(payload.data);
      setForm(next);
      setSaved(true);
    } catch (err) {
      setError(firstError(err));
    }
  }

  if (!form) {
    return (
      <AppShell title="Formulários" full>
        <div className="auth-loading"><div className="spinner" /></div>
      </AppShell>
    );
  }

  return (
    <AppShell title={form.label} subtitle={form.path} full>
      {error ? <div className="editor-banner form-error">{error}</div> : null}
      {saved ? <div className="editor-banner form-success">Formulário atualizado.</div> : null}
      <div className="editor-frame form-editor-split">
        <div className="editor-canvas">
          <div className="panel form-field-panel" data-tour="form-submit">
            <div className="panel-head">
              <h3>Envio</h3>
            </div>
            <div className="panel-body form-meta-grid">
              <label>
                Texto do botão
                <input value={form.submit_label} onChange={(event) => patchForm((current) => ({ ...current, submit_label: event.target.value }))} />
              </label>
              <label data-tour="form-lgpd">
                Texto LGPD
                <textarea rows={3} value={form.lgpd_label} onChange={(event) => patchForm((current) => ({ ...current, lgpd_label: event.target.value }))} />
              </label>
            </div>
          </div>
          {form.sections.map((section, sectionIndex) => (
            <div className="panel form-field-panel" key={section.key} data-tour={sectionIndex === 0 ? 'form-section' : undefined}>
              <div className="panel-head form-section-head">
                <input
                  value={section.label}
                  onChange={(event) => updateSection(section.key, { label: event.target.value })}
                />
                {section.fields.some((field) => field.system) ? null : (
                  <button className="icon-btn danger" type="button" onClick={() => setPendingRemove({ kind: 'section', sectionKey: section.key })}>
                    <Trash2 size={14} />
                  </button>
                )}
              </div>
              <div className="panel-body">
                {section.fields.map((field, index) => {
                  const selected = active === field.name;
                  const lockedType = Boolean(field.system);
                  const firstField = sectionIndex === 0 && index === 0;

                  return (
                    <article
                      key={field.key}
                      className={`form-field-card${selected ? ' is-active' : ''}`}
                      data-tour={firstField ? 'form-field' : undefined}
                      onClick={() => setActive(field.name)}
                    >
                      <div className="form-field-card-head">
                        <div>
                          <strong>{field.label || 'Sem título'}</strong>
                          <span>{typeLabel(field.type)}{field.required ? ' · obrigatório' : ''}{field.system ? ' · sistema' : ''}</span>
                        </div>
                        <div className="form-option-actions">
                          <button className="icon-btn" type="button" disabled={index === 0} onClick={(event) => { event.stopPropagation(); moveField(section.key, field.key, -1); }}>
                            <ChevronUp size={14} />
                          </button>
                          <button className="icon-btn" type="button" disabled={index === section.fields.length - 1} onClick={(event) => { event.stopPropagation(); moveField(section.key, field.key, 1); }}>
                            <ChevronDown size={14} />
                          </button>
                          {field.system ? null : (
                            <button className="icon-btn danger" type="button" onClick={(event) => { event.stopPropagation(); setPendingRemove({ kind: 'field', sectionKey: section.key, fieldKey: field.key }); }}>
                              <Trash2 size={14} />
                            </button>
                          )}
                        </div>
                      </div>
                      <div className="form-field-meta" onClick={(event) => event.stopPropagation()}>
                        <input
                          value={field.label}
                          placeholder="Rótulo"
                          onFocus={() => setActive(field.name)}
                          onChange={(event) => {
                            const label = event.target.value;
                            const patch: Partial<FormField> = { label };
                            if (!field.system && field.draft) {
                              patch.name = uniqueName(form, slugName(label), field.key);
                            }
                            updateField(section.key, field.key, patch);
                          }}
                        />
                        <select
                          value={field.type}
                          disabled={lockedType}
                          data-tour={firstField ? 'form-type' : undefined}
                          onChange={(event) => updateField(section.key, field.key, { type: event.target.value })}
                        >
                          {field.type === 'file' ? <option value="file">Arquivo</option> : null}
                          {TYPES.map((item) => (
                            <option key={item.value} value={item.value}>{item.label}</option>
                          ))}
                        </select>
                        <label className="check" data-tour={firstField ? 'form-required' : undefined}>
                          <input
                            type="checkbox"
                            checked={Boolean(field.required)}
                            disabled={['lgpd', 'curriculo', 'nome', 'email'].includes(field.name) || (form.key === 'contact' && field.name === 'telefone')}
                            onChange={(event) => updateField(section.key, field.key, { required: event.target.checked })}
                          />
                          Obrigatório
                        </label>
                      </div>
                      {field.type === 'select' ? (
                        <div className="form-option-list" onClick={(event) => event.stopPropagation()}>
                          {(field.options ?? []).map((option, optionIndex) => {
                            const withEmail = form.key === 'contact' && field.name === 'destino';

                            return (
                              <div className={`form-option-row${withEmail ? ' form-option-row--email' : ''}`} key={option.key}>
                                <input
                                  value={option.label}
                                  placeholder="Label"
                                  onFocus={() => setActive(field.name)}
                                  onChange={(event) => updateOption(section.key, field.key, option.key, withEmail
                                    ? { label: event.target.value, value: event.target.value }
                                    : {
                                      label: event.target.value,
                                      value: option.id ? option.value : event.target.value,
                                    })}
                                />
                                {withEmail ? (
                                  <input
                                    type="email"
                                    value={option.email ?? ''}
                                    placeholder="E-mail do destino"
                                    onFocus={() => setActive(field.name)}
                                    onChange={(event) => updateOption(section.key, field.key, option.key, { email: event.target.value })}
                                  />
                                ) : (
                                  <input
                                    value={option.value}
                                    placeholder="Valor"
                                    onFocus={() => setActive(field.name)}
                                    onChange={(event) => updateOption(section.key, field.key, option.key, { value: event.target.value })}
                                  />
                                )}
                                <div className="form-option-actions">
                                  <button className="icon-btn" type="button" disabled={optionIndex === 0} onClick={() => moveOption(section.key, field.key, option.key, -1)}>
                                    <ChevronUp size={14} />
                                  </button>
                                  <button className="icon-btn" type="button" disabled={optionIndex === (field.options ?? []).length - 1} onClick={() => moveOption(section.key, field.key, option.key, 1)}>
                                    <ChevronDown size={14} />
                                  </button>
                                  <button className="icon-btn danger" type="button" onClick={() => setPendingRemove({ kind: 'option', sectionKey: section.key, fieldKey: field.key, optionKey: option.key })}>
                                    <Trash2 size={14} />
                                  </button>
                                </div>
                              </div>
                            );
                          })}
                          <button className="btn btn-secondary" type="button" onClick={() => addOption(section.key, field.key, field.name)}>
                            <Plus size={14} /> Adicionar opção
                          </button>
                        </div>
                      ) : field.type === 'file' ? (
                        <input
                          className="form-field-hint"
                          value={field.hint ?? ''}
                          placeholder="Texto de ajuda"
                          onClick={(event) => event.stopPropagation()}
                          onChange={(event) => updateField(section.key, field.key, { hint: event.target.value })}
                        />
                      ) : null}
                    </article>
                  );
                })}
                <button className="btn btn-secondary" type="button" data-tour={sectionIndex === 0 ? 'form-add-field' : undefined} onClick={() => addField(section.key)}>
                  <Plus size={14} /> Adicionar campo
                </button>
              </div>
            </div>
          ))}
          <button className="btn btn-secondary form-add-section" type="button" onClick={addSection}>
            <Plus size={14} /> Adicionar seção
          </button>
        </div>
        <aside className="form-preview-pane" data-tour="form-preview">
          <div className="inspect-scroll">
            <span className="eyebrow">Prévia</span>
            <div className="form-preview">
              <h2>{form.label}</h2>
              <form className="form-preview-form" onSubmit={(event) => event.preventDefault()}>
                {form.sections.map((section) => (
                  <div className="form-preview-section" key={section.key}>
                    <h3 className="form-preview-section__title">{section.label}</h3>
                    <div className="form-preview-grid">
                      {section.fields.map((field) => (
                        <div
                          key={field.key}
                          className={`form-preview-field${field.type === 'textarea' || field.type === 'checkbox' || field.type === 'file' || field.name === 'linkedin' ? ' is-full' : ''}${active === field.name ? ' is-active' : ''}`}
                          onClick={() => setActive(field.name)}
                        >
                          {field.type === 'checkbox' ? (
                            <label className="form-preview-check">
                              <input type="checkbox" disabled />
                              <span>{field.name === 'lgpd' ? form.lgpd_label : field.label}</span>
                            </label>
                          ) : (
                            <>
                              <label>{field.label}</label>
                              {field.type === 'textarea' ? (
                                <textarea disabled rows={4} />
                              ) : field.type === 'select' ? (
                                <select disabled defaultValue="">
                                  <option value="">Selecione</option>
                                  {(field.options ?? []).filter((option) => option.label.trim()).map((option) => (
                                    <option key={option.key} value={option.value || option.label}>{option.label}</option>
                                  ))}
                                </select>
                              ) : field.type === 'file' ? (
                                <>
                                  <input type="text" disabled placeholder="Nenhum arquivo" />
                                  {field.hint ? <small>{field.hint}</small> : null}
                                </>
                              ) : (
                                <input type={field.type} disabled />
                              )}
                            </>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
                <button className="btn btn-primary" type="button" disabled>{form.submit_label}</button>
              </form>
            </div>
          </div>
          <div className="editor-actions" data-tour="form-save">
            <button className="btn btn-secondary" type="button" onClick={() => navigate('/formularios')}>Cancelar</button>
            <button className="btn btn-primary" type="button" onClick={() => void save()}>Salvar</button>
          </div>
        </aside>
      </div>
      {pendingRemove ? (
        <ConfirmDialog
          title="Remover?"
          message={removeMessage()}
          confirmLabel="Remover"
          onClose={() => setPendingRemove(null)}
          onConfirm={confirmPendingRemove}
        />
      ) : null}
    </AppShell>
  );
}
