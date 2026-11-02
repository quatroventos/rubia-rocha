import type { DashboardStats } from '../screens/analytics';

export function VisitsChart({ chart }: { chart: DashboardStats['chart'] }) {
  const width = 640;
  const height = 228;
  const pad = { top: 18, right: 10, bottom: 30, left: 30 };
  const innerW = width - pad.left - pad.right;
  const innerH = height - pad.top - pad.bottom;
  const max = Math.max(...chart.current, ...chart.previous, 1);
  const count = Math.max(chart.labels.length, 1);
  const groupW = innerW / count;
  const barW = Math.max(2, Math.min(9, groupW * 0.3));
  const ticks = [0, 0.5, 1];

  return (
    <div className="visits-chart">
      <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Acessos por dia">
        {ticks.map((tick) => {
          const y = pad.top + innerH * (1 - tick);
          const value = Math.round(max * tick);
          return (
            <g key={tick}>
              <line
                x1={pad.left}
                x2={width - pad.right}
                y1={y}
                y2={y}
                stroke="#edf0f3"
                strokeDasharray="3 4"
              />
              <text x={pad.left - 8} y={y + 3} textAnchor="end" className="chart-axis">
                {value}
              </text>
            </g>
          );
        })}
        {chart.labels.map((label, index) => {
          const center = pad.left + index * groupW + groupW / 2;
          const currentH = (chart.current[index] / max) * innerH;
          const previousH = (chart.previous[index] / max) * innerH;
          const showLabel = count <= 14 || index % Math.ceil(count / 10) === 0 || index === count - 1;

          return (
            <g key={`${label}-${index}`}>
              <rect
                x={center - barW - 1}
                y={pad.top + innerH - previousH}
                width={barW}
                height={previousH}
                rx="2"
                fill="url(#barPrevious)"
              />
              <rect
                x={center + 1}
                y={pad.top + innerH - currentH}
                width={barW}
                height={currentH}
                rx="2"
                fill="url(#barCurrent)"
              />
              {showLabel ? (
                <text x={center} y={height - 10} textAnchor="middle" className="chart-axis">
                  {label}
                </text>
              ) : null}
            </g>
          );
        })}
        <defs>
          <linearGradient id="barCurrent" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor="#ef8976" />
            <stop offset="100%" stopColor="#f4b2a6" />
          </linearGradient>
          <linearGradient id="barPrevious" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor="#8e81e2" />
            <stop offset="100%" stopColor="#bfb9f4" />
          </linearGradient>
        </defs>
      </svg>
      <div className="chart-legend">
        <span><i className="coral" /> {chart.current_label}</span>
        <span><i className="purple" /> {chart.previous_label}</span>
      </div>
    </div>
  );
}
