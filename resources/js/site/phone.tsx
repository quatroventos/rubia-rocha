import { getLocale } from './i18n';

export type PhoneCountry = { iso: string; dial: string; masks: string[] };

// '#' marca cada dígito; máscaras em ordem crescente de tamanho.
export const PHONE_COUNTRIES: PhoneCountry[] = [
  { iso: 'BR', dial: '55', masks: ['(##) ####-####', '(##) #####-####'] },
  { iso: 'PT', dial: '351', masks: ['### ### ###'] },
  { iso: 'US', dial: '1', masks: ['(###) ###-####'] },
  { iso: 'AR', dial: '54', masks: ['## ####-####', '# ## ####-####'] },
  { iso: 'CL', dial: '56', masks: ['# #### ####'] },
  { iso: 'CO', dial: '57', masks: ['### ### ####'] },
  { iso: 'MX', dial: '52', masks: ['## #### ####'] },
  { iso: 'PY', dial: '595', masks: ['### ### ###'] },
  { iso: 'UY', dial: '598', masks: ['## ### ###'] },
  { iso: 'ES', dial: '34', masks: ['### ### ###'] },
];

export type PhoneValue = { iso: string; number: string };

const slots = (mask: string) => mask.split('#').length - 1;

export function phoneCountry(iso: string): PhoneCountry {
  return PHONE_COUNTRIES.find((country) => country.iso === iso) ?? PHONE_COUNTRIES[0];
}

export function formatPhone(raw: string, country: PhoneCountry): string {
  const max = slots(country.masks[country.masks.length - 1]);
  const digits = raw.replace(/\D/g, '').slice(0, max);
  if (!digits) {
    return '';
  }

  const mask = country.masks.find((candidate) => slots(candidate) >= digits.length) ?? country.masks[country.masks.length - 1];
  let out = '';
  let index = 0;
  for (const char of mask) {
    if (index >= digits.length) {
      break;
    }
    if (char === '#') {
      out += digits[index++];
    } else {
      out += char;
    }
  }

  return out;
}

export function isPhoneComplete(value: PhoneValue): boolean {
  const length = value.number.replace(/\D/g, '').length;
  return phoneCountry(value.iso).masks.some((mask) => slots(mask) === length);
}

export function phoneToString(value: PhoneValue): string {
  return `+${phoneCountry(value.iso).dial} ${value.number}`;
}

function flag(iso: string): string {
  return String.fromCodePoint(...[...iso].map((char) => 0x1f1e6 + char.charCodeAt(0) - 65));
}

function countryName(iso: string): string {
  try {
    return new Intl.DisplayNames([getLocale()], { type: 'region' }).of(iso) ?? iso;
  } catch {
    return iso;
  }
}

export function PhoneInput({ name, value, onChange, invalid, countryLabel, id }: {
  name: string;
  value: PhoneValue;
  onChange: (value: PhoneValue) => void;
  invalid?: boolean;
  countryLabel: string;
  id?: string;
}) {
  const country = phoneCountry(value.iso);
  const placeholder = country.masks[country.masks.length - 1].replace(/#/g, '0');

  return (
    <span className={['phone-input', invalid ? 'is-invalid' : ''].filter(Boolean).join(' ')}>
      <span className="phone-country">
        <span className="phone-country-view" aria-hidden="true">
          <span className="phone-flag">{flag(country.iso)}</span>
          +{country.dial}
          <svg viewBox="0 0 12 12"><path d="M3 4.5l3 3 3-3" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" /></svg>
        </span>
        <select
          aria-label={countryLabel}
          value={country.iso}
          onChange={(event) => {
            const next = phoneCountry(event.target.value);
            onChange({ iso: next.iso, number: formatPhone(value.number, next) });
          }}
        >
          {PHONE_COUNTRIES.map((option) => (
            <option key={option.iso} value={option.iso} data-dial={option.dial} data-masks={option.masks.join('|')}>{`${flag(option.iso)} ${countryName(option.iso)} (+${option.dial})`}</option>
          ))}
        </select>
      </span>
      <input
        id={id}
        name={name}
        type="tel"
        inputMode="tel"
        autoComplete="tel-national"
        required
        placeholder={placeholder}
        value={value.number}
        aria-invalid={invalid || undefined}
        onChange={(event) => onChange({ iso: country.iso, number: formatPhone(event.target.value, country) })}
      />
    </span>
  );
}
