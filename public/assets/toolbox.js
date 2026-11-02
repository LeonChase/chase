(() => {
 'use strict';
 document.addEventListener('click', async event=>{
  const opener=event.target.closest('[data-toolbox-dialog]');if(opener)document.getElementById(opener.dataset.toolboxDialog)?.showModal();
  const closer=event.target.closest('[data-toolbox-close]');if(closer)closer.closest('dialog')?.close();
  const copy=event.target.closest('[data-toolbox-copy]');if(copy){const input=document.querySelector(copy.dataset.toolboxCopy),status=document.querySelector('[data-toolbox-status]');try{await navigator.clipboard.writeText(input.value);if(status)status.textContent='Text kopiert.';}catch(_){input.focus();input.select();if(status)status.textContent='Text markiert. Mit Strg+C kopieren.';}}
 });
 document.addEventListener('submit',event=>{
  const form=event.target;if(form.matches('[data-toolbox-confirm]')&&!window.confirm(form.dataset.toolboxConfirm))event.preventDefault();
  if(form.matches('[data-toolbox-jump]')){event.preventDefault();const value=new FormData(form).get('property');if(value)location.assign('/properties/'+encodeURIComponent(value)+'#'+form.dataset.section);}
 });
 document.querySelectorAll('.toolbox-dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog){const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)dialog.close();}}));
 const textRoot=document.querySelector('[data-toolbox-texts]');
 if(textRoot){const property=textRoot.querySelector('[data-toolbox-property]'),manual=textRoot.querySelector('[data-toolbox-manual]'),tool=textRoot.querySelector('[data-toolbox-tool]');property.addEventListener('change',()=>{manual.hidden=property.value!=='';});tool.addEventListener('change',()=>textRoot.querySelectorAll('[data-toolbox-only]').forEach(el=>el.hidden=!el.dataset.toolboxOnly.split(' ').includes(tool.value)));}
 const editor=document.querySelector('[data-image-editor]');if(!editor)return;
 const input=editor.querySelector('#toolbox-image-input'),canvas=editor.querySelector('canvas'),ctx=canvas.getContext('2d'),empty=editor.querySelector('[data-image-empty]'),settings=editor.querySelector('[data-image-settings]'),download=editor.querySelector('[data-image-download]'),status=editor.querySelector('[data-image-status]'),name=editor.querySelector('[data-image-name]');
 if(!ctx){status.textContent='Dein Browser unterstützt diese Bildbearbeitung nicht.';return;}
 let source=null,rotation=0,objectUrl=null,filename='CHASE-Bild',sequence=0;
 editor.querySelector('[data-image-select]').addEventListener('click',()=>input.click());
 const message=(text,error=false)=>{status.textContent=text;status.classList.toggle('is-error',error);};
 const values=()=>Object.fromEntries([...editor.querySelectorAll('[data-image-filter]')].map(el=>[el.dataset.imageFilter,Number(el.value)]));
 function draw(){if(!source)return;const v=values(),turned=rotation%180!==0;canvas.width=turned?source.naturalHeight:source.naturalWidth;canvas.height=turned?source.naturalWidth:source.naturalHeight;ctx.save();ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(rotation*Math.PI/180);ctx.filter='brightness('+v.brightness+'%) contrast('+v.contrast+'%) saturate('+v.saturate+'%)';ctx.drawImage(source,-source.naturalWidth/2,-source.naturalHeight/2);ctx.restore();editor.querySelectorAll('[data-image-output]').forEach(el=>el.textContent=v[el.dataset.imageOutput]+' %');}
 input.addEventListener('change',()=>{const current=++sequence,file=input.files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>15*1024*1024||file.size===0){message('Bitte JPG, PNG oder WebP bis 15 MB auswählen.',true);return;}const url=URL.createObjectURL(file),image=new Image();image.onload=()=>{if(current!==sequence){URL.revokeObjectURL(url);return;}if(image.naturalWidth*image.naturalHeight>25000000){URL.revokeObjectURL(url);message('Bitte ein Bild mit höchstens 25 Megapixeln verwenden.',true);return;}if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=url;source=image;rotation=0;editor.querySelectorAll('[data-image-filter]').forEach(el=>el.value=100);filename=(file.name.replace(/\.[^.]+$/,'').replace(/[^\p{L}\p{N} _-]/gu,'').slice(0,100)||'CHASE-Bild')+'-bearbeitet';name.textContent=file.name+' · '+image.naturalWidth+' × '+image.naturalHeight+' Pixel';settings.inert=false;download.disabled=false;empty.hidden=true;canvas.hidden=false;draw();message('Bereit. Änderungen werden lokal berechnet.');};image.onerror=()=>{URL.revokeObjectURL(url);if(current===sequence)message('Das Bild konnte nicht geöffnet werden.',true);};image.src=url;});
 editor.querySelectorAll('[data-image-filter]').forEach(el=>el.addEventListener('input',draw));
 editor.querySelector('[data-image-rotate]').addEventListener('click',()=>{rotation=(rotation+90)%360;draw();});
 editor.querySelector('[data-image-reset]').addEventListener('click',()=>{rotation=0;editor.querySelectorAll('[data-image-filter]').forEach(el=>el.value=100);draw();message('Originaldarstellung wiederhergestellt.');});
 download.addEventListener('click',()=>{if(!source)return;canvas.toBlob(blob=>{if(!blob){message('Export fehlgeschlagen. Bitte ein kleineres Bild verwenden.',true);return;}const url=URL.createObjectURL(blob),link=document.createElement('a');link.href=url;link.download=filename+'.png';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);message('PNG erstellt. Dein Original bleibt unverändert.');},'image/png');});
 window.addEventListener('pagehide',()=>{if(objectUrl)URL.revokeObjectURL(objectUrl);});
})();
