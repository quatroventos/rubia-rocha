import { Copy } from 'lucide-react';
import { useState } from 'react';

export function RecoveryCodes({ codes }: { codes: string[] }) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    await navigator.clipboard.writeText(codes.join('\n'));
    setCopied(true);
  }

  return (
    <div className="recovery-codes">
      <ul>
        {codes.map((code) => <li key={code}><code>{code}</code></li>)}
      </ul>
      <button className="btn btn-secondary" type="button" onClick={() => void copy()}>
        <Copy size={14} /> {copied ? 'Copiados' : 'Copiar códigos'}
      </button>
    </div>
  );
}
