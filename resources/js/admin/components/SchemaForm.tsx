import { type PointerEvent, type ReactNode } from 'react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { getPath, setPath } from '../lib';
import { MediaField, MediaUploadMany } from './MediaField';
import { RichText } from './RichText';
import { SortableList } from './SortableList';
import { acceptsAi, FieldAi, type FieldAiContext } from './SectionCopy';

export type Field = {
  type: string;
  name: string;
  label: string;
  simple?: boolean;
  itemType?: string;
  kind?: 'image' | 'video' | 'pdf';
  fields?: Field[];
  options?: { value: string; label: string }[];
  max?: number;
  all?: boolean;
  hint?: string;
  showIf?: string;
};

export type SchemaContext = {
  pageSlug?: string;
};

export type Section = {
  id?: string;
  type?: string;
  label: string;
  preview?: string;
  fields: Field[];
};

function AiSlot({
  type,
  name,
  path,
  value,
  ai,
  onChange,
  children,
}: {
  type: string;
  name: string;
  path: string;
  value: unknown;
  ai?: FieldAiContext;
  onChange: (value: string) => void;
  children: ReactNode;
}) {
  if (!ai || !acceptsAi(type, name)) {
    return <>{children}</>;
  }

  return (
    <div className={`field-ai-wrap is-${type}`}>
      {children}
      <FieldAi ai={ai} path={path} value={value} onApply={onChange} />
    </div>
  );
}

function FieldControl({
  field,
  value,
  context,
  path,
  ai,
  onChange,
}: {
  field: Field;
  value: unknown;
  context?: SchemaContext;
  path: string;
  ai?: FieldAiContext;
  onChange: (value: unknown) => void;
}) {
  if (field.type === 'select') {
    return (
      <div className="field">
        <label>{field.label}</label>
        <select value={(value as string) ?? ''} onChange={(event) => onChange(event.target.value)}>
          <option value="">Padrão</option>
          {(field.options ?? []).map((option) => (
            <option key={option.value} value={option.value}>{option.label}</option>
          ))}
        </select>
        {field.hint ? <p className="hint">{field.hint}</p> : null}
      </div>
    );
  }

  if (field.type === 'textarea') {
    return (
      <div className="field">
        <label>{field.label}</label>
        <AiSlot type={field.type} name={field.name} path={path} value={value} ai={ai} onChange={onChange}>
          <textarea value={(value as string) ?? ''} onChange={(event) => onChange(event.target.value)} />
        </AiSlot>
      </div>
    );
  }

  if (field.type === 'toggle') {
    return (
      <div className="field">
        <label>
          <input
            type="checkbox"
            checked={Boolean(value)}
            onChange={(event) => onChange(event.target.checked)}
          />{' '}
          {field.label}
        </label>
        {field.hint ? <p className="hint">{field.hint}</p> : null}
      </div>
    );
  }

  if (field.type === 'rich') {
    return (
      <div className="field">
        <label>{field.label}</label>
        <AiSlot type={field.type} name={field.name} path={path} value={value} ai={ai} onChange={onChange}>
          <RichText value={(value as string) ?? ''} onChange={onChange} />
        </AiSlot>
      </div>
    );
  }

  if (field.type === 'media') {
    return (
      <MediaField
        label={field.label}
        kind={field.kind ?? 'image'}
        value={(value as string) ?? ''}
        onChange={onChange}
      />
    );
  }

  if (field.type === 'repeater') {
    const items = Array.isArray(value) ? value : [];
    const simple = Boolean(field.simple);
    const sortable = items.length > 1;

    const move = (from: number, to: number) => {
      if (to < 0 || to >= items.length || to === from) {
        return;
      }
      const copy = [...items];
      const [moved] = copy.splice(from, 1);
      copy.splice(to, 0, moved);
      onChange(copy);
    };

    const handle = (index: number, onPointerDown: (event: PointerEvent) => void) => (sortable ? (
      <button
        className="icon-btn repeater-handle"
        type="button"
        aria-label={`Mover item ${index + 1}. Arraste ou use as setas.`}
        onPointerDown={onPointerDown}
        onKeyDown={(event) => {
          if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(index, index - 1);
          } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(index, index + 1);
          }
        }}
      >
        <GripVertical size={14} />
      </button>
    ) : null);

    const remove = (index: number) => (
      <button
        className="icon-btn danger"
        type="button"
        aria-label={`Remover item ${index + 1}`}
        onClick={() => onChange(items.filter((_, i) => i !== index))}
      >
        <Trash2 size={14} />
      </button>
    );

    return (
      <div className="field">
        <label>{field.label}</label>
        <SortableList
          items={items.map((item, index) => ({ id: index, item }))}
          onReorder={(from, to) => move(from, to)}
          renderItem={({ item }, index, drag) => (
          <div className="repeater-item">
            {simple ? handle(index, drag.onPointerDown) : null}
            {simple ? (
              <AiSlot
                type={field.itemType ?? 'text'}
                name={field.name}
                path={`${path}.${index}`}
                value={item}
                ai={ai}
                onChange={(next) => {
                  const copy = [...items];
                  copy[index] = next;
                  onChange(copy);
                }}
              >
                {field.itemType === 'media' ? (
                  <MediaField
                    label=""
                    value={typeof item === 'string' ? item : ''}
                    onChange={(next) => {
                      const copy = [...items];
                      copy[index] = next ?? '';
                      onChange(copy);
                    }}
                  />
                ) : field.itemType === 'rich' ? (
                  <RichText
                    value={typeof item === 'string' ? item : ''}
                    onChange={(next) => {
                      const copy = [...items];
                      copy[index] = next;
                      onChange(copy);
                    }}
                  />
                ) : (
                  <input
                    value={typeof item === 'string' ? item : ''}
                    onChange={(event) => {
                      const copy = [...items];
                      copy[index] = event.target.value;
                      onChange(copy);
                    }}
                  />
                )}
              </AiSlot>
            ) : (
              (field.fields ?? []).map((child) => (
                <FieldControl
                  key={child.name}
                  field={child}
                  context={context}
                  path={`${path}.${index}.${child.name}`}
                  ai={ai}
                  value={(item as Record<string, unknown>)?.[child.name]}
                  onChange={(next) => {
                    const copy = [...items] as Record<string, unknown>[];
                    copy[index] = { ...(copy[index] ?? {}), [child.name]: next };
                    onChange(copy);
                  }}
                />
              ))
            )}
            {simple ? remove(index) : (
              <div className="repeater-actions">
                {handle(index, drag.onPointerDown)}
                {remove(index)}
              </div>
            )}
          </div>
          )}
        />
        <button
          className="btn btn-secondary"
          type="button"
          onClick={() => onChange([...items, simple ? '' : {}])}
        >
          <Plus size={14} /> Adicionar
        </button>
        {simple && field.itemType === 'media' ? (
          <MediaUploadMany onUploaded={(paths) => onChange([...items, ...paths])} />
        ) : null}
      </div>
    );
  }

  return (
    <div className="field">
      <label>{field.label}</label>
      <AiSlot type={field.type} name={field.name} path={path} value={value} ai={ai} onChange={onChange}>
        <input value={(value as string) ?? ''} onChange={(event) => onChange(event.target.value)} />
      </AiSlot>
      {field.hint ? <p className="hint">{field.hint}</p> : null}
    </div>
  );
}

export function SchemaForm({
  fields,
  content,
  context,
  ai,
  onChange,
}: {
  fields: Field[];
  content: Record<string, unknown>;
  context?: SchemaContext;
  ai?: FieldAiContext;
  onChange: (content: Record<string, unknown>) => void;
}) {
  return (
    <>
      {fields.filter((field) => !field.showIf || Boolean(getPath(content, field.showIf))).map((field) => (
        <FieldControl
          key={field.name}
          field={field}
          context={context}
          path={field.name}
          ai={ai}
          value={getPath(content, field.name)}
          onChange={(next) => onChange(setPath(content, field.name, next))}
        />
      ))}
    </>
  );
}
