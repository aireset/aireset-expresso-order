import { useEffect, useRef } from 'react';
import type { ProductResult } from './app/types';
import { adminApi } from './app/api';

/* eslint-disable @typescript-eslint/no-explicit-any */

function ProductSelect2({ onSelect }: { onSelect: (product: ProductResult) => void }) {
  const selectRef = useRef<HTMLSelectElement>(null);
  const onSelectRef = useRef(onSelect);
  onSelectRef.current = onSelect;

  useEffect(() => {
    const jq = (window as any).jQuery;
    const element = selectRef.current;

    if (!jq || !element || typeof jq(element).select2 !== 'function') {
      return;
    }

    const $el = jq(element);

    $el.select2({
      width: '100%',
      placeholder: 'Buscar produto por nome ou SKU...',
      minimumInputLength: 2,
      language: {
        inputTooShort: () => 'Digite ao menos 2 letras...',
        searching: () => 'Buscando...',
        noResults: () => 'Nenhum produto encontrado.',
      },
      ajax: {
        delay: 250,
        transport: (params: any, success: any, failure: any) => {
          adminApi
            .searchProducts(params.data.term || '')
            .then((payload) =>
              success({
                results: payload.results.map((product) => ({
                  id: product.id,
                  text: product.text || product.name,
                  product,
                })),
              })
            )
            .catch(failure);

          return { abort: () => undefined };
        },
      },
      templateResult: (item: any) => {
        if (!item.product) {
          return item.text;
        }

        const product = item.product as ProductResult;
        const wrap = jq('<span class="eop-select2-product"></span>');

        if (product.image) {
          wrap.append(jq('<img alt="" />').attr('src', product.image));
        }

        wrap.append(jq('<span></span>').text(item.text));
        return wrap;
      },
    });

    const handler = (event: any) => {
      const product = event.params?.data?.product as ProductResult | undefined;

      if (product) {
        onSelectRef.current(product);
      }

      $el.val(null).trigger('change');
    };

    $el.on('select2:select', handler);

    return () => {
      $el.off('select2:select', handler);
      if (typeof $el.select2 === 'function') {
        $el.select2('destroy');
      }
    };
  }, []);

  return <select ref={selectRef} style={{ width: '100%' }} />;
}

export default ProductSelect2;
