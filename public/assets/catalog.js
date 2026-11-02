(() => {
 document.addEventListener('click', event => {
  const button=event.target.closest('[data-quantity-change]');if(!button)return;
  const input=button.closest('[data-quantity-control]')?.querySelector('input');if(!input)return;
  const minimum=Number(input.min)||0;const maximum=Number(input.max)||Number.MAX_SAFE_INTEGER;
  const current=Number(input.value);input.value=String(Math.min(maximum,Math.max(minimum,(Number.isSafeInteger(current)?current:minimum)+Number(button.dataset.quantityChange))));input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));
 });
})();
