import { Mark, useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import { Bold, Highlighter, Italic, Link as LinkIcon, List, ListOrdered, Strikethrough, Underline as UnderlineIcon } from 'lucide-react';
import { useEffect } from 'react';

// O site pinta <mark> com o destaque da marca: faixa lima no claro, texto lima no escuro.
const Highlight = Mark.create({
  name: 'highlight',
  parseHTML() {
    return [{ tag: 'mark' }];
  },
  renderHTML() {
    return ['mark', 0];
  },
});

// Links internos abrem na mesma aba e passam autoridade: sem target/rel (o padrão do TipTap põe nofollow em tudo).
const SiteLink = Link.extend({
  renderHTML(props) {
    const rendered = this.parent?.(props) as [string, Record<string, unknown>, 0];
    const href = String(rendered[1].href ?? '');

    if (href.startsWith('/') || href.startsWith('#')) {
      const { target: _target, rel: _rel, ...attributes } = rendered[1];

      return [rendered[0], attributes, 0];
    }

    return rendered;
  },
});

export function RichText({
  value,
  onChange,
}: {
  value: string;
  onChange: (value: string) => void;
}) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: false }),
      Underline,
      Highlight,
      SiteLink.configure({ openOnClick: false }),
    ],
    content: value || '',
    onUpdate: ({ editor: current }) => {
      onChange(current.getHTML() === '<p></p>' ? '' : current.getHTML());
    },
  });

  useEffect(() => {
    if (!editor) {
      return;
    }
    const current = editor.getHTML() === '<p></p>' ? '' : editor.getHTML();
    const next = value || '';
    if (next !== current) {
      editor.commands.setContent(next, { emitUpdate: false });
    }
  }, [value, editor]);

  if (!editor) {
    return null;
  }

  return (
    <div className="rich-wrap">
      <div className="rich-toolbar">
        <button type="button" className={editor.isActive('bold') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleBold().run()}><Bold size={14} /></button>
        <button type="button" className={editor.isActive('italic') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleItalic().run()}><Italic size={14} /></button>
        <button type="button" className={editor.isActive('underline') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleUnderline().run()}><UnderlineIcon size={14} /></button>
        <button type="button" className={editor.isActive('strike') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleStrike().run()}><Strikethrough size={14} /></button>
        <button type="button" className={editor.isActive('bulletList') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleBulletList().run()}><List size={14} /></button>
        <button type="button" className={editor.isActive('orderedList') ? 'is-active' : ''} onClick={() => editor.chain().focus().toggleOrderedList().run()}><ListOrdered size={14} /></button>
        <button
          type="button"
          className={editor.isActive('link') ? 'is-active' : ''}
          onClick={() => {
            const href = window.prompt('Link');
            if (href) {
              editor.chain().focus().setLink({ href }).run();
            }
          }}
        >
          <LinkIcon size={14} />
        </button>
        <button
          type="button"
          className={editor.isActive('highlight') ? 'is-active' : ''}
          title="Destaque da marca"
          onClick={() => editor.chain().focus().toggleMark('highlight').run()}
        >
          <Highlighter size={14} />
        </button>
      </div>
      <EditorContent editor={editor} />
    </div>
  );
}
