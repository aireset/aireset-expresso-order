import { useEffect, useRef } from 'react';

/* eslint-disable @typescript-eslint/no-explicit-any */

export type Select2Option = { value: string; label: string };

/**
 * Select com busca (Select2 do WooCommerce) para listas estaticas.
 * Mantem o <select> nativo controlado pelo React e apenas decora com Select2.
 */
function Select2({
  value,
  options,
  onChange,
  ariaLabel,
  placeholder,
}: {
  value: string;
  options: Select2Option[];
  onChange: (value: string) => void;
  ariaLabel?: string;
  placeholder?: string;
}) {
  const selectRef = useRef<HTMLSelectElement>(null);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  useEffect(() => {
    const jq = (window as any).jQuery;
    const element = selectRef.current;

    if (!jq || !element || typeof jq(element).select2 !== 'function') {
      return;
    }

    const $el = jq(element);

    $el.select2({
      width: '100%',
      minimumResultsForSearch: 0,
      placeholder: placeholder || '',
      language: {
        noResults: () => 'Nada encontrado.',
        searching: () => 'Buscando...',
      },
    });

    const handler = () => onChangeRef.current(String($el.val() ?? ''));
    $el.on('select2:select', handler);

    return () => {
      $el.off('select2:select', handler);
      if (typeof $el.select2 === 'function') {
        $el.select2('destroy');
      }
    };
  }, []);

  // Sincroniza valor vindo de fora (sem disparar onChange).
  useEffect(() => {
    const jq = (window as any).jQuery;
    const element = selectRef.current;

    if (jq && element && typeof jq(element).select2 === 'function') {
      if (String(jq(element).val() ?? '') !== value) {
        jq(element).val(value).trigger('change.select2');
      }
    }
  }, [value]);

  return (
    <select ref={selectRef} defaultValue={value} aria-label={ariaLabel}>
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}

export default Select2;
