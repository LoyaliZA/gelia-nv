// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import SelectorProducto from './SelectorProducto';
vi.mock('../Escanner/InputConEscanner', () => ({ default: ({ value, onChange, inputProps, alEscanear }) => <><input {...inputProps} value={value} onChange={onChange} /><button onClick={()=>alEscanear('CAMARA-1')}>Cámara</button></> }));
let root, container;
beforeEach(()=>{
 globalThis.IS_REACT_ACT_ENVIRONMENT=true;globalThis.route=()=>'/productos/buscar';
 vi.stubGlobal('fetch',vi.fn().mockResolvedValue({ok:true,json:async()=>({data:[{id:7,sku:'SKU-7',descripcion:'Perfume'}],current_page:1,last_page:1})}));
 container=document.createElement('div');document.body.append(container);root=createRoot(container);
});
afterEach(async()=>{await act(()=>root.unmount());container.remove();vi.unstubAllGlobals();});
it('conserva la selección de catálogo habitual sin escáner',async()=>{
 const onChange=vi.fn();await act(()=>root.render(<SelectorProducto value="" onChange={onChange}/>));
 await act(()=>container.querySelector('input[type=text]').focus());
 await act(()=>container.querySelector('.selector-productos-lista button').click());
 expect(onChange).toHaveBeenCalledWith(7,expect.objectContaining({sku:'SKU-7'}));
 expect(container.textContent).not.toContain('Cámara');
});
it('un solo campo sirve para buscar, enviar Enter y recibir la cámara sin seleccionar dos veces',async()=>{
 const onChange=vi.fn(),onEscanear=vi.fn();await act(()=>root.render(<SelectorProducto value="" onChange={onChange} onEscanear={onEscanear}/>));
 expect(container.querySelectorAll('input[type=text]')).toHaveLength(1);
 const input=container.querySelector('input[type=text]');
 await act(()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,'SKU-7');input.dispatchEvent(new Event('input',{bubbles:true}));});
 await act(()=>input.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true})));
 expect(onEscanear).toHaveBeenCalledWith('SKU-7');expect(input.value).toBe('');
 expect(container.querySelector('.selector-productos-lista')).toBeNull();
 await act(()=>container.querySelector('button').click());expect(onEscanear).toHaveBeenLastCalledWith('CAMARA-1');
 expect(onChange).not.toHaveBeenCalled();
});
