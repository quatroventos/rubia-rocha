import { type ReactNode } from 'react';
import { X } from 'lucide-react';

export function Modal({
  title,
  eyebrow = 'Cadastro',
  wide = false,
  children,
  footer,
  onClose,
}: {
  title: string;
  eyebrow?: string;
  wide?: boolean;
  children: ReactNode;
  footer: ReactNode;
  onClose: () => void;
}) {
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className={`modal${wide ? ' wide' : ''}`} onClick={(event) => event.stopPropagation()}>
        <div className="modal-head">
          <span className="eyebrow">{eyebrow}</span>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12 }}>
            <h2>{title}</h2>
            <button className="icon-btn" type="button" onClick={onClose}><X size={14} /></button>
          </div>
        </div>
        <div className="modal-body">{children}</div>
        <div className="modal-foot">{footer}</div>
      </div>
    </div>
  );
}
