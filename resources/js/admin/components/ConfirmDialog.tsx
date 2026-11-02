import { Modal } from './Modal';

export function ConfirmDialog({
  title,
  message,
  confirmLabel = 'Excluir',
  busy = false,
  onConfirm,
  onClose,
}: {
  title: string;
  message: string;
  confirmLabel?: string;
  busy?: boolean;
  onConfirm: () => void;
  onClose: () => void;
}) {
  return (
    <Modal
      title={title}
      eyebrow="Confirmação"
      onClose={onClose}
      footer={(
        <>
          <button className="btn btn-secondary" type="button" disabled={busy} onClick={onClose}>
            Cancelar
          </button>
          <button className="btn btn-danger" type="button" disabled={busy} onClick={onConfirm}>
            {confirmLabel}
          </button>
        </>
      )}
    >
      <p className="desc">{message}</p>
    </Modal>
  );
}
