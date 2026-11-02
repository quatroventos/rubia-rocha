import { rangeOptions, type RangeKey } from '../screens/analytics';

export function DateRangeFilter({
  value,
  onChange,
}: {
  value: RangeKey;
  onChange: (range: RangeKey) => void;
}) {
  return (
    <div className="range-chips" role="tablist" aria-label="Período">
      {rangeOptions.map((option) => (
        <button
          key={option.key}
          type="button"
          role="tab"
          aria-selected={value === option.key}
          className={`range-chip${value === option.key ? ' is-active' : ''}`}
          onClick={() => onChange(option.key)}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}
