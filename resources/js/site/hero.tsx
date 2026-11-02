import { useEffect, useState, type PointerEvent, type ReactNode } from 'react';
import { getLocale, t } from './i18n';
import { LOGS } from './i18n/log';
import { Cta, Marked, Rich, strings, text, type Data } from './ui';

function LogLines() {
  return (
    <>
      {(LOGS[getLocale()] ?? LOGS['pt-BR']).map((line, row) => (
        <span key={row}>
          {line.map(([value, tone], index) => (tone ? <span key={index} className={tone}>{value}</span> : value))}
          {'\n'}
        </span>
      ))}
    </>
  );
}

export function AgentLog() {
  return (
    <div className="agent-log" aria-hidden="true">
      <div>
        <LogLines />
        <LogLines />
      </div>
    </div>
  );
}

// Fluxo de agente sendo montado nó a nó; as classes c-* e w-* casam com as keyframes de site.css.
const FLOW_NODES = [
  { id: 'a', kind: 'trigger', label: 'flow.trigger', name: 'WhatsApp', x: 20, y: 130, w: 150 },
  { id: 'b', kind: 'agent', label: 'flow.agent', name: 'flow.support', x: 220, y: 130, w: 150 },
  { id: 'c', kind: 'tool', label: 'flow.tool', name: 'flow.knowledge', x: 430, y: 20, w: 190 },
  { id: 'd', kind: 'tool', label: 'flow.tool', name: 'CRM', x: 430, y: 130, w: 150 },
  { id: 'e', kind: 'action', label: 'flow.action', name: 'flow.reply', x: 630, y: 130, w: 150 },
  { id: 'f', kind: 'check', label: 'flow.check', name: 'flow.confidence', x: 220, y: 260, w: 170 },
  { id: 'g', kind: 'human', label: 'flow.human', name: 'flow.handoff', x: 430, y: 260, w: 170 },
] as const;

const FLOW_EDGES = [
  { id: 'ab', d: 'M170,162 L220,162' },
  { id: 'bc', d: 'M370,162 C400,162 400,52 430,52' },
  { id: 'bd', d: 'M370,162 L430,162' },
  { id: 'de', d: 'M580,162 L630,162' },
  { id: 'bf', d: 'M295,194 L295,260' },
  { id: 'fg', d: 'M390,292 L430,292' },
];

// Mesma geometria do fluxo de agente, com rótulos de automação (documento entrando no ERP).
const FLOW_PRESETS: Record<string, Partial<Record<(typeof FLOW_NODES)[number]['id'], { label: string; name: string }>>> = {
  agent: {},
  automation: {
    a: { label: 'flow.trigger', name: 'flow.auto.email' },
    b: { label: 'flow.ai', name: 'flow.auto.extract' },
    c: { label: 'flow.tool', name: 'flow.auto.invoice' },
    d: { label: 'flow.tool', name: 'ERP' },
    e: { label: 'flow.action', name: 'flow.auto.order' },
    f: { label: 'flow.check', name: 'flow.auto.missing' },
    g: { label: 'flow.human', name: 'flow.auto.approve' },
  },
};

function flowText(value: string): string {
  return value.startsWith('flow.') ? t(value as Parameters<typeof t>[0]) : value;
}

export function FlowCanvas({ preset = 'agent' }: { preset?: 'agent' | 'automation' }) {
  const overrides = FLOW_PRESETS[preset] ?? {};
  const nodes = FLOW_NODES.map((node) => ({ ...node, ...overrides[node.id] }));

  return (
    <div className="flow-canvas" aria-hidden="true">
      <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
        {FLOW_EDGES.map((edge) => (
          <path key={edge.id} className={`flow-wire w-${edge.id}`} d={edge.d} pathLength={1} />
        ))}
        {nodes.map((node) => (
          <g key={node.id} className={`flow-node c-${node.id} is-${node.kind}`} transform={`translate(${node.x} ${node.y})`}>
            <g className="flow-node-in">
              <rect width={node.w} height="64" rx="14" />
              <rect className="flow-node-dot" x="16" y="16" width="10" height="10" rx="3" />
              <text className="flow-node-kind" x="34" y="25">{flowText(node.label)}</text>
              <text className="flow-node-name" x="16" y="48">{flowText(node.name)}</text>
            </g>
          </g>
        ))}
        <path className="flow-cursor" d="M0,0 L0,16 L4.5,12 L7.5,19 L10,18 L7,11 L13,11 Z" />
      </svg>
    </div>
  );
}

// RAG em um ciclo de 12s: pergunta, varredura na base, trechos recuperados, modelo, resposta com fonte.
// Classes rag-* casam com as keyframes de site.css; hits são os índices dos documentos que acendem na varredura.
const RAG_COLS = 3;
const RAG_ROWS = 4;
const RAG_HITS: Record<number, number> = { 2: 1, 4: 2, 11: 3 };
const RAG_WIRES = [
  { id: 'h1', d: 'M392,89 C416,89 416,200 440,200' },
  { id: 'h2', d: 'M330,163 C400,163 400,200 440,200' },
  { id: 'h3', d: 'M392,311 C416,311 416,200 440,200' },
];

function RagNode({ className, x, y, w, kind, label, name }: { className: string; x: number; y: number; w: number; kind: string; label: string; name: string }) {
  return (
    <g className={`flow-node ${className} is-${kind}`} transform={`translate(${x} ${y})`}>
      <g className="flow-node-in">
        <rect width={w} height="64" rx="14" />
        <rect className="flow-node-dot" x="16" y="16" width="10" height="10" rx="3" />
        <text className="flow-node-kind" x="34" y="25">{flowText(label)}</text>
        <text className="flow-node-name" x="16" y="48">{flowText(name)}</text>
      </g>
    </g>
  );
}

export function RagCanvas() {
  return (
    <div className="flow-canvas is-rag" aria-hidden="true">
      <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
        <defs>
          <linearGradient id="rag-scan-fade" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stopColor="#e9e9e4" stopOpacity="0" />
            <stop offset="1" stopColor="#e9e9e4" stopOpacity="0.14" />
          </linearGradient>
        </defs>
        <path className="flow-wire rag-w-q" d="M180,200 L220,200" pathLength={1} />
        {RAG_WIRES.map((wire) => (
          <path key={wire.id} className={`flow-wire rag-w-${wire.id}`} d={wire.d} pathLength={1} />
        ))}
        <path className="flow-wire rag-w-a" d="M600,200 L620,200" pathLength={1} />
        <RagNode className="rag-q" x={0} y={168} w={180} kind="trigger" label="flow.rag.question" name="flow.rag.ask" />
        <g className="rag-base">
          <text className="flow-node-kind" x="220" y="40">{flowText('flow.rag.docs')}</text>
          {Array.from({ length: RAG_COLS * RAG_ROWS }, (_, index) => {
            const hit = RAG_HITS[index];
            return (
              <g key={index} className="rag-doc" transform={`translate(${220 + (index % RAG_COLS) * 62} ${59 + Math.floor(index / RAG_COLS) * 74})`}>
                <rect className="rag-doc-sheet" width="48" height="60" rx="6" />
                <rect className="rag-doc-bar" x="9" y="14" width="30" height="3" rx="1.5" />
                <rect className="rag-doc-bar" x="9" y="24" width="24" height="3" rx="1.5" />
                <rect className="rag-doc-bar" x="9" y="34" width="28" height="3" rx="1.5" />
                <rect className="rag-doc-bar" x="9" y="44" width="18" height="3" rx="1.5" />
                {hit ? <rect className={`rag-hit rag-hit-${hit}`} width="48" height="60" rx="6" /> : null}
              </g>
            );
          })}
          <g className="rag-scan">
            <rect x="212" y="35" width="188" height="24" fill="url(#rag-scan-fade)" />
            <rect className="rag-scan-line" x="212" y="58" width="188" height="1.5" />
          </g>
          <text className="flow-node-kind rag-search" x="220" y="372">{flowText('flow.rag.search')}</text>
        </g>
        <RagNode className="rag-m" x={440} y={168} w={160} kind="agent" label="flow.rag.model" name="flow.rag.context" />
        <g className="flow-node rag-a is-action" transform="translate(620 112)">
          <g className="flow-node-in">
            <rect width="180" height="176" rx="14" />
            <rect className="flow-node-dot" x="16" y="16" width="10" height="10" rx="3" />
            <text className="flow-node-kind" x="34" y="25">{flowText('flow.rag.answer')}</text>
            <rect className="rag-line rag-line-1" x="16" y="44" width="148" height="6" rx="3" />
            <rect className="rag-line rag-line-2" x="16" y="60" width="132" height="6" rx="3" />
            <rect className="rag-line rag-line-3" x="16" y="76" width="92" height="6" rx="3" />
            <g className="rag-cite rag-cite-1" transform="translate(16 100)">
              <rect width="148" height="24" rx="8" />
              <text x="10" y="16">[1] {flowText('flow.rag.doc1')}</text>
            </g>
            <g className="rag-cite rag-cite-2" transform="translate(16 132)">
              <rect width="148" height="24" rx="8" />
              <text x="10" y="16">[2] {flowText('flow.rag.doc2')}</text>
            </g>
          </g>
        </g>
      </svg>
    </div>
  );
}

// Workflow no estilo do editor do n8n em um ciclo de 14s: montagem nó a nó (0-44%) e execução (48-70%),
// com nós e fios ficando verdes e "1 item" nas conexões. O ramo false do IF não executa.
// Cada elemento animado recebe n8n-{tipo}-{início%}; as keyframes são geradas a partir de N8N_STEPS.
const N8N_STEPS = {
  pop: [2, 9, 15, 17, 23, 30, 34, 41],
  draw: [6, 20, 27, 31, 38, 49, 56, 61, 66],
  fade: [13, 27, 31, 48, 51, 53, 54, 55, 58, 60, 63, 65, 68, 70],
} as const;

const N8N_FRAMES: Record<keyof typeof N8N_STEPS, (at: number) => string> = {
  pop: (at) => `0%, ${at}% { opacity: 0; transform: scale(0.86); } ${at + 3}%, 90% { opacity: 1; transform: none; } 96%, 100% { opacity: 0; transform: none; }`,
  draw: (at) => `0%, ${at}% { stroke-dashoffset: 1; opacity: 1; } ${at + 3}%, 90% { stroke-dashoffset: 0; opacity: 1; } 96%, 100% { stroke-dashoffset: 0; opacity: 0; }`,
  fade: (at) => `0%, ${at}% { opacity: 0; } ${at + 2}%, 90% { opacity: 1; } 96%, 100% { opacity: 0; }`,
};

const N8N_CSS = (Object.keys(N8N_STEPS) as (keyof typeof N8N_STEPS)[])
  .flatMap((kind) => N8N_STEPS[kind].map((at) => `.n8n-${kind}-${at} { animation-name: n8n-${kind}-${at}; } @keyframes n8n-${kind}-${at} { ${N8N_FRAMES[kind](at)} }`))
  .join('\n');

function n8n(kind: keyof typeof N8N_STEPS, at: number) {
  return `n8n-anim n8n-${kind}-${at}`;
}

const N8N_ICONS = {
  mail: <><rect x="-11" y="-8" width="22" height="16" rx="2" /><path d="M-11,-7 L0,1 L11,-7" /></>,
  agent: <><rect x="-9" y="-6" width="18" height="14" rx="4" /><path d="M0,-6 V-10" /><circle cx="0" cy="-11.5" r="1.5" /><circle cx="-4" cy="1" r="1.2" /><circle cx="4" cy="1" r="1.2" /></>,
  branch: <path d="M-10,0 H-2 C4,0 4,-8 10,-8 M-2,0 C4,0 4,8 10,8" />,
  http: <><circle r="10" /><ellipse rx="4" ry="10" /><path d="M-10,0 H10" /></>,
  slack: <path d="M-3,-10 L-6,10 M5,-10 L2,10 M-9,-4 H9 M-10,4 H8" />,
  model: <path d="M0,-9 C1,-3 3,-1 9,0 C3,1 1,3 0,9 C-1,3 -3,1 -9,0 C-3,-1 -1,-3 0,-9 Z" />,
  db: <><ellipse cy="-7" rx="8" ry="3" /><path d="M-8,-7 V7 A8,3 0 0 0 8,7 V-7 M-8,0 A8,3 0 0 0 8,0" /></>,
};

const N8N_TRIGGER_SHAPE = 'M32,0 H52 A12,12 0 0 1 64,12 V52 A12,12 0 0 1 52,64 H32 A32,32 0 0 1 32,0 Z';

function N8nNode({ x, y, icon, name, sub, at, ok, trigger, out = true }: { x: number; y: number; icon: keyof typeof N8N_ICONS; name: string; sub: string; at: number; ok?: number; trigger?: boolean; out?: boolean }) {
  return (
    <g transform={`translate(${x} ${y})`}>
      <g className={n8n('pop', at)}>
        {trigger ? (
          <>
            <path className="n8n-bolt" d="M-6,25 L-12,33 H-8 L-10,39 L-4,31 H-8 Z" />
            <path className="n8n-node" d={N8N_TRIGGER_SHAPE} />
          </>
        ) : <rect className="n8n-node" width="64" height="64" rx="12" />}
        <g className="n8n-icon" transform="translate(32 32)">{N8N_ICONS[icon]}</g>
        {out ? <circle className="n8n-handle" cx="68" cy="32" r="4" /> : null}
        <text className="n8n-name" x="32" y="84">{name}</text>
        <text className="n8n-sub" x="32" y="99">{flowText(sub)}</text>
      </g>
      {ok ? (
        <g className={n8n('fade', ok)}>
          {trigger ? <path className="n8n-ok" d={N8N_TRIGGER_SHAPE} /> : <rect className="n8n-ok" width="64" height="64" rx="12" />}
          <path className="n8n-check" d="M48,52 L51,55 L56,49" />
        </g>
      ) : null}
    </g>
  );
}

function N8nSubNode({ x, icon, name, at, ok }: { x: number; icon: keyof typeof N8N_ICONS; name: string; at: number; ok: number }) {
  return (
    <g transform={`translate(${x} 290)`}>
      <g className={n8n('pop', at)}>
        <circle className="n8n-node" r="24" />
        <g className="n8n-icon">{N8N_ICONS[icon]}</g>
        <text className="n8n-name" y="44">{name}</text>
      </g>
      <circle className={`n8n-ok ${n8n('fade', ok)}`} r="24" />
    </g>
  );
}

function N8nWire({ d, at, run, item }: { d: string; at: number; run?: number; item?: { x: number; y: number; at: number } }) {
  return (
    <>
      <path className={`n8n-wire ${n8n('draw', at)}`} d={d} pathLength={1} />
      {run ? <path className={`n8n-wire is-run ${n8n('draw', run)}`} d={d} pathLength={1} /> : null}
      {item ? <text className={`n8n-item ${n8n('fade', item.at)}`} x={item.x} y={item.y}>{flowText('flow.n8n.item')}</text> : null}
    </>
  );
}

export function N8nCanvas() {
  return (
    <div className="flow-canvas is-n8n" aria-hidden="true">
      <style>{N8N_CSS}</style>
      <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
        <N8nWire d="M78,150 L130,150" at={6} run={49} item={{ x: 104, y: 142, at: 51 }} />
        <N8nWire d="M284,150 L340,150" at={20} run={56} item={{ x: 312, y: 142, at: 58 }} />
        <N8nWire d="M408,138 C444,138 444,92 480,92" at={27} run={61} item={{ x: 452, y: 102, at: 63 }} />
        <N8nWire d="M408,162 C444,162 444,228 480,228" at={31} />
        <N8nWire d="M548,92 L620,92" at={38} run={66} item={{ x: 584, y: 84, at: 68 }} />
        <text className={`n8n-branch ${n8n('fade', 27)}`} x="414" y="130">true</text>
        <text className={`n8n-branch ${n8n('fade', 31)}`} x="414" y="180">false</text>
        <path className={`n8n-sub-wire ${n8n('fade', 13)}`} d="M170,208 V266 M240,208 V266" />

        <N8nNode x={10} y={118} icon="mail" name="Gmail" sub="flow.auto.email" at={2} ok={48} trigger />

        <g transform="translate(130 118)">
          <g className={n8n('pop', 9)}>
            <rect className="n8n-node" width="150" height="64" rx="10" />
            <g className="n8n-icon" transform="translate(26 33)">{N8N_ICONS.agent}</g>
            <text className="n8n-title" x="48" y="30">AI Agent</text>
            <text className="n8n-sub is-start" x="48" y="46">{flowText('flow.auto.extract')}</text>
            <circle className="n8n-handle" cx="154" cy="32" r="4" />
            <path className="n8n-handle" d="M40,60 L44,64 L40,68 L36,64 Z M110,60 L114,64 L110,68 L106,64 Z" />
            <text className="n8n-port" x="40" y="82">Chat Model</text>
            <text className="n8n-port" x="110" y="82">Tool</text>
          </g>
          <g className={n8n('fade', 55)}>
            <rect className="n8n-ok" width="150" height="64" rx="10" />
            <path className="n8n-check" d="M134,52 L137,55 L142,49" />
          </g>
        </g>

        <N8nSubNode x={170} icon="model" name="OpenAI" at={15} ok={53} />
        <N8nSubNode x={240} icon="db" name="Postgres" at={17} ok={54} />

        <g transform="translate(340 118)">
          <g className={n8n('pop', 23)}>
            <rect className="n8n-node" width="64" height="64" rx="12" />
            <g className="n8n-icon" transform="translate(32 32)">{N8N_ICONS.branch}</g>
            <circle className="n8n-handle" cx="68" cy="20" r="4" />
            <circle className="n8n-handle" cx="68" cy="44" r="4" />
            <text className="n8n-name" x="32" y="84">If</text>
            <text className="n8n-sub" x="32" y="99">{flowText('flow.n8n.complete')}</text>
          </g>
          <g className={n8n('fade', 60)}>
            <rect className="n8n-ok" width="64" height="64" rx="12" />
            <path className="n8n-check" d="M48,52 L51,55 L56,49" />
          </g>
        </g>

        <N8nNode x={480} y={60} icon="http" name="HTTP Request" sub="flow.n8n.erp" at={30} ok={65} />
        <N8nNode x={480} y={196} icon="slack" name="Slack" sub="flow.n8n.approve" at={34} out={false} />
        <N8nNode x={620} y={60} icon="mail" name="Gmail" sub="flow.n8n.confirm" at={41} ok={70} out={false} />
      </svg>
    </div>
  );
}

// LLMs dentro do sistema em um ciclo de 18s: cada tarefa da janela sai como pulso pelo gateway até um provedor,
// a resposta volta em oliva e preenche o campo com a etiqueta do modelo. Tarefas começam em 0%, 28% e 56%.
const LLM_PROVIDERS = [
  { id: 'gpt', name: 'ChatGPT', vendor: 'OpenAI', y: 70 },
  { id: 'claude', name: 'Claude', vendor: 'Anthropic', y: 168 },
  { id: 'gemini', name: 'Gemini', vendor: 'Google', y: 266 },
] as const;

const LLM_TASKS = [
  { label: 'flow.llm.t1', provider: 1, d: 'M300,200 L580,200' },
  { label: 'flow.llm.t2', provider: 0, d: 'M300,200 L500,200 C540,200 540,102 580,102' },
  { label: 'flow.llm.t3', provider: 2, d: 'M300,200 L500,200 C540,200 540,298 580,298' },
] as const;

const LLM_STARTS = [0, 28, 56];

const LLM_CSS = [
  ...LLM_STARTS.flatMap((s, k) => {
    const [a, b, c, d] = [s + 2, s + 9, s + 13, s + 20];
    return [
      `@keyframes llm-row-${k} { 0%, ${s + 1}% { opacity: 0; } ${s + 2}%, ${s + 26}% { opacity: 1; } ${s + 28}%, 100% { opacity: 0; } }`,
      `@keyframes llm-out-${k} { 0%, ${a}% { stroke-dashoffset: 0.08; opacity: 0; } ${a + 0.3}% { opacity: 1; } ${b}% { stroke-dashoffset: -1; opacity: 1; } ${b + 0.3}%, 100% { stroke-dashoffset: -1; opacity: 0; } }`,
      `@keyframes llm-back-${k} { 0%, ${c}% { stroke-dashoffset: -1; opacity: 0; } ${c + 0.3}% { opacity: 1; } ${d}% { stroke-dashoffset: 0.08; opacity: 1; } ${d + 0.3}%, 100% { stroke-dashoffset: 0.08; opacity: 0; } }`,
      `@keyframes llm-ring-${k} { 0%, ${b - 1}% { opacity: 0; } ${b}%, ${c + 1}% { opacity: 1; } ${c + 3}%, 100% { opacity: 0; } }`,
      `@keyframes llm-bar-${k} { 0%, ${d}% { transform: scaleX(0); opacity: 1; } ${d + 6}%, 92% { transform: scaleX(1); opacity: 1; } 97%, 100% { transform: scaleX(1); opacity: 0; } }`,
      `@keyframes llm-chip-${k} { 0%, ${d + 5}% { opacity: 0; } ${d + 7}%, 92% { opacity: 1; } 97%, 100% { opacity: 0; } }`,
      `.llm-row-${k} { animation-name: llm-row-${k}; } .llm-out-${k} { animation-name: llm-out-${k}; } .llm-back-${k} { animation-name: llm-back-${k}; }`,
      `.llm-ring-${k} { animation-name: llm-ring-${k}; } .llm-bar-${k} { animation-name: llm-bar-${k}; } .llm-chip-${k} { animation-name: llm-chip-${k}; }`,
    ];
  }),
  `@keyframes llm-gw { 0% { opacity: 0; } ${LLM_STARTS.map((s) => `${s + 3}% { opacity: 0; } ${s + 4}%, ${s + 6}% { opacity: 1; } ${s + 7}% { opacity: 0; } ${s + 16}% { opacity: 0; } ${s + 17}%, ${s + 19}% { opacity: 1; } ${s + 20}% { opacity: 0; }`).join(' ')} 100% { opacity: 0; } }`,
].join('\n');

export function LlmCanvas() {
  return (
    <div className="flow-canvas is-llm" aria-hidden="true">
      <style>{LLM_CSS}</style>
      <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
        <g className="llm-window">
          <rect className="llm-frame" width="300" height="288" y="56" rx="14" />
          <circle cx="20" cy="76" r="3.5" />
          <circle cx="32" cy="76" r="3.5" />
          <circle cx="44" cy="76" r="3.5" />
          <text className="flow-node-kind" x="60" y="80">{flowText('flow.llm.window')}</text>
          <path className="llm-sep" d="M0,96 H300" />
          {LLM_TASKS.map((task, k) => {
            const top = 116 + k * 72;
            const provider = LLM_PROVIDERS[task.provider];
            return (
              <g key={task.label}>
                <rect className={`llm-row-hl llm-anim llm-row-${k}`} x="8" y={top - 8} width="284" height="58" rx="10" />
                <text className="llm-task" x="20" y={top + 14}>{flowText(task.label)}</text>
                <g className={`llm-chip is-${provider.id} llm-anim llm-chip-${k}`}>
                  <rect x="196" y={top} width="84" height="20" rx="7" />
                  <text x="238" y={top + 14}>{provider.name}</text>
                </g>
                <rect className="llm-track" x="20" y={top + 28} width="260" height="6" rx="3" />
                <rect className={`llm-bar llm-anim llm-bar-${k}`} x="20" y={top + 28} width={[236, 200, 248][k]} height="6" rx="3" />
              </g>
            );
          })}
        </g>

        <path className="flow-wire" d="M300,200 L360,200" pathLength={1} />
        {LLM_PROVIDERS.map((provider) => (
          <path key={provider.id} className="flow-wire" d={`M500,200 C540,200 540,${provider.y + 32} 580,${provider.y + 32}`} pathLength={1} />
        ))}
        {LLM_TASKS.map((task, k) => (
          <g key={task.label}>
            <path className={`llm-pulse llm-anim llm-out-${k}`} d={task.d} pathLength={1} />
            <path className={`llm-pulse is-back llm-anim llm-back-${k}`} d={task.d} pathLength={1} />
          </g>
        ))}

        <g className="flow-node is-agent" transform="translate(360 168)">
          <rect width="140" height="64" rx="14" />
          <rect className="flow-node-dot" x="16" y="16" width="10" height="10" rx="3" />
          <text className="flow-node-kind" x="34" y="25">{flowText('flow.llm.kind')}</text>
          <text className="flow-node-name" x="16" y="48">Gateway LLM</text>
          <rect className="llm-ring llm-anim llm-gw" width="140" height="64" rx="14" />
        </g>
        <text className="flow-node-kind llm-gw-sub" x="430" y="256">{flowText('flow.llm.gw_sub')}</text>

        {LLM_PROVIDERS.map((provider, index) => (
          <g key={provider.id} className={`flow-node llm-provider is-${provider.id}`} transform={`translate(580 ${provider.y})`}>
            <rect width="180" height="64" rx="14" />
            <rect className="flow-node-dot" x="16" y="16" width="10" height="10" rx="3" />
            <text className="flow-node-kind" x="34" y="25">{provider.vendor}</text>
            <text className="flow-node-name" x="16" y="48">{provider.name}</text>
            {LLM_TASKS.map((task, k) => (task.provider === index ? (
              <rect key={k} className={`llm-ring llm-anim llm-ring-${k}`} width="180" height="64" rx="14" />
            ) : null))}
          </g>
        ))}
      </svg>
    </div>
  );
}

// Sistema AI-native em um ciclo de 16s: a interface se monta (0-20%), a IA processa a fila linha a linha
// com etiqueta, confiança e status (28-63%), a linha de baixa confiança vai para revisão humana,
// e o painel lateral monta um insight com gráfico e ação sugerida (64-79%).
// As keyframes sys-{tipo}-{início%} são geradas a partir das chamadas de sys() durante o render.
const SYS_FRAMES: Record<string, (at: number) => string> = {
  pop: (at) => `0%, ${at}% { opacity: 0; transform: scale(0.94); } ${at + 3}%, 90% { opacity: 1; transform: none; } 96%, 100% { opacity: 0; transform: none; }`,
  fade: (at) => `0%, ${at}% { opacity: 0; } ${at + 2}%, 90% { opacity: 1; } 96%, 100% { opacity: 0; }`,
  grow: (at) => `0%, ${at}% { transform: scaleX(0); opacity: 1; } ${at + 4}%, 90% { transform: scaleX(1); opacity: 1; } 96%, 100% { transform: scaleX(1); opacity: 0; }`,
  rise: (at) => `0%, ${at}% { transform: scaleY(0); opacity: 1; } ${at + 3}%, 90% { transform: scaleY(1); opacity: 1; } 96%, 100% { transform: scaleY(1); opacity: 0; }`,
  hl: (at) => `0%, ${at}% { opacity: 0; } ${at + 1}%, ${at + 7}% { opacity: 1; } ${at + 8}%, 100% { opacity: 0; }`,
  draw: (at) => `0%, ${at}% { stroke-dashoffset: 1; opacity: 1; } ${at + 3}%, 90% { stroke-dashoffset: 0; opacity: 1; } 96%, 100% { stroke-dashoffset: 0; opacity: 0; }`,
  wire: (at) => `0%, ${at}% { opacity: 0; } ${at + 2}%, ${at + 38}% { opacity: 1; } ${at + 41}%, 100% { opacity: 0; }`,
  note: (at) => `0%, ${at}% { opacity: 0; transform: translateY(4px); } ${at + 2}%, 38% { opacity: 1; transform: none; } 41%, 100% { opacity: 0; transform: none; }`,
  swap: (at) => `0%, ${at}% { opacity: 0; } ${at + 2}%, ${at + 28}% { opacity: 1; } ${at + 30}%, 100% { opacity: 0; }`,
};

// Registra cada sys(tipo, início) usado no render e gera só as keyframes necessárias (ciclo de 16s em .sys-anim).
function createTimeline() {
  const used = new Map<string, [string, number]>();
  const sys = (kind: keyof typeof SYS_FRAMES, at: number, extra = '') => {
    used.set(`${kind}-${at}`, [kind, at]);
    return `sys-anim sys-${kind} sys-${kind}-${at}${extra ? ` ${extra}` : ''}`;
  };
  const css = () => [...used.values()]
    .map(([kind, at]) => `.sys-${kind}-${at} { animation-name: sys-${kind}-${at}; } @keyframes sys-${kind}-${at} { ${SYS_FRAMES[kind](at)} }`)
    .join('\n');
  return { sys, css };
}

const SYS_ROWS = [
  { id: '#4821', tag: 'flow.sys.tag1', conf: '98%', review: false, bar: 96 },
  { id: '#4822', tag: 'flow.sys.tag2', conf: '94%', review: false, bar: 78 },
  { id: '#4823', tag: 'flow.sys.tag3', conf: '61%', review: true, bar: 104 },
  { id: '#4824', tag: 'flow.sys.tag4', conf: '92%', review: false, bar: 70 },
];

const SYS_CHART = [40, 64, 52, 88, 70];

const SYS_SPARK = 'M0,-6 C0.6,-2 2,-0.6 6,0 C2,0.6 0.6,2 0,6 C-0.6,2 -2,0.6 -6,0 C-2,-0.6 -0.6,-2 0,-6 Z';

export function SystemCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="20" y="28" width="770" height="344" rx="16" />
        <path className="sys-sep" d="M20,68 H790 M150,68 V372" />
        <circle className="sys-dot" cx="44" cy="48" r="3.5" />
        <circle className="sys-dot" cx="56" cy="48" r="3.5" />
        <circle className="sys-dot" cx="68" cy="48" r="3.5" />
      </g>
      <text className={sys('fade', 3, 'sys-title')} x="90" y="53">{flowText('flow.sys.app')}</text>
      <g className={sys('pop', 5)}>
        <rect className="sys-search" x="300" y="38" width="260" height="20" rx="10" />
        <path className="sys-spark" d={SYS_SPARK} transform="translate(316 48) scale(0.9)" />
        <text className="sys-mono" x="330" y="52">{flowText('flow.sys.ask')}</text>
      </g>
      {[80, 64, 72, 56].map((w, i) => (
        <rect key={w} className={sys('fade', 6 + i, i === 0 ? 'sys-nav is-active' : 'sys-nav')} x="36" y={92 + i * 30} width={w} height="8" rx="4" />
      ))}
      <text className={sys('fade', 10, 'sys-mono')} x="182" y="96">{flowText('flow.sys.queue')}</text>
      {SYS_ROWS.map((row, k) => {
        const y = 112 + k * 58;
        const s = 28 + k * 9;
        return (
          <g key={row.id}>
            <g className={sys('pop', 10 + k * 2)}>
              <rect className="sys-row" x="166" y={y} width="394" height="48" rx="10" />
              <text className="sys-mono" x="182" y={y + 20}>{row.id}</text>
              <rect className="sys-bar" x="182" y={y + 30} width={row.bar} height="6" rx="3" />
            </g>
            <rect className={sys('hl', s, 'sys-row-hl')} x="166" y={y} width="394" height="48" rx="10" />
            <path className={sys('hl', s, 'sys-spark')} d={SYS_SPARK} transform={`translate(${288} ${y + 24})`} />
            <g className={sys('fade', s + 2, 'sys-tag')}>
              <rect x="300" y={y + 14} width="84" height="20" rx="7" />
              <text x="342" y={y + 28}>{flowText(row.tag)}</text>
            </g>
            <text className={sys('fade', s + 3, row.review ? 'sys-conf is-low' : 'sys-conf')} x="396" y={y + 28}>{row.conf}</text>
            <g className={sys('fade', s + 5, row.review ? 'sys-status is-review' : 'sys-status')}>
              <rect x="436" y={y + 14} width="112" height="20" rx="7" />
              <text x="492" y={y + 28}>{flowText(row.review ? 'flow.sys.review' : 'flow.sys.auto')}</text>
            </g>
          </g>
        );
      })}
      <g className={sys('pop', 18)}>
        <rect className="sys-panel" x="576" y="80" width="200" height="280" rx="12" />
        <path className="sys-spark" d={SYS_SPARK} transform="translate(596 101)" />
        <text className="sys-mono" x="608" y="105">{flowText('flow.sys.insight')}</text>
        <path className="sys-sep" d="M592,232 H760" />
      </g>
      {SYS_CHART.map((h, i) => (
        <rect key={i} className={sys('rise', 64 + i, i === 3 ? 'sys-col is-peak' : 'sys-col')} x={600 + i * 32} y={232 - h} width="18" height={h} rx="3" />
      ))}
      {[164, 140, 96].map((w, i) => (
        <rect key={w} className={sys('grow', 70 + i * 2, 'sys-line')} x="592" y={252 + i * 16} width={w} height="6" rx="3" />
      ))}
      <g className={sys('fade', 77, 'sys-action')}>
        <rect x="592" y="310" width="120" height="26" rx="13" />
        <text x="652" y="327">{flowText('flow.sys.action')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Do rascunho ao sistema em 16s: a tela é desenhada como wireframe com anotações (2-30%) e cada região
// troca o rascunho pela versão real 40% depois de aparecer (42-57%); em seguida entram os dados (60-70%).
const WF_ROWS = [
  { id: '#1041', client: 'Metalúrgica Sul', value: 'R$ 4.280', status: 'ok' },
  { id: '#1042', client: 'Clínica Aurora', value: 'R$ 1.150', status: 'pending' },
  { id: '#1043', client: 'Transportes Lima', value: 'R$ 9.900', status: 'late' },
  { id: '#1044', client: 'Grupo Vale', value: 'R$ 2.430', status: 'ok' },
] as const;

const WF_STATUS = { ok: 'flow.wf.ok', pending: 'flow.sw.pending', late: 'flow.wf.late' } as const;

const WF_CHART = [38, 52, 44, 70, 58, 86];

export function SoftwareCanvas() {
  const { sys, css } = createTimeline();
  const wire = (at: number, extra = '') => sys('wire', at, extra);
  const real = (at: number, extra = '') => sys('pop', at + 40, extra);

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <text className={wire(2, 'sys-mono')} x="40" y="20">{flowText('flow.wf.stage1')}</text>
      <text className={real(2, 'sys-mono')} x="40" y="20">{flowText('flow.wf.stage2')}</text>

      <g className={real(2)}>
        <rect className="sys-frame" x="40" y="30" width="750" height="340" rx="16" />
        <path className="sys-sep" d="M40,72 H790 M180,72 V370" />
        <rect className="wf-logo" x="56" y="43" width="22" height="16" rx="4" />
        {[100, 150, 200].map((x) => <rect key={x} className="sys-nav" x={x} y="48" width="36" height="6" rx="3" />)}
        <circle className="wf-avatar" cx="766" cy="51" r="9" />
      </g>

      <g className={wire(2, 'wf-wire')}>
        <rect x="40" y="30" width="750" height="340" rx="16" />
        <path d="M40,72 H790 M180,72 V370" />
        <rect x="56" y="43" width="22" height="16" rx="4" />
        <path d="M56,43 L78,59 M78,43 L56,59" />
        {[100, 150, 200].map((x) => <rect key={x} className="wf-block" x={x} y="48" width="36" height="6" rx="3" />)}
        <circle cx="766" cy="51" r="9" />
      </g>

      <g className={wire(5, 'wf-wire')}>
        {[96, 72, 84, 64, 80].map((w, i) => <rect key={i} className="wf-block" x="56" y={96 + i * 28} width={w} height="8" rx="4" />)}
      </g>
      <g className={real(5)}>
        {[96, 72, 84, 64, 80].map((w, i) => <rect key={i} className={i === 0 ? 'sys-nav is-active' : 'sys-nav'} x="56" y={96 + i * 28} width={w} height="8" rx="4" />)}
      </g>

      <g className={wire(8, 'wf-wire')}>
        <rect className="wf-block" x="200" y="92" width="150" height="14" rx="4" />
        <rect x="200" y="118" width="64" height="20" rx="10" />
        <rect x="270" y="118" width="78" height="20" rx="10" />
        <rect x="354" y="118" width="74" height="20" rx="10" />
      </g>
      <g className={real(8)}>
        <text className="wf-title" x="200" y="106">{flowText('flow.sw.ui')}</text>
        <g className="wf-filter is-active">
          <rect x="200" y="118" width="64" height="20" rx="10" />
          <text x="232" y="132">{flowText('flow.wf.all')}</text>
        </g>
        <g className="wf-filter">
          <rect x="270" y="118" width="78" height="20" rx="10" />
          <text x="309" y="132">{flowText('flow.wf.pending')}</text>
        </g>
        <g className="wf-filter">
          <rect x="354" y="118" width="74" height="20" rx="10" />
          <text x="391" y="132">{flowText('flow.wf.late_pl')}</text>
        </g>
      </g>

      <g className={wire(11, 'wf-wire')}>
        <rect x="200" y="152" width="366" height="164" rx="8" />
        <path d="M200,174 H566" />
        {WF_ROWS.map((row, r) => (
          <g key={row.id}>
            <rect className="wf-block" x="212" y={190 + r * 34} width="40" height="8" rx="4" />
            <rect className="wf-block" x="268" y={190 + r * 34} width="110" height="8" rx="4" />
            <rect className="wf-block" x="420" y={190 + r * 34} width="52" height="8" rx="4" />
            <rect x="492" y={185 + r * 34} width="64" height="18" rx="6" />
          </g>
        ))}
      </g>
      <g className={real(11)}>
        <rect className="sys-row" x="200" y="152" width="366" height="164" rx="8" />
        <path className="sys-sep" d="M200,174 H566" />
        {WF_ROWS.map((row, r) => (
          <g key={row.id}>
            <text className="sys-mono" x="212" y={198 + r * 34}>{row.id}</text>
            <text className="wf-cell" x="268" y={198 + r * 34}>{row.client}</text>
            <text className="sys-mono" x="420" y={198 + r * 34}>{row.value}</text>
          </g>
        ))}
      </g>
      {WF_ROWS.map((row, r) => (
        <g key={row.id} className={sys('fade', 62 + r * 2, `wf-status is-${row.status}`)}>
          <rect x="492" y={185 + r * 34} width="64" height="18" rx="6" />
          <text x="524" y={198 + r * 34}>{flowText(WF_STATUS[row.status])}</text>
        </g>
      ))}

      <g className={wire(14, 'wf-wire')}>
        {[582, 682].map((x) => (
          <g key={x}>
            <rect x={x} y="92" width="92" height="60" rx="10" />
            <rect className="wf-block" x={x + 12} y="106" width="40" height="6" rx="3" />
            <rect className="wf-block" x={x + 12} y="122" width="56" height="16" rx="4" />
          </g>
        ))}
      </g>
      <g className={real(14)}>
        <rect className="sys-row" x="582" y="92" width="92" height="60" rx="10" />
        <rect className="sys-row" x="682" y="92" width="92" height="60" rx="10" />
        <text className="sys-mono" x="594" y="112">{flowText('flow.wf.open')}</text>
        <text className="sys-mono" x="694" y="112">{flowText('flow.wf.ontime')}</text>
      </g>
      <text className={sys('fade', 60, 'wf-kpi')} x="594" y="140">128</text>
      <text className={sys('fade', 62, 'wf-kpi')} x="694" y="140">94%</text>

      <g className={wire(17, 'wf-wire')}>
        <rect x="582" y="164" width="192" height="152" rx="10" />
        <path d="M582,164 L774,316 M774,164 L582,316" />
      </g>
      <g className={real(17)}>
        <rect className="sys-row" x="582" y="164" width="192" height="152" rx="10" />
        <path className="sys-sep" d="M598,300 H758" />
      </g>
      {WF_CHART.map((h, i) => (
        <rect key={i} className={sys('rise', 60 + i, i === 5 ? 'sys-col is-peak' : 'sys-col')} x={604 + i * 26} y={300 - h} width="16" height={h} rx="3" />
      ))}

      <g className={sys('note', 22, 'wf-note')}>
        <path d="M434,128 H452" />
        <text x="458" y="132">{flowText('flow.wf.n1')}</text>
      </g>
      <g className={sys('note', 25, 'wf-note')}>
        <path d="M212,324 V334" />
        <text x="206" y="350">{flowText('flow.wf.n2')}</text>
      </g>
      <g className={sys('note', 28, 'wf-note')}>
        <path d="M600,324 V334" />
        <text x="594" y="350">{flowText('flow.wf.n3')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// SaaS multi-tenant em 16s: a base comum (login, permissões, planos, cobrança) e o painel de assinaturas
// sobem primeiro (1-10%); cada cliente assina em 14/30/46%, ganha o próprio espaço com plano, e o painel
// registra a barra de receita e a fatura paga; a nota de isolamento e o total entram em 60-64%.
const SAAS_TENANTS = [
  { name: 'Clínica Aurora', plan: 'Starter', price: 'R$ 290', x: 40, y: 36 },
  { name: 'Metalúrgica Sul', plan: 'Pro', price: 'R$ 690', x: 110, y: 96 },
  { name: 'Grupo Vale', plan: 'Business', price: 'R$ 1.490', x: 180, y: 156 },
];

const SAAS_STARTS = [14, 30, 46];

const SAAS_CHIPS = [
  { key: 'flow.saas.login', x: 196, w: 48 },
  { key: 'flow.saas.roles', x: 250, w: 80 },
  { key: 'flow.saas.plans', x: 336, w: 56 },
  { key: 'flow.saas.billing', x: 398, w: 72 },
];

const SAAS_CHART = [24, 30, 36, 52, 70, 90];

export function SaasCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="326" width="440" height="44" rx="12" />
        <text className="sys-mono" x="56" y="352">{flowText('flow.saas.base')}</text>
      </g>
      {SAAS_CHIPS.map((chip, i) => (
        <g key={chip.key} className={sys('fade', 3 + i, 'sys-tag')}>
          <rect x={chip.x} y="338" width={chip.w} height="20" rx="7" />
          <text x={chip.x + chip.w / 2} y="352">{flowText(chip.key)}</text>
        </g>
      ))}

      {SAAS_TENANTS.map((tenant, k) => {
        const s = SAAS_STARTS[k];
        return (
          <g key={tenant.name} transform={`translate(${tenant.x} ${tenant.y})`}>
            <g className={sys('pop', s)}>
              <rect className="sys-frame" width="270" height="150" rx="12" />
              <path className="sys-sep" d="M0,36 H270 M70,36 V150" />
              <rect className={`saas-av is-${k + 1}`} x="12" y="9" width="18" height="18" rx="5" />
              <text className="wf-cell" x="38" y="22">{tenant.name}</text>
              {[44, 32, 38].map((w, i) => <rect key={i} className={i === 0 ? 'sys-nav is-active' : 'sys-nav'} x="14" y={52 + i * 20} width={w} height="6" rx="3" />)}
              <rect className="sys-row" x="84" y="84" width="172" height="22" rx="6" />
              <rect className="sys-row" x="84" y="114" width="172" height="22" rx="6" />
            </g>
            <rect className={sys('hl', s, 'sys-row-hl')} width="270" height="150" rx="12" />
            <rect className={sys('grow', s + 2, 'sys-line')} x="84" y="50" width="150" height="6" rx="3" />
            <rect className={sys('grow', s + 3, 'sys-line')} x="84" y="64" width="110" height="6" rx="3" />
            <g className={sys('fade', s + 4, 'sys-tag')}>
              <rect x="190" y="9" width="68" height="18" rx="7" />
              <text x="224" y="22">{tenant.plan}</text>
            </g>
          </g>
        );
      })}

      <g className={sys('pop', 4)}>
        <rect className="sys-frame" x="510" y="36" width="270" height="334" rx="14" />
        <path className="sys-sep" d="M510,76 H780 M528,212 H762" />
      </g>
      <text className={sys('fade', 6, 'sys-title')} x="528" y="62">{flowText('flow.saas.subs')}</text>
      <text className={sys('fade', 7, 'sys-mono')} x="528" y="98">{flowText('flow.saas.mrr')}</text>
      {SAAS_CHART.map((h, i) => {
        const at = i < 3 ? 8 + i : SAAS_STARTS[i - 3] + 6;
        return <rect key={i} className={sys('rise', at, i === 5 ? 'sys-col is-peak' : 'sys-col')} x={534 + i * 38} y={196 - h} width="22" height={h} rx="3" />;
      })}
      <g className={sys('fade', 64, 'sys-status')}>
        <rect x="682" y="48" width="84" height="20" rx="7" />
        <text x="724" y="62">{flowText('flow.saas.new')}</text>
      </g>

      {SAAS_TENANTS.map((tenant, k) => {
        const s = SAAS_STARTS[k];
        const y = 226 + k * 44;
        return (
          <g key={tenant.name}>
            <g className={sys('pop', s + 6)}>
              <rect className="sys-row" x="528" y={y} width="234" height="32" rx="8" />
              <text className="wf-cell" x="540" y={y + 20}>{tenant.name}</text>
              <text className="sys-mono" x="652" y={y + 20}>{tenant.price}</text>
            </g>
            <g className={sys('fade', s + 10, 'wf-status is-ok')}>
              <rect x="712" y={y + 7} width="42" height="18" rx="6" />
              <text x="733" y={y + 20}>{flowText('flow.saas.paid')}</text>
            </g>
          </g>
        );
      })}

      <g className={sys('fade', 60, 'wf-note')}>
        <text x="40" y="392">{flowText('flow.saas.note')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Modernização sem parar a operação em 16s: a calculadora legada é varrida da esquerda para a direita
// em três etapas (entradas 12-20%, cálculo 34-42%, gráfico 56-64%); a versão nova aparece atrás da
// linha de varredura e o selo "Em produção" fica aceso o ciclo inteiro. Keyframes mod-* em site.css.
const MOD_INPUTS = [
  { label: 'd [mm]', value: '2.00', modern: 'd · mm', knob: 0.18 },
  { label: 'De [mm]', value: '18.00', modern: 'De · mm', knob: 0.3 },
  { label: 'n', value: '6', modern: 'n', knob: 0.14 },
  { label: 'L0 [mm]', value: '50.00', modern: 'L0 · mm', knob: 0.46 },
];

const MOD_TABLE = ['R  = 6.632 N/mm', 'F1 = 66.3 N', 'F2 = 119.4 N', 'Lc = 16.00 mm', 'Fc = 225.5 N', 'TAU= 1347'];

const MOD_MODULES = [
  { key: 'flow.mod.m1', at: 20 },
  { key: 'flow.mod.m2', at: 42 },
  { key: 'flow.mod.m3', at: 64 },
];

export function ModernizeCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className="mod-stage">
        <g className="mod-legacy">
          <rect className="mod-l-win" x="40" y="40" width="750" height="262" />
          <rect className="mod-l-bar" x="40" y="40" width="750" height="22" />
          <text className="mod-l-title" x="48" y="55">CALC.EXE - v2.3</text>
          {[738, 754, 770].map((x) => <rect key={x} className="mod-l-btn" x={x} y="44" width="14" height="14" />)}
          {MOD_INPUTS.map((input, i) => (
            <g key={input.label}>
              <text className="mod-l-text" x="56" y={104 + i * 40}>{input.label}</text>
              <rect className="mod-l-input" x="140" y={91 + i * 40} width="120" height="18" />
              <text className="mod-l-text" x="146" y={104 + i * 40}>{input.value}</text>
            </g>
          ))}
          <rect className="mod-l-btn" x="56" y="262" width="104" height="24" />
          <text className="mod-l-text is-center" x="108" y="278">{flowText('flow.mod.calc')}</text>
          <path className="mod-l-grid" d="M300,108 H528 M300,132 H528 M300,156 H528 M300,180 H528 M300,204 H528 M300,228 H528 M300,84 V228 H528 V84 Z" />
          {MOD_TABLE.map((row, i) => <text key={row} className="mod-l-text" x="306" y={100 + i * 24}>{row}</text>)}
          <rect className="mod-l-input" x="556" y="84" width="218" height="200" />
          <path className="mod-l-grid" d="M556,134 H774 M556,184 H774 M556,234 H774 M610,84 V284 M665,84 V284 M720,84 V284" />
          <polyline className="mod-l-plot" points="566,104 590,104 590,124 616,124 616,146 640,146 640,168 664,168 664,190 690,190 690,214 714,214 714,238 740,238 740,262 764,262" />
        </g>

        <g className="mod-modern">
          <rect className="sys-frame" x="40" y="40" width="750" height="262" rx="14" />
          <path className="sys-sep" d="M40,72 H790" />
          {[56, 68, 80].map((cx) => <circle key={cx} className="sys-dot" cx={cx} cy="56" r="3.5" />)}
          <text className="sys-title" x="98" y="61">{flowText('flow.mod.title')}</text>
          <g className="sys-action">
            <rect x="714" y="47" width="60" height="18" rx="9" />
            <text x="744" y="60">PDF</text>
          </g>
          {MOD_INPUTS.map((input, i) => {
            const y = 88 + i * 50;
            return (
              <g key={input.label}>
                <text className="sys-mono" x="56" y={y + 8}>{input.modern}</text>
                <rect className="sys-search" x="56" y={y + 16} width="214" height="24" rx="7" />
                <text className="wf-cell" x="66" y={y + 32}>{input.value.replace('.', ',')}</text>
                <rect className="sys-nav" x="140" y={y + 27} width="118" height="3" rx="1.5" />
                <rect className="mod-track" x="140" y={y + 27} width={118 * input.knob} height="3" rx="1.5" />
                <circle className="mod-knob" cx={140 + 118 * input.knob} cy={y + 28.5} r="5" />
              </g>
            );
          })}
          {[
            { label: 'flow.mod.k', value: '6,632', unit: 'N/mm', y: 88 },
            { label: 'flow.mod.f1', value: '66,3', unit: 'N', y: 156 },
          ].map((kpi) => (
            <g key={kpi.label}>
              <rect className="sys-row" x="300" y={kpi.y} width="228" height="60" rx="10" />
              <text className="sys-mono" x="314" y={kpi.y + 20}>{flowText(kpi.label)}</text>
              <text className="wf-kpi" x="314" y={kpi.y + 47}>{kpi.value}</text>
              <text className="sys-mono" x={kpi.value.length > 4 ? 382 : 366} y={kpi.y + 47}>{kpi.unit}</text>
            </g>
          ))}
          <rect className="sys-row mod-ok-card" x="300" y="224" width="228" height="60" rx="10" />
          <text className="sys-mono" x="314" y="244">{flowText('flow.mod.life')}</text>
          <text className="mod-ok" x="314" y="270">{flowText('flow.mod.ok')}</text>
          <rect className="sys-row" x="556" y="84" width="218" height="200" rx="10" />
          <path className="sys-sep" d="M572,268 H760 M572,104 V268" />
          <path className="mod-zone" d="M572,104 H604 V268 H572 Z" />
          <path className="mod-curve" d="M576,112 C630,150 690,206 756,262" />
          <circle className="mod-point" cx="664" cy="178" r="4.5" />
          <circle className="mod-point is-alt" cx="712" cy="222" r="4.5" />
        </g>

        <g className="mod-sweep">
          <rect x="39" y="32" width="2" height="278" rx="1" />
        </g>
      </g>

      <g className="mod-live">
        <rect x="40" y="318" width="128" height="24" rx="12" />
        <circle className="mod-pulse" cx="56" cy="330" r="4" />
        <text x="68" y="334">{flowText('flow.mod.live')}</text>
      </g>
      {MOD_MODULES.map((module, i) => (
        <g key={module.key} className={sys('fade', module.at, 'sys-tag mod-chip')}>
          <rect x={184 + i * 122} y="318" width="112" height="24" rx="8" />
          <path d={`M${198 + i * 122},330 l3,3 l6,-7`} />
          <text x={246 + i * 122} y="334">{flowText(module.key)}</text>
        </g>
      ))}
    </svg>
  );

  return (
    <div className="flow-canvas is-sys is-mod" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Funil de vendas em 16s: o card do contato é arrastado de coluna em coluna (14-18%, 30-34%, 46-50%,
// 62-66%), os pontos de progresso acendem a cada etapa e, ao chegar em "Ganho", o pedido é criado no ERP (70%).
// O deslocamento do card e o cursor usam as keyframes pipe-* em site.css.
const PIPE_COLUMNS = [
  { key: 'flow.pipe.c1', count: '8', ghosts: 2 },
  { key: 'flow.pipe.c2', count: '5', ghosts: 2 },
  { key: 'flow.pipe.c3', count: '3', ghosts: 1 },
  { key: 'flow.pipe.c4', count: '2', ghosts: 1 },
  { key: 'flow.pipe.c5', count: '12', ghosts: 2 },
];

const PIPE_STEPS = [2, 18, 34, 50, 66];

export function PipelineCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className="pipe-stage">
        <rect className="sys-frame" x="40" y="40" width="750" height="290" rx="14" />
        <text className="sys-title" x="60" y="66">{flowText('flow.pipe.title')}</text>
        <path className="sys-sep" d="M40,80 H790" />
        {PIPE_COLUMNS.map((column, i) => {
          const x = 52 + i * 148;
          return (
            <g key={column.key}>
              <rect className="sys-row" x={x} y="90" width="138" height="228" rx="10" />
              <text className="sys-mono" x={x + 10} y="106">{flowText(column.key)}</text>
              <text className="sys-mono pipe-count" x={x + 128} y="106">{column.count}</text>
              <rect className="pipe-slot" x={x + 6} y="116" width="126" height="62" rx="8" />
              {Array.from({ length: column.ghosts }, (_, k) => (
                <g key={k}>
                  <rect className="pipe-ghost" x={x + 6} y={188 + k * 64} width="126" height="54" rx="8" />
                  <rect className="sys-nav" x={x + 16} y={203 + k * 64} width={70 - k * 12} height="6" rx="3" />
                  <rect className="sys-nav" x={x + 16} y={219 + k * 64} width="44" height="6" rx="3" />
                </g>
              ))}
            </g>
          );
        })}

        <g className="pipe-move">
          <rect className="pipe-card" x="58" y="116" width="126" height="62" rx="8" />
          <rect className={sys('fade', 66, 'pipe-won')} x="58" y="116" width="126" height="62" rx="8" />
          <circle className="pipe-avatar" cx="74" cy="134" r="7" />
          <text className="wf-cell" x="86" y="138">Metalúrgica Sul</text>
          <text className="sys-mono" x="68" y="164">R$ 4.280</text>
          {PIPE_STEPS.map((at, k) => (
            <g key={at}>
              <circle className="pipe-dot" cx={134 + k * 9} cy="160" r="3" />
              <circle className={sys('fade', at, 'pipe-dot is-on')} cx={134 + k * 9} cy="160" r="3" />
            </g>
          ))}
          <path className="pipe-cursor" d="M181,140 l0,16 l4,-4 l3,7 l3,-1.5 l-3,-7 l5.5,0 Z" />
        </g>
      </g>

      <g className={sys('fade', 70, 'sys-status')}>
        <rect x="610" y="342" width="180" height="24" rx="8" />
        <text x="700" y="358">{flowText('flow.pipe.erp')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Do wireframe ao site em 16s: a mesma tela passa por três camadas no mesmo lugar. Wireframe tracejado
// (2-30%), editor com os componentes digitados linha a linha (36-62%) e o site renderizado (68-92%).
// A troca de camadas usa as keyframes ux-* em site.css; os detalhes internos usam createTimeline.
const UX_CODE: Array<[string, string, string]> = [
  ['', 'Nav', ' />'],
  ['', 'Hero', '>'],
  ['  ', 'Title', ' />'],
  ['  ', 'Text', ' />'],
  ['  ', 'Button', ' />'],
  ['  ', 'Image', ' />'],
  ['', '/Hero', '>'],
  ['', 'Cards', '>'],
  ['  ', 'Card', ' />'],
  ['  ', 'Card', ' />'],
  ['  ', 'Card', ' />'],
  ['', '/Cards', '>'],
];

const UX_STAGES = [
  { key: 'flow.ux.s1', at: 2 },
  { key: 'flow.ux.s2', at: 34 },
  { key: 'flow.ux.s3', at: 66 },
];

export function UxCanvas() {
  const { sys, css } = createTimeline();
  const cards = [72, 306, 540];

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className="ux-layer ux-a">
        <g className={sys('fade', 1, 'wf-wire')}>
          <rect x="40" y="40" width="750" height="290" rx="14" />
          <path d="M40,82 H790" />
        </g>
        <g className={sys('fade', 4, 'wf-wire')}>
          <rect x="56" y="54" width="26" height="16" rx="3" />
          <path d="M56,54 L82,70 M82,54 L56,70" />
          {[560, 610, 660].map((x) => <rect key={x} className="wf-block" x={x} y="59" width="38" height="6" rx="3" />)}
          <rect x="712" y="52" width="62" height="20" rx="10" />
        </g>
        <g className={sys('fade', 8, 'wf-wire')}>
          <rect className="wf-block" x="72" y="112" width="270" height="18" rx="4" />
          <rect className="wf-block" x="72" y="138" width="210" height="18" rx="4" />
          {[250, 230, 180].map((w, i) => <rect key={i} className="wf-block" x="72" y={172 + i * 13} width={w} height="6" rx="3" />)}
          <rect x="72" y="222" width="112" height="26" rx="13" />
        </g>
        <g className={sys('fade', 12, 'wf-wire')}>
          <rect x="420" y="102" width="354" height="148" rx="10" />
          <path d="M420,102 L774,250 M774,102 L420,250" />
        </g>
        <g className={sys('fade', 16, 'wf-wire')}>
          {cards.map((x) => <rect key={x} x={x} y="266" width="234" height="48" rx="8" />)}
        </g>
      </g>

      <g className="ux-layer ux-b">
        <rect className="sys-frame" x="40" y="40" width="750" height="290" rx="14" />
        <path className="sys-sep" d="M40,74 H790 M88,74 V330" />
        {[56, 68, 80].map((cx) => <circle key={cx} className="sys-dot" cx={cx} cy="57" r="3.5" />)}
        <rect className="ux-tab" x="100" y="46" width="96" height="28" rx="6" />
        <text className="sys-mono" x="114" y="64">page.tsx</text>
        {UX_CODE.map(([indent, name, end], i) => (
          <g key={i}>
            <text className="ux-ln" x="76" y={98 + i * 19}>{i + 1}</text>
            <text className={sys('fade', 36 + i * 2, 'ux-code')} x="104" y={98 + i * 19} xmlSpace="preserve">
              {indent}<tspan className="ux-punct">&lt;</tspan><tspan className="ux-tag">{name}</tspan><tspan className="ux-punct">{end}</tspan>
            </text>
          </g>
        ))}
        <rect className="ux-caret" x="104" y={85 + 12 * 19} width="7" height="14" />
      </g>

      <g className="ux-layer ux-c">
        <rect className="sys-frame" x="40" y="40" width="750" height="290" rx="14" />
        <path className="sys-sep" d="M40,82 H790" />
        <g className={sys('pop', 68)}>
          <rect className="wf-logo" x="56" y="54" width="26" height="16" rx="4" />
          {[560, 610, 660].map((x) => <rect key={x} className="sys-nav" x={x} y="59" width="38" height="6" rx="3" />)}
          <rect className="ux-btn-ghost" x="712" y="52" width="62" height="20" rx="10" />
        </g>
        <g className={sys('pop', 70)}>
          <text className="ux-h1" x="72" y="128">{flowText('flow.ux.h1')}</text>
          <text className="ux-h1 is-mark" x="72" y="156">{flowText('flow.ux.h2')}</text>
        </g>
        {[250, 230, 180].map((w, i) => <rect key={i} className={sys('grow', 72 + i, 'sys-line')} x="72" y={172 + i * 13} width={w} height="6" rx="3" />)}
        <g className={sys('pop', 74)}>
          <rect className="ux-btn" x="72" y="222" width="112" height="26" rx="13" />
          <text className="ux-btn-text" x="128" y="239">{flowText('flow.ux.cta')}</text>
        </g>
        <g className={sys('pop', 72)}>
          <rect className="ux-img" x="420" y="102" width="354" height="148" rx="10" />
          <circle className="ux-sun" cx="700" cy="140" r="16" />
          <path className="ux-hill" d="M420,250 L500,188 L556,226 L640,164 L774,240 L774,250 Z" />
        </g>
        {cards.map((x, i) => (
          <g key={x} className={sys('pop', 76 + i * 2)}>
            <rect className="sys-row" x={x} y="266" width="234" height="48" rx="8" />
            <rect className="ux-icon" x={x + 14} y="280" width="20" height="20" rx="6" />
            <rect className="sys-nav" x={x + 46} y="282" width="120" height="6" rx="3" />
            <rect className="sys-nav" x={x + 46} y="294" width="84" height="6" rx="3" />
          </g>
        ))}
      </g>

      {UX_STAGES.map((stage, i) => (
        <g key={stage.key} className={sys('fade', stage.at, 'sys-tag ux-step')}>
          <rect x={40 + i * 142} y="344" width="124" height="24" rx="8" />
          <text x={102 + i * 142} y="360">{flowText(stage.key)}</text>
        </g>
      ))}
      {[0, 1].map((i) => <path key={i} className={sys('fade', UX_STAGES[i + 1].at, 'ux-arrow')} d={`M${168 + i * 142},356 h10 m-4,-4 l4,4 l-4,4`} />)}
    </svg>
  );

  return (
    <div className="flow-canvas is-sys is-ux" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Rodada de testes em 16s: cada teste roda (destaque) e passa a cada 5% a partir de 6%; o cálculo do frete
// falha em 24% com a mensagem de erro, é retestado em 60% e passa em 64%. À direita, a cobertura sobe (42%),
// os dispositivos são aprovados (48-54%) e o selo "Pronto para publicar" entra em 70%.
const QA_TESTS = [
  { key: 'flow.qa.t1', ms: '84ms' },
  { key: 'flow.qa.t2', ms: '132ms' },
  { key: 'flow.qa.t3', ms: '210ms' },
  { key: 'flow.qa.t4', ms: '96ms', fail: true },
  { key: 'flow.qa.t5', ms: '348ms' },
  { key: 'flow.qa.t6', ms: '160ms' },
  { key: 'flow.qa.t7', ms: '402ms' },
];

const QA_DEVICES = ['flow.qa.d1', 'flow.qa.d2', 'flow.qa.d3'];

const QA_CHECK = 'l3,3 l6,-7';

export function QaCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="750" height="290" rx="14" />
        <path className="sys-sep" d="M40,80 H790" />
        <text className="sys-title" x="60" y="66">{flowText('flow.qa.suite')}</text>
        <text className="sys-mono qa-right" x="774" y="65">v2.4</text>
      </g>

      {QA_TESTS.map((test, i) => {
        const y = 92 + i * 32;
        const s = 6 + i * 5;
        const end = test.fail ? 64 : s + 3;
        return (
          <g key={test.key}>
            <g className={sys('pop', 2 + i)}>
              <rect className="sys-row" x="56" y={y} width="466" height="26" rx="7" />
              <circle className="qa-pending" cx="72" cy={y + 13} r="6" />
              <text className="qa-name" x="88" y={y + 17}>{flowText(test.key)}</text>
            </g>
            <rect className={sys('hl', s, 'sys-row-hl')} x="56" y={y} width="466" height="26" rx="7" />
            {test.fail ? (
              <>
                <g className={sys('wire', s + 3, 'qa-fail')}>
                  <circle cx="72" cy={y + 13} r="6" />
                  <path d={`M69,${y + 10} l6,6 M75,${y + 10} l-6,6`} />
                </g>
                <text className={sys('wire', s + 4, 'qa-err')} x="226" y={y + 17}>{flowText('flow.qa.fail')}</text>
                <rect className={sys('hl', 60, 'sys-row-hl')} x="56" y={y} width="466" height="26" rx="7" />
              </>
            ) : null}
            <g className={sys('fade', end, 'qa-pass')}>
              <circle cx="72" cy={y + 13} r="6" />
              <path d={`M68.5,${y + 13} ${QA_CHECK}`} />
            </g>
            <text className={sys('fade', end, 'sys-mono qa-right')} x="508" y={y + 17}>{test.ms}</text>
          </g>
        );
      })}

      <g className={sys('pop', 4)}>
        <rect className="sys-panel" x="540" y="92" width="234" height="218" rx="12" />
        <text className="sys-mono" x="556" y="114">{flowText('flow.qa.result')}</text>
        <text className="sys-mono" x="556" y="142">{flowText('flow.qa.cov')}</text>
        <rect className="sys-nav" x="556" y="152" width="202" height="8" rx="4" />
      </g>
      <rect className={sys('grow', 42, 'qa-bar')} x="556" y="152" width="186" height="8" rx="4" />
      <text className={sys('fade', 46, 'wf-kpi')} x="556" y="192">92%</text>
      {QA_DEVICES.map((key, k) => {
        const y = 210 + k * 32;
        return (
          <g key={key}>
            <g className={sys('pop', 6 + k)}>
              <rect className="sys-row" x="556" y={y} width="202" height="24" rx="7" />
              <text className="wf-cell" x="570" y={y + 16}>{flowText(key)}</text>
            </g>
            <g className={sys('fade', 48 + k * 3, 'qa-pass')}>
              <circle cx="740" cy={y + 12} r="6" />
              <path d={`M736.5,${y + 12} ${QA_CHECK}`} />
            </g>
          </g>
        );
      })}

      <g className={sys('fade', 70, 'sys-status')}>
        <rect x="40" y="344" width="170" height="24" rx="8" />
        <text x="125" y="360">{flowText('flow.qa.ready')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Site responsivo em 16s: desktop, tablet e celular se montam juntos, seção por seção, no layout de cada
// tela (menu 6%, hero 10-14%, cards 18-20%, rodapé 24%). Depois as notas de performance, SEO e
// acessibilidade fecham o anel (30-38%) e o selo "No ar" entra em 46%.
type SiteDevice = {
  frame: [number, number, number, number];
  browser?: boolean;
  logo: [number, number, number, number];
  links: [number, number, number][];
  burger?: [number, number];
  title: [number, number, number, number][];
  text: [number, number, number, number][];
  btn: [number, number, number, number];
  img: [number, number, number, number];
  cards: [number, number, number, number][];
  foot?: [number, number, number, number];
};

const SITE_DEVICES: SiteDevice[] = [
  {
    frame: [40, 40, 420, 290],
    browser: true,
    logo: [56, 80, 34, 10],
    links: [[330, 82, 30], [370, 82, 30], [410, 82, 34]],
    title: [[56, 108, 180, 12], [56, 126, 130, 12]],
    text: [[56, 150, 170, 6], [56, 162, 140, 6]],
    btn: [56, 180, 70, 18],
    img: [276, 102, 168, 96],
    cards: [[56, 218, 120, 56], [190, 218, 120, 56], [324, 218, 120, 56]],
    foot: [56, 290, 388, 26],
  },
  {
    frame: [480, 70, 170, 250],
    logo: [492, 84, 26, 8],
    links: [[590, 85, 20], [616, 85, 22]],
    title: [[492, 108, 110, 9], [492, 122, 80, 9]],
    text: [[492, 140, 120, 5], [492, 150, 96, 5]],
    btn: [492, 164, 56, 14],
    img: [492, 188, 146, 56],
    cards: [[492, 254, 69, 34], [569, 254, 69, 34]],
    foot: [492, 296, 146, 14],
  },
  {
    frame: [670, 100, 110, 210],
    logo: [680, 114, 20, 7],
    links: [],
    burger: [752, 114],
    title: [[680, 134, 72, 8], [680, 146, 54, 8]],
    text: [[680, 162, 80, 4], [680, 170, 64, 4]],
    btn: [680, 182, 44, 12],
    img: [680, 202, 90, 40],
    cards: [[680, 250, 90, 20], [680, 276, 90, 20]],
  },
];

const SITE_SCORES = [
  { key: 'flow.sites.perf', value: 98, x: 60 },
  { key: 'flow.sites.seo', value: 100, x: 236 },
  { key: 'flow.sites.a11y', value: 96, x: 400 },
];

export function SitesCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      {SITE_DEVICES.map((d, k) => {
        const [x, y, w, h] = d.frame;
        return (
          <g key={x}>
            <g className={sys('pop', 1 + k)}>
              <rect className="sys-frame" x={x} y={y} width={w} height={h} rx={d.browser ? 14 : 12} />
              {d.browser ? (
                <>
                  <path className="sys-sep" d={`M${x},${y + 28} H${x + w}`} />
                  <circle className="sys-dot" cx={x + 16} cy={y + 14} r="3.5" />
                  <circle className="sys-dot" cx={x + 28} cy={y + 14} r="3.5" />
                  <circle className="sys-dot" cx={x + 40} cy={y + 14} r="3.5" />
                </>
              ) : null}
            </g>
            <g className={sys('fade', 6)}>
              <rect className="wf-logo" x={d.logo[0]} y={d.logo[1]} width={d.logo[2]} height={d.logo[3]} rx="2" />
              {d.links.map(([lx, ly, lw]) => <rect key={lx} className="sys-nav" x={lx} y={ly} width={lw} height="6" rx="3" />)}
              {d.burger ? <path className="site-burger" d={`M${d.burger[0]},${d.burger[1]} h18 M${d.burger[0]},${d.burger[1] + 4} h18 M${d.burger[0]},${d.burger[1] + 8} h18`} /> : null}
            </g>
            {d.title.map(([tx, ty, tw, th]) => <rect key={ty} className={sys('grow', 10, 'site-h')} x={tx} y={ty} width={tw} height={th} rx="3" />)}
            {d.text.map(([tx, ty, tw, th]) => <rect key={ty} className={sys('grow', 12, 'sys-line')} x={tx} y={ty} width={tw} height={th} rx="2" />)}
            <rect className={sys('pop', 14, 'ux-btn')} x={d.btn[0]} y={d.btn[1]} width={d.btn[2]} height={d.btn[3]} rx={d.btn[3] / 2} />
            <g className={sys('pop', 12)}>
              <rect className="ux-img" x={d.img[0]} y={d.img[1]} width={d.img[2]} height={d.img[3]} rx="6" />
              <circle className="ux-sun" cx={d.img[0] + d.img[2] * 0.72} cy={d.img[1] + d.img[3] * 0.3} r={Math.max(4, d.img[3] * 0.1)} />
              <path className="ux-hill" d={`M${d.img[0]},${d.img[1] + d.img[3]} L${d.img[0] + d.img[2] * 0.35},${d.img[1] + d.img[3] * 0.5} L${d.img[0] + d.img[2] * 0.6},${d.img[1] + d.img[3] * 0.78} L${d.img[0] + d.img[2] * 0.8},${d.img[1] + d.img[3] * 0.6} L${d.img[0] + d.img[2]},${d.img[1] + d.img[3]} Z`} />
            </g>
            {d.cards.map(([cx, cy, cw, ch], i) => (
              <g key={cx + cy} className={sys('pop', 18 + i)}>
                <rect className="sys-row" x={cx} y={cy} width={cw} height={ch} rx="6" />
                <rect className="ux-icon" x={cx + 8} y={cy + (ch > 30 ? 8 : 6)} width={ch > 40 ? 12 : 8} height={ch > 40 ? 12 : 8} rx="2" />
                <rect className="sys-nav" x={cx + (ch > 30 ? 8 : 22)} y={cy + (ch > 30 ? ch - 10 : 8)} width={Math.min(80, cw - 30)} height="4" rx="2" />
              </g>
            ))}
            {d.foot ? <rect className={sys('fade', 24, 'site-foot')} x={d.foot[0]} y={d.foot[1]} width={d.foot[2]} height={d.foot[3]} rx="5" /> : null}
          </g>
        );
      })}

      {SITE_SCORES.map((score, i) => (
        <g key={score.key}>
          <circle className={sys('pop', 28, 'site-ring-bg')} cx={score.x} cy="362" r="13" />
          <path
            className={sys('draw', 30 + i * 4, 'site-ring')}
            d={`M${score.x},349 a13,13 0 1,1 -0.01,0`}
            pathLength="1"
            style={{ strokeDasharray: `${score.value / 100} ${2 - score.value / 100}` }}
          />
          <text className={sys('fade', 33 + i * 4, 'site-score')} x={score.x} y="366">{score.value}</text>
          <text className={sys('fade', 28, 'sys-mono')} x={score.x + 22} y="366">{flowText(score.key)}</text>
        </g>
      ))}

      <g className={sys('fade', 46, 'sys-status')}>
        <rect x="670" y="350" width="110" height="24" rx="8" />
        <text x="725" y="366">{flowText('flow.sites.live')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Do anúncio ao lead em 16s: o clique no anúncio (6-8%) leva à landing page, os campos do formulário
// são preenchidos (20-32%), o envio confirma (36-40%) e o lead chega ao time (44%) enquanto a barra
// de conversão sobe (46-50%).
const LP_FIELDS = [
  { key: 'flow.lp.name', value: 'Mariana Costa' },
  { key: 'flow.lp.phone', value: '(41) 99812-4470' },
  { key: 'flow.lp.email', value: 'mariana@empresa.com.br' },
];

const LP_BARS = [30, 38, 34, 46, 52, 84];

const SITE_CURSOR = 'M0,0 L0,15 L4,11 L7,18 L10,17 L7,10 L12,10 Z';

export function LandingCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 2)}>
        <rect className="sys-panel" x="40" y="80" width="190" height="200" rx="12" />
        <text className="sys-mono" x="54" y="102">{flowText('flow.lp.ad')}</text>
        <rect className="ux-img" x="54" y="112" width="162" height="70" rx="6" />
        <circle className="ux-sun" cx="176" cy="132" r="7" />
        <path className="ux-hill" d="M54,182 L104,148 L138,170 L166,154 L216,182 Z" />
        <rect className="site-h" x="54" y="194" width="140" height="8" rx="3" />
        <rect className="sys-line" x="54" y="208" width="104" height="6" rx="3" />
        <rect className="ux-btn-ghost" x="54" y="230" width="92" height="22" rx="11" />
        <text className="lp-ghost-text" x="100" y="245">{flowText('flow.lp.more')}</text>
      </g>
      <rect className={sys('hl', 8, 'sys-row-hl')} x="54" y="230" width="92" height="22" rx="11" />
      <path className={sys('hl', 6, 'lp-cursor')} d={SITE_CURSOR} transform="translate(122 242)" />
      <path className={sys('draw', 12, 'lp-wire')} d="M230,180 H252" pathLength="1" />

      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="256" y="40" width="300" height="290" rx="14" />
        <path className="sys-sep" d="M256,68 H556" />
        <circle className="sys-dot" cx="272" cy="54" r="3.5" />
        <circle className="sys-dot" cx="284" cy="54" r="3.5" />
        <circle className="sys-dot" cx="296" cy="54" r="3.5" />
        <rect className="wf-logo" x="272" y="80" width="30" height="8" rx="2" />
        <rect className="site-h" x="272" y="100" width="210" height="12" rx="3" />
        <rect className="site-h" x="272" y="118" width="150" height="12" rx="3" />
        <rect className="sys-line" x="272" y="138" width="190" height="5" rx="2" />
        <rect className="sys-panel" x="272" y="152" width="268" height="168" rx="10" />
      </g>
      {LP_FIELDS.map((field, i) => {
        const y = 172 + i * 38;
        const s = 20 + i * 6;
        return (
          <g key={field.key}>
            <g className={sys('fade', 2)}>
              <text className="sys-mono" x="286" y={y}>{flowText(field.key)}</text>
              <rect className="sys-row" x="286" y={y + 6} width="240" height="22" rx="6" />
            </g>
            <rect className={sys('hl', s, 'sys-row-hl')} x="286" y={y + 6} width="240" height="22" rx="6" />
            <text className={sys('fade', s + 2, 'lp-input')} x="296" y={y + 21}>{field.value}</text>
          </g>
        );
      })}
      <g className={sys('fade', 2)}>
        <rect className="ux-btn" x="286" y="288" width="240" height="24" rx="12" />
        <text className="ux-btn-text" x="406" y="304">{flowText('flow.lp.send')}</text>
      </g>
      <rect className={sys('hl', 36, 'sys-row-hl')} x="286" y="288" width="240" height="24" rx="12" />
      <path className={sys('hl', 35, 'lp-cursor')} d={SITE_CURSOR} transform="translate(450 298)" />
      <g className={sys('fade', 40, 'lp-ok')}>
        <rect x="272" y="152" width="268" height="168" rx="10" />
        <circle cx="406" cy="210" r="16" />
        <path d="M398,210 l5,5 l10,-11" />
        <text className="wf-title" x="406" y="250">{flowText('flow.lp.ok')}</text>
        <text className="sys-mono" x="406" y="270">{flowText('flow.lp.ok2')}</text>
      </g>
      <path className={sys('draw', 42, 'lp-wire')} d="M556,104 H572" pathLength="1" />

      <g className={sys('pop', 44)}>
        <rect className="sys-panel" x="572" y="60" width="218" height="86" rx="12" />
        <circle className="wf-avatar" cx="596" cy="96" r="12" />
        <text className="sys-title" x="616" y="88">{flowText('flow.lp.lead')}</text>
        <text className="wf-cell" x="616" y="106">Mariana Costa</text>
        <text className="sys-mono" x="616" y="126">{flowText('flow.lp.via')}</text>
      </g>
      <g className={sys('pop', 3)}>
        <rect className="sys-panel" x="572" y="160" width="218" height="170" rx="12" />
        <text className="sys-mono" x="588" y="184">{flowText('flow.lp.conv')}</text>
        <path className="sys-sep" d="M588,312 H774" />
      </g>
      <text className={sys('fade', 50, 'wf-kpi')} x="588" y="216">4,8%</text>
      {LP_BARS.map((h, i) => (
        <rect key={i} className={sys('rise', i === 5 ? 46 : 4 + i, i === 5 ? 'sys-col is-peak' : 'sys-col')} x={592 + i * 30} y={312 - h} width="18" height={h} rx="3" />
      ))}
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Pedido de ponta a ponta em 16s: o tamanho é escolhido e o clique em "Comprar" (8-9%) põe o item no
// carrinho; o pedido percorre pagamento, nota fiscal, estoque, etiqueta e rastreio a cada 9% a partir
// de 16%, cada etapa com o sistema que a executa, e o selo "Sem digitação" fecha em 62%.
const EC_STEPS = [
  { key: 'flow.ec.s1', chip: 'Pix' },
  { key: 'flow.ec.s2', chip: 'ERP' },
  { key: 'flow.ec.s3', chip: 'ERP' },
  { key: 'flow.ec.s4', chip: 'Frete' },
  { key: 'flow.ec.s5', chip: 'WhatsApp' },
];

const EC_SIZES = ['38', '39', '40', '41'];

const EC_CHECK = 'l3,3 l6,-7';

export function EcomCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="370" height="290" rx="14" />
        <path className="sys-sep" d="M40,68 H410" />
        <circle className="sys-dot" cx="56" cy="54" r="3.5" />
        <circle className="sys-dot" cx="68" cy="54" r="3.5" />
        <circle className="sys-dot" cx="80" cy="54" r="3.5" />
        <rect className="wf-logo" x="56" y="80" width="30" height="8" rx="2" />
        <path className="ec-cart" d="M368,78 h4 l3,12 h14 l3,-9 h-18 M377,95 a1.6,1.6 0 1,0 0.1,0 M388,95 a1.6,1.6 0 1,0 0.1,0" />
        <rect className="ux-img" x="56" y="100" width="160" height="160" rx="10" />
        <circle className="ux-sun" cx="176" cy="136" r="12" />
        <path className="ux-hill" d="M56,260 L112,190 L150,232 L180,206 L216,260 Z" />
        <text className="wf-title" x="232" y="118">Runner 2</text>
        <text className="wf-kpi" x="232" y="152">R$ 189,90</text>
        <text className="sys-mono" x="232" y="170">{flowText('flow.ec.inst')}</text>
        {EC_SIZES.map((size, i) => (
          <g key={size} className="wf-filter">
            <rect x={232 + i * 36} y="184" width="30" height="22" rx="6" />
            <text x={247 + i * 36} y="199">{size}</text>
          </g>
        ))}
        <rect className="ux-btn" x="232" y="220" width="162" height="30" rx="15" />
        <text className="ux-btn-text" x="313" y="239">{flowText('flow.ec.buy')}</text>
        <rect className="sys-nav" x="56" y="276" width="330" height="5" rx="2" />
        <rect className="sys-nav" x="56" y="288" width="280" height="5" rx="2" />
        <rect className="sys-nav" x="56" y="300" width="300" height="5" rx="2" />
      </g>
      <g className={sys('fade', 4, 'wf-filter is-active')}>
        <rect x="304" y="184" width="30" height="22" rx="6" />
        <text x="319" y="199">40</text>
      </g>
      <rect className={sys('hl', 9, 'sys-row-hl')} x="232" y="220" width="162" height="30" rx="15" />
      <path className={sys('hl', 7, 'lp-cursor')} d={SITE_CURSOR} transform="translate(336 236)" />
      <g className={sys('pop', 11, 'ec-badge')}>
        <circle cx="392" cy="78" r="7" />
        <text x="392" y="81.5">1</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="430" y="40" width="360" height="290" rx="14" />
        <path className="sys-sep" d="M430,80 H790" />
        <text className="sys-title" x="450" y="66">{flowText('flow.ec.order')}</text>
        <text className="sys-mono qa-right" x="774" y="65">R$ 189,90</text>
      </g>
      {EC_STEPS.map((step, i) => {
        const y = 94 + i * 44;
        const s = 16 + i * 9;
        return (
          <g key={step.key}>
            <g className={sys('pop', 4 + i)}>
              <rect className="sys-row" x="446" y={y} width="328" height="36" rx="8" />
              <circle className="qa-pending" cx="466" cy={y + 18} r="7" />
              <text className="qa-name" x="484" y={y + 22}>{flowText(step.key)}</text>
            </g>
            <rect className={sys('hl', s, 'sys-row-hl')} x="446" y={y} width="328" height="36" rx="8" />
            <g className={sys('fade', s + 1, 'sys-tag')}>
              <rect x="690" y={y + 9} width="72" height="18" rx="6" />
              <text x="726" y={y + 22}>{step.chip}</text>
            </g>
            <g className={sys('fade', s + 3, 'qa-pass')}>
              <circle cx="466" cy={y + 18} r="7" />
              <path d={`M462.5,${y + 18} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}

      <g className={sys('fade', 62, 'sys-status')}>
        <rect x="430" y="344" width="220" height="24" rx="8" />
        <text x="540" y="360">{flowText('flow.ec.done')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Editor de blocos do WordPress em 16s: o inseridor abre e cada bloco entra selecionado, com o nome ao
// lado (título 8%, parágrafo 16%, imagem 24%, colunas 32%, botão 40%); "Atualizar" é clicado em 46% e a
// página é salva. À direita, a saúde do site confirma núcleo, plugins, backup e firewall (52-64%).
const WP_BLOCKS = [
  { key: 'flow.wp.b1', y: 92, h: 22 },
  { key: 'flow.wp.b2', y: 120, h: 32 },
  { key: 'flow.wp.b3', y: 158, h: 88 },
  { key: 'flow.wp.b4', y: 252, h: 44 },
  { key: 'flow.wp.b5', y: 302, h: 22 },
];

const WP_HEALTH = ['flow.wp.h1', 'flow.wp.h2', 'flow.wp.h3', 'flow.wp.h4'];

export function WpCanvas() {
  const { sys, css } = createTimeline();
  const at = (i: number) => 8 + i * 8;

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="480" height="294" rx="14" />
        <path className="sys-sep" d="M40,78 H520" />
        <circle className="wp-logo" cx="64" cy="59" r="10" />
        <text className="wp-logo-text" x="64" y="63.5">W</text>
        <rect className="wp-plus" x="84" y="49" width="20" height="20" rx="4" />
        <path className="wp-plus-icon" d="M94,53 v12 M88,59 h12" />
        <text className="sys-mono" x="116" y="63">{flowText('flow.wp.edit')}</text>
        <rect className="ux-btn" x="428" y="49" width="76" height="20" rx="4" />
        <text className="ux-btn-text" x="466" y="63">{flowText('flow.wp.update')}</text>
      </g>
      {WP_BLOCKS.map((block, i) => <rect key={block.key} className={sys('hl', at(i) - 3, 'sys-row-hl')} x="84" y="49" width="20" height="20" rx="4" />)}

      <rect className={sys('grow', at(0), 'site-h')} x="130" y="96" width="230" height="14" rx="3" />
      {[310, 290, 240].map((w, i) => <rect key={w} className={sys('grow', at(1), 'sys-line')} x="130" y={124 + i * 10} width={w} height="5" rx="2" />)}
      <g className={sys('pop', at(2))}>
        <rect className="ux-img" x="130" y="162" width="310" height="80" rx="6" />
        <circle className="ux-sun" cx="380" cy="186" r="10" />
        <path className="ux-hill" d="M130,242 L220,196 L280,226 L330,206 L440,242 Z" />
      </g>
      <g className={sys('pop', at(3))}>
        <rect className="sys-row" x="130" y="256" width="150" height="36" rx="6" />
        <rect className="sys-row" x="290" y="256" width="150" height="36" rx="6" />
        <rect className="sys-nav" x="140" y="266" width="100" height="5" rx="2" />
        <rect className="sys-nav" x="140" y="276" width="80" height="5" rx="2" />
        <rect className="sys-nav" x="300" y="266" width="110" height="5" rx="2" />
        <rect className="sys-nav" x="300" y="276" width="70" height="5" rx="2" />
      </g>
      <g className={sys('pop', at(4))}>
        <rect className="ux-btn" x="130" y="304" width="96" height="18" rx="9" />
        <text className="ux-btn-text wp-small" x="178" y="317">{flowText('flow.wp.cta')}</text>
      </g>
      {WP_BLOCKS.map((block, i) => (
        <g key={block.key}>
          <rect className={sys('hl', at(i), 'sys-row-hl')} x="122" y={block.y} width="326" height={block.h} rx="4" />
          <g className={sys('hl', at(i), 'sys-tag wp-sel')}>
            <rect x="52" y={block.y + block.h / 2 - 9} width="62" height="18" rx="6" />
            <text x="83" y={block.y + block.h / 2 + 4}>{flowText(block.key)}</text>
          </g>
        </g>
      ))}
      <rect className={sys('hl', 46, 'sys-row-hl')} x="428" y="49" width="76" height="20" rx="4" />
      <path className={sys('hl', 45, 'lp-cursor')} d={SITE_CURSOR} transform="translate(476 60)" />
      <g className={sys('fade', 48, 'sys-status')}>
        <rect x="40" y="346" width="160" height="24" rx="8" />
        <text x="120" y="362">{flowText('flow.wp.saved')}</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="540" y="40" width="250" height="294" rx="14" />
        <path className="sys-sep" d="M540,78 H790" />
        <text className="sys-title" x="560" y="64">{flowText('flow.wp.health')}</text>
      </g>
      {WP_HEALTH.map((key, i) => {
        const y = 94 + i * 46;
        const s = 52 + i * 4;
        return (
          <g key={key}>
            <g className={sys('pop', 4 + i)}>
              <rect className="sys-row" x="556" y={y} width="218" height="36" rx="8" />
              <circle className="qa-pending" cx="576" cy={y + 18} r="7" />
              <text className="qa-name" x="594" y={y + 22}>{flowText(key)}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="556" y={y} width="218" height="36" rx="8" />
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="576" cy={y + 18} r="7" />
              <path d={`M572.5,${y + 18} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 66, 'sys-status')}>
        <rect x="556" y="290" width="218" height="26" rx="8" />
        <text x="665" y="307">{flowText('flow.wp.good')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Busca e resposta de IA em 16s: na página de resultados, o site sobe uma posição por vez
// (14-18%, 24-28%, 34-38%, 44-48%) até o primeiro lugar, empurrando os concorrentes (keyframes seo-*
// em site.css). Em seguida a IA monta a resposta (52-62%) e cita o site como fonte (66%).
const SEO_ROWS = [
  { url: 'agencia-exemplo.com.br', w: 230, move: 'seo-r0' },
  { url: 'portal-de-sites.com', w: 260, move: 'seo-r1' },
  { url: 'guia-empresas.com.br', w: 200, move: 'seo-r2' },
  { url: 'outra-agencia.com.br', w: 244, move: 'seo-r3' },
];

const SEO_ANSWER = [270, 250, 262, 180];

export function SeoCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="420" height="290" rx="14" />
        <rect className="sys-search" x="56" y="54" width="388" height="26" rx="13" />
        <circle className="seo-lens" cx="74" cy="66" r="5" />
        <path className="seo-lens" d="M78,70 l4,4" />
        <text className="sys-mono" x="92" y="71">{flowText('flow.seo.q')}</text>
      </g>
      <g className="seo-loop">
        {SEO_ROWS.map((row, i) => (
          <g key={row.url} className={`seo-move ${row.move}`}>
            <rect className="sys-row" x="56" y={96 + i * 44} width="388" height="36" rx="8" />
            <text className="seo-url" x="70" y={110 + i * 44}>{row.url}</text>
            <rect className="seo-title" x="70" y={118 + i * 44} width={row.w} height="6" rx="3" />
          </g>
        ))}
        <g className="seo-move seo-me">
          <rect className="seo-ours" x="56" y="96" width="388" height="36" rx="8" />
          <text className="seo-url is-ours" x="70" y="110">seusite.com.br</text>
          <rect className="seo-title is-ours" x="70" y="118" width="250" height="6" rx="3" />
        </g>
      </g>
      <g className={sys('fade', 49, 'sys-status')}>
        <rect x="396" y="104" width="38" height="20" rx="7" />
        <text x="415" y="118">1º</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="480" y="40" width="310" height="290" rx="14" />
        <path className="sys-sep" d="M480,80 H790" />
        <path className="sys-spark" d={SYS_SPARK} transform="translate(500 60)" />
        <text className="sys-title" x="514" y="65">{flowText('flow.seo.ai')}</text>
      </g>
      <g className={sys('fade', 52)}>
        <rect className="sys-row" x="560" y="94" width="214" height="26" rx="13" />
        <text className="sys-mono" x="574" y="111">{flowText('flow.seo.ask')}</text>
      </g>
      {SEO_ANSWER.map((w, i) => <rect key={i} className={sys('grow', 56 + i * 2, 'sys-line')} x="496" y={138 + i * 14} width={w} height="6" rx="3" />)}
      <text className={sys('fade', 64, 'sys-mono')} x="496" y="216">{flowText('flow.seo.src')}</text>
      <g className={sys('pop', 66)}>
        <rect className="seo-ours" x="496" y="226" width="278" height="30" rx="8" />
        <circle className="wf-logo" cx="514" cy="241" r="6" />
        <text className="seo-url is-ours" x="528" y="245">seusite.com.br</text>
      </g>
      <rect className={sys('hl', 66, 'sys-row-hl')} x="496" y="226" width="278" height="30" rx="8" />
      <g className={sys('pop', 69)}>
        <rect className="sys-row" x="496" y="264" width="278" height="30" rx="8" />
        <circle className="wf-avatar" cx="514" cy="279" r="6" />
        <rect className="seo-title" x="528" y="276" width="120" height="6" rx="3" />
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Sustentação em 16s: o painel de produção se monta e o gráfico de erros mostra um pico em 14%; o
// incidente abre (16-44%), a correção é commitada (28%) e publicada (34%), e o status fica verde em 46%,
// quando os erros voltam ao normal. À direita, a rotina da semana fecha item a item (48-64%).
const SUS_ERRORS = [24, 30, 22, 28, 26, 96, 34, 22, 18, 20];
const SUS_ROUTINE = ['flow.sus.r1', 'flow.sus.r2', 'flow.sus.r3', 'flow.sus.r4', 'flow.sus.r5'];

export function SustainCanvas() {
  const { sys, css } = createTimeline();
  const colAt = (i: number) => (i < 5 ? 3 + i : i === 5 ? 14 : 38 + i);

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="400" height="290" rx="14" />
        <path className="sys-sep" d="M40,78 H440 M60,244 H420" />
        <text className="sys-title" x="60" y="64">{flowText('flow.sus.prod')}</text>
        <text className="sys-mono qa-right" x="420" y="64">app.cliente.com.br</text>
        <text className="sys-mono" x="60" y="104">{flowText('flow.sus.uptime')}</text>
        <text className="wf-kpi" x="60" y="134">99,98%</text>
        <text className="sys-mono" x="250" y="104">{flowText('flow.sus.errors')}</text>
      </g>
      {SUS_ERRORS.map((h, i) => (
        <rect key={i} className={sys('rise', colAt(i), i === 5 ? 'sys-col is-alert' : 'sys-col')} x={60 + i * 36} y={236 - h} width="22" height={h} rx="3" />
      ))}
      <g className={sys('swap', 16, 'sys-status is-review')}>
        <rect x="60" y="256" width="360" height="26" rx="8" />
        <text x="240" y="273">{flowText('flow.sus.incident')}</text>
      </g>
      <g className={sys('fade', 46, 'sys-status')}>
        <rect x="60" y="256" width="360" height="26" rx="8" />
        <text x="240" y="273">{flowText('flow.sus.fixed')}</text>
      </g>
      <text className={sys('fade', 28, 'sys-mono')} x="60" y="309">{flowText('flow.sus.commit')}</text>
      <g className={sys('fade', 34, 'sys-tag is-ok')}>
        <rect x="346" y="296" width="74" height="18" rx="6" />
        <text x="383" y="309">{flowText('flow.sus.deploy')}</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="460" y="40" width="330" height="290" rx="14" />
        <path className="sys-sep" d="M460,78 H790" />
        <text className="sys-title" x="480" y="64">{flowText('flow.sus.week')}</text>
      </g>
      {SUS_ROUTINE.map((key, i) => {
        const y = 92 + i * 44;
        const s = 48 + i * 4;
        return (
          <g key={key}>
            <g className={sys('pop', 4 + i)}>
              <rect className="sys-row" x="476" y={y} width="298" height="36" rx="8" />
              <circle className="qa-pending" cx="496" cy={y + 18} r="7" />
              <text className="qa-name" x="514" y={y + 22}>{flowText(key)}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="476" y={y} width="298" height="36" rx="8" />
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="496" cy={y + 18} r="7" />
              <path d={`M492.5,${y + 18} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 68, 'sys-status')}>
        <rect x="460" y="344" width="200" height="24" rx="8" />
        <text x="560" y="360">{flowText('flow.sus.ok')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Manutenção de WordPress em 16s: as atualizações pendentes são aplicadas uma a uma (8-29%), o backup na
// nuvem enche a barra (35%) e o relatório mensal se monta à direita com uptime, carregamento e serviços
// executados (46-60%), fechando com "Relatório enviado" (66%).
const MT_UPDATES = [
  { name: 'WordPress', version: '6.6.2 → 6.7' },
  { name: 'WooCommerce', version: '9.3.3 → 9.4' },
  { name: 'Yoast SEO', version: '23.5 → 23.8' },
  { name: 'flow.mt.theme', version: '2.1.0 → 2.2.0' },
];

const MT_DONE = ['flow.mt.s1', 'flow.mt.s2', 'flow.mt.s3'];

export function MaintainCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="430" height="290" rx="14" />
        <path className="sys-sep" d="M40,78 H470" />
        <circle className="wp-logo" cx="64" cy="59" r="10" />
        <text className="wp-logo-text" x="64" y="63.5">W</text>
        <text className="sys-title" x="84" y="64">{flowText('flow.mt.updates')}</text>
        <text className="sys-mono qa-right" x="454" y="64">{flowText('flow.mt.avail')}</text>
      </g>
      {MT_UPDATES.map((item, i) => {
        const y = 92 + i * 40;
        const s = 8 + i * 7;
        return (
          <g key={item.name}>
            <g className={sys('pop', 3 + i)}>
              <rect className="sys-row" x="56" y={y} width="398" height="32" rx="8" />
              <circle className="qa-pending" cx="76" cy={y + 16} r="7" />
              <text className="qa-name" x="94" y={y + 20}>{flowText(item.name)}</text>
              <text className="sys-mono qa-right" x="438" y={y + 20}>{item.version}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="56" y={y} width="398" height="32" rx="8" />
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="76" cy={y + 16} r="7" />
              <path d={`M72.5,${y + 16} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 2)}>
        <text className="sys-mono" x="56" y="270">{flowText('flow.mt.backup')}</text>
        <rect className="sys-nav" x="56" y="280" width="398" height="8" rx="4" />
        <text className="sys-mono" x="56" y="310">{flowText('flow.mt.backup2')}</text>
      </g>
      <rect className={sys('grow', 35, 'qa-bar')} x="56" y="280" width="398" height="8" rx="4" />
      <text className={sys('fade', 39, 'sys-mono qa-right')} x="454" y="270">100%</text>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="490" y="40" width="300" height="290" rx="14" />
        <path className="sys-sep" d="M490,78 H790" />
        <text className="sys-title" x="510" y="64">{flowText('flow.mt.report')}</text>
        <text className="sys-mono" x="510" y="104">Uptime</text>
        <text className="sys-mono" x="650" y="104">{flowText('flow.mt.speed')}</text>
      </g>
      <text className={sys('fade', 46, 'wf-kpi')} x="510" y="132">99,98%</text>
      <text className={sys('fade', 49, 'wf-kpi')} x="650" y="132">1,2 s</text>
      {MT_DONE.map((key, i) => {
        const y = 160 + i * 38;
        const s = 54 + i * 3;
        return (
          <g key={key}>
            <g className={sys('pop', 5 + i)}>
              <rect className="sys-row" x="506" y={y} width="268" height="30" rx="8" />
              <circle className="qa-pending" cx="526" cy={y + 15} r="7" />
              <text className="qa-name" x="544" y={y + 19}>{flowText(key)}</text>
            </g>
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="526" cy={y + 15} r="7" />
              <path d={`M522.5,${y + 15} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 66, 'sys-status')}>
        <rect x="490" y="344" width="180" height="24" rx="8" />
        <text x="580" y="360">{flowText('flow.mt.sent')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Recuperação de WordPress em 16s: a varredura passa arquivo por arquivo (4-24%) e marca três como
// malware (âmbar, keyframes sys-swap); a limpeza troca cada marca por "limpo" (40-52%) e o aviso de
// arquivos comprometidos dá lugar a "Malware removido" (58%). À direita, as etapas da recuperação
// fecham em sequência (30-72%) até "Site no ar e protegido" (76%).
const RC_FILES = [
  { path: 'wp-config.php', bad: false },
  { path: 'wp-content/uploads/x7.php', bad: true },
  { path: 'wp-includes/version.php', bad: false },
  { path: 'plugins/form-lite/upload.php', bad: true },
  { path: 'themes/site/functions.php', bad: true },
  { path: 'index.php', bad: false },
];

const RC_STEPS = [
  { key: 'flow.rc.s1', at: 30 },
  { key: 'flow.rc.s2', at: 54 },
  { key: 'flow.rc.s3', at: 60 },
  { key: 'flow.rc.s4', at: 66 },
  { key: 'flow.rc.s5', at: 72 },
];

export function RecoverCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="400" height="290" rx="14" />
        <path className="sys-sep" d="M40,78 H440" />
        <text className="sys-title" x="60" y="64">{flowText('flow.rc.scan')}</text>
        <text className="sys-mono qa-right" x="420" y="64">seusite.com.br</text>
      </g>
      {RC_FILES.map((file, i) => {
        const y = 88 + i * 38;
        const s = 6 + i * 4;
        return (
          <g key={file.path}>
            <g className={sys('pop', 2 + i)}>
              <rect className="sys-row" x="56" y={y} width="368" height="30" rx="8" />
              <circle className="qa-pending" cx="76" cy={y + 15} r="7" />
              <text className="qa-name" x="94" y={y + 19}>{file.path}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="56" y={y} width="368" height="30" rx="8" />
            {file.bad ? (
              <>
                <g className={sys('swap', s, 'qa-fail')}>
                  <circle cx="76" cy={y + 15} r="7" />
                  <path d={`M73,${y + 12} l6,6 M79,${y + 12} l-6,6`} />
                </g>
                <g className={sys('swap', s, 'sys-tag is-alert')}>
                  <rect x="346" y={y + 6} width="66" height="18" rx="6" />
                  <text x="379" y={y + 19}>{flowText('flow.rc.bad')}</text>
                </g>
                <g className={sys('fade', s + 30, 'qa-pass')}>
                  <circle cx="76" cy={y + 15} r="7" />
                  <path d={`M72.5,${y + 15} ${EC_CHECK}`} />
                </g>
                <g className={sys('fade', s + 30, 'sys-tag is-ok')}>
                  <rect x="346" y={y + 6} width="66" height="18" rx="6" />
                  <text x="379" y={y + 19}>{flowText('flow.rc.clean')}</text>
                </g>
              </>
            ) : (
              <g className={sys('fade', s, 'qa-pass')}>
                <circle cx="76" cy={y + 15} r="7" />
                <path d={`M72.5,${y + 15} ${EC_CHECK}`} />
              </g>
            )}
          </g>
        );
      })}
      <g className={sys('swap', 26, 'sys-status is-review')}>
        <rect x="40" y="344" width="220" height="24" rx="8" />
        <text x="150" y="360">{flowText('flow.rc.found')}</text>
      </g>
      <g className={sys('fade', 58, 'sys-status')}>
        <rect x="40" y="344" width="220" height="24" rx="8" />
        <text x="150" y="360">{flowText('flow.rc.removed')}</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="460" y="40" width="330" height="290" rx="14" />
        <path className="sys-sep" d="M460,78 H790" />
        <text className="sys-title" x="480" y="64">{flowText('flow.rc.title')}</text>
      </g>
      {RC_STEPS.map((step, i) => {
        const y = 92 + i * 44;
        return (
          <g key={step.key}>
            <g className={sys('pop', 4 + i)}>
              <rect className="sys-row" x="476" y={y} width="298" height="36" rx="8" />
              <circle className="qa-pending" cx="496" cy={y + 18} r="7" />
              <text className="qa-name" x="514" y={y + 22}>{flowText(step.key)}</text>
            </g>
            <rect className={sys('hl', step.at - 2, 'sys-row-hl')} x="476" y={y} width="298" height="36" rx="8" />
            <g className={sys('fade', step.at, 'qa-pass')}>
              <circle cx="496" cy={y + 18} r="7" />
              <path d={`M492.5,${y + 18} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 76, 'sys-status')}>
        <rect x="460" y="344" width="230" height="24" rx="8" />
        <text x="575" y="360">{flowText('flow.rc.ok')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// Hospedagem em 16s: o domínio abre em HTTPS com o selo SSL (8%), o uso de arquivos e e-mails enche as
// barras (12-16%) e as contas de e-mail entram (20-26%). À direita, a caixa de entrada recebe mensagens
// (32-56%) e o anti-spam marca a suspeita (46%); "Site e e-mails no ar" fecha em 64%.
const HS_METERS = [
  { key: 'flow.hs.files', value: 'flow.hs.files_v', y: 150, ratio: 0.4, at: 12 },
  { key: 'flow.hs.mail', value: 'flow.hs.mail_v', y: 190, ratio: 0.62, at: 16 },
];

const HS_ACCOUNTS = ['contato', 'comercial', 'financeiro'];

const HS_INBOX = [
  { from: 'Mariana Costa', w: 190 },
  { from: 'Rafael Lima', w: 150 },
  { from: 'promo@ofert4s.biz', w: 120, spam: true },
  { from: 'Ana Souza', w: 176 },
  { from: 'Pedro Alves', w: 140 },
];

export function HostCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="400" height="290" rx="14" />
        <path className="sys-sep" d="M40,78 H440" />
        <text className="sys-title" x="60" y="64">{flowText('flow.hs.panel')}</text>
        <rect className="sys-row" x="56" y="92" width="368" height="36" rx="8" />
        <path className="hs-lock" d="M70,109 h12 v10 h-12 Z M73,109 v-3 a3,3 0 0,1 6,0 v3" />
      </g>
      <text className={sys('fade', 4, 'qa-name')} x="92" y="114">https://seusite.com.br</text>
      <g className={sys('pop', 8, 'sys-tag is-ok')}>
        <rect x="354" y="101" width="56" height="18" rx="6" />
        <text x="382" y="114">SSL</text>
      </g>
      {HS_METERS.map((meter) => (
        <g key={meter.key}>
          <g className={sys('fade', 2)}>
            <text className="sys-mono" x="56" y={meter.y}>{flowText(meter.key)}</text>
            <text className="sys-mono qa-right" x="424" y={meter.y}>{flowText(meter.value)}</text>
            <rect className="sys-nav" x="56" y={meter.y + 8} width="368" height="8" rx="4" />
          </g>
          <rect className={sys('grow', meter.at, 'qa-bar')} x="56" y={meter.y + 8} width={368 * meter.ratio} height="8" rx="4" />
        </g>
      ))}
      <text className={sys('fade', 18, 'sys-mono')} x="56" y="228">{flowText('flow.hs.accounts')}</text>
      {HS_ACCOUNTS.map((name, i) => {
        const y = 238 + i * 30;
        return (
          <g key={name} className={sys('pop', 20 + i * 3)}>
            <rect className="sys-row" x="56" y={y} width="368" height="24" rx="6" />
            <circle className="wf-avatar" cx="72" cy={y + 12} r="6" />
            <text className="wf-cell" x="86" y={y + 16}>{name}@seusite.com.br</text>
          </g>
        );
      })}

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="460" y="40" width="330" height="290" rx="14" />
        <path className="sys-sep" d="M460,78 H790" />
        <text className="sys-title" x="480" y="64">{flowText('flow.hs.inbox')}</text>
        <text className="sys-mono qa-right" x="774" y="64">contato@</text>
      </g>
      {HS_INBOX.map((mail, i) => {
        const y = 92 + i * 44;
        const s = 32 + i * 6;
        return (
          <g key={mail.from}>
            <g className={sys('pop', s)}>
              <rect className={mail.spam ? 'sys-row is-alert' : 'sys-row'} x="476" y={y} width="298" height="36" rx="8" />
              <circle className="wf-avatar" cx="496" cy={y + 18} r="8" />
              <text className="wf-cell" x="514" y={y + 16}>{mail.from}</text>
              <rect className="sys-nav" x="514" y={y + 23} width={mail.w} height="5" rx="2" />
            </g>
            {mail.spam ? (
              <g className={sys('fade', s + 2, 'sys-tag is-alert')}>
                <rect x="706" y={y + 9} width="56" height="18" rx="6" />
                <text x="734" y={y + 22}>{flowText('flow.hs.spam')}</text>
              </g>
            ) : null}
          </g>
        );
      })}
      <g className={sys('fade', 64, 'sys-status')}>
        <rect x="460" y="344" width="200" height="24" rx="8" />
        <text x="560" y="360">{flowText('flow.hs.ok')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

// VPS gerenciado em 16s: o "docker compose up" sobe os containers um a um (8-28%) e o deploy conclui
// em 36%. À direita, o servidor mostra CPU, memória e disco (40-44%) e os itens de segurança fecham em
// sequência (50-60%), terminando em "Servidor monitorado" (66%).
const VPS_CONTAINERS = [
  { name: 'app', image: 'node:22' },
  { name: 'api', image: 'php:8.3-fpm' },
  { name: 'n8n', image: 'n8nio/n8n' },
  { name: 'postgres', image: 'postgres:16' },
  { name: 'proxy', image: 'nginx:1.27' },
];

const VPS_METRICS = [
  { key: 'CPU', ratio: 0.38 },
  { key: 'flow.vps.mem', ratio: 0.61 },
  { key: 'flow.vps.disk', ratio: 0.42 },
];

const VPS_SECURITY = ['flow.vps.fw', 'flow.vps.ssl', 'flow.vps.bk'];

export function VpsCanvas() {
  const { sys, css } = createTimeline();

  const svg = (
    <svg viewBox="0 0 800 400" preserveAspectRatio="xMidYMid meet">
      <g className={sys('pop', 1)}>
        <rect className="sys-frame" x="40" y="40" width="420" height="290" rx="14" />
        <path className="sys-sep" d="M40,68 H460" />
        <circle className="sys-dot" cx="56" cy="54" r="3.5" />
        <circle className="sys-dot" cx="68" cy="54" r="3.5" />
        <circle className="sys-dot" cx="80" cy="54" r="3.5" />
        <text className="sys-mono" x="100" y="58">deploy@vps-01</text>
      </g>
      <g className={sys('fade', 3)}>
        <text className="vps-prompt" x="56" y="92">$</text>
        <text className="qa-name" x="70" y="92">docker compose up -d</text>
      </g>
      {VPS_CONTAINERS.map((item, i) => {
        const y = 106 + i * 38;
        const s = 8 + i * 5;
        return (
          <g key={item.name}>
            <g className={sys('pop', 4 + i)}>
              <rect className="sys-row" x="56" y={y} width="388" height="30" rx="8" />
              <circle className="qa-pending" cx="76" cy={y + 15} r="7" />
              <text className="qa-name" x="94" y={y + 19}>{item.name}</text>
              <text className="sys-mono" x="180" y={y + 19}>{item.image}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="56" y={y} width="388" height="30" rx="8" />
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="76" cy={y + 15} r="7" />
              <path d={`M72.5,${y + 15} ${EC_CHECK}`} />
            </g>
            <g className={sys('fade', s + 1, 'sys-tag is-ok')}>
              <rect x="352" y={y + 6} width="84" height="18" rx="6" />
              <text x="394" y={y + 19}>{flowText('flow.vps.running')}</text>
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 36, 'sys-status')}>
        <rect x="40" y="344" width="180" height="24" rx="8" />
        <text x="130" y="360">{flowText('flow.vps.done')}</text>
      </g>

      <g className={sys('pop', 2)}>
        <rect className="sys-frame" x="480" y="40" width="310" height="290" rx="14" />
        <path className="sys-sep" d="M480,78 H790" />
        <text className="sys-title" x="500" y="64">vps-01</text>
        <text className="sys-mono qa-right" x="774" y="64">4 vCPU · 8 GB</text>
      </g>
      {VPS_METRICS.map((metric, i) => {
        const y = 104 + i * 40;
        return (
          <g key={metric.key}>
            <g className={sys('fade', 3)}>
              <text className="sys-mono" x="500" y={y}>{flowText(metric.key)}</text>
              <rect className="sys-nav" x="500" y={y + 8} width="274" height="8" rx="4" />
            </g>
            <rect className={sys('grow', 40 + i * 2, 'qa-bar')} x="500" y={y + 8} width={274 * metric.ratio} height="8" rx="4" />
            <text className={sys('fade', 42 + i * 2, 'sys-mono qa-right')} x="774" y={y}>{Math.round(metric.ratio * 100)}%</text>
          </g>
        );
      })}
      {VPS_SECURITY.map((key, i) => {
        const y = 216 + i * 36;
        const s = 50 + i * 5;
        return (
          <g key={key}>
            <g className={sys('pop', 5 + i)}>
              <rect className="sys-row" x="496" y={y} width="278" height="28" rx="8" />
              <circle className="qa-pending" cx="516" cy={y + 14} r="7" />
              <text className="qa-name" x="534" y={y + 18}>{flowText(key)}</text>
            </g>
            <rect className={sys('hl', s - 2, 'sys-row-hl')} x="496" y={y} width="278" height="28" rx="8" />
            <g className={sys('fade', s, 'qa-pass')}>
              <circle cx="516" cy={y + 14} r="7" />
              <path d={`M512.5,${y + 14} ${EC_CHECK}`} />
            </g>
          </g>
        );
      })}
      <g className={sys('fade', 66, 'sys-status')}>
        <rect x="480" y="344" width="190" height="24" rx="8" />
        <text x="575" y="360">{flowText('flow.vps.mon')}</text>
      </g>
    </svg>
  );

  return (
    <div className="flow-canvas is-sys" aria-hidden="true">
      <style>{css()}</style>
      {svg}
    </div>
  );
}

export function trackLens(event: PointerEvent<HTMLElement>) {
  const box = event.currentTarget.getBoundingClientRect();
  event.currentTarget.style.setProperty('--lens-x', `${event.clientX - box.left}px`);
  event.currentTarget.style.setProperty('--lens-y', `${event.clientY - box.top}px`);
}

export function Rotating({ items }: { items: string[] }) {
  const [state, setState] = useState({ active: 0, past: -1 });

  useEffect(() => {
    if (items.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }

    const timer = window.setInterval(() => {
      setState((current) => ({ active: (current.active + 1) % items.length, past: current.active }));
    }, 2800);

    return () => window.clearInterval(timer);
  }, [items.length]);

  return (
    <span className="rot">
      {items.map((item, index) => (
        <span key={item} className={index === state.active ? 'is-active' : index === state.past ? 'is-past' : undefined}>{item}</span>
      ))}
    </span>
  );
}

export const VISUALS: Record<string, () => ReactNode> = {
  'flow-agent': () => <FlowCanvas />,
  'flow-automation': () => <FlowCanvas preset="automation" />,
  rag: () => <RagCanvas />,
  n8n: () => <N8nCanvas />,
  llm: () => <LlmCanvas />,
  system: () => <SystemCanvas />,
  software: () => <SoftwareCanvas />,
  saas: () => <SaasCanvas />,
  modernize: () => <ModernizeCanvas />,
  pipeline: () => <PipelineCanvas />,
  ux: () => <UxCanvas />,
  qa: () => <QaCanvas />,
  sites: () => <SitesCanvas />,
  landing: () => <LandingCanvas />,
  ecom: () => <EcomCanvas />,
  wp: () => <WpCanvas />,
  seo: () => <SeoCanvas />,
  sustain: () => <SustainCanvas />,
  maintain: () => <MaintainCanvas />,
  recover: () => <RecoverCanvas />,
  host: () => <HostCanvas />,
  vps: () => <VpsCanvas />,
};

// Três formatos: home (linha rotativa e lente), com animação (canvas ao fundo) e interno (log do agente). O aside entra ao lado do título, em hero-split.
export function Hero({ data, aside }: { data: Data; aside?: ReactNode }) {
  const title = text(data, 'title');
  const rotating = strings(data, 'rotating');
  const badges = strings(data, 'badges');
  const home = rotating.length > 0;
  const visualKey = text(data, 'visual');
  const visual = visualKey in VISUALS ? VISUALS[visualKey] : undefined;
  const lead = text(data, 'subtitle');
  const hasActions = Boolean(text(data, 'primary_href') || text(data, 'secondary_href'));
  const classes = home ? 'hero is-home is-lens' : visual ? 'hero has-flow' : 'hero';

  const heading = (
    <h1 className={home ? 'display' : 'display is-inner'}>
      {home ? (
        <>
          <span className="sr-only">{`${title} ${rotating[0]}`}</span>
          <span aria-hidden="true">
            {title}
            <Rotating items={rotating} />
          </span>
        </>
      ) : <Marked value={title} />}
    </h1>
  );

  const body = (
    <>
      {heading}
      {lead || hasActions ? (
        <div className="hero-foot">
          <Rich className="lead" value={lead} />
          {hasActions ? (
            <div className="actions">
              <Cta href={text(data, 'primary_href')} label={text(data, 'primary_label')} variant="primary" />
              <Cta href={text(data, 'secondary_href')} label={text(data, 'secondary_label')} variant="line" />
            </div>
          ) : null}
        </div>
      ) : null}
      {badges.length ? (
        <ul className="badges">
          {badges.map((badge) => <li key={badge}>{badge}</li>)}
        </ul>
      ) : null}
    </>
  );

  return (
    <section className={classes} onPointerMove={home ? trackLens : undefined}>
      {visual ? visual() : <AgentLog />}
      {aside ? (
        <div className="wrap hero-split">
          <div>{body}</div>
          {aside}
        </div>
      ) : (
        <div className="wrap">{body}</div>
      )}
    </section>
  );
}
