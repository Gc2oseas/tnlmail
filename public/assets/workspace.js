/* Navegación adaptable, sin dependencias externas. */
document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
  var sidebar = button.closest('.workspace-sidebar');
  function closeMenu() { sidebar.classList.remove('nav-expanded'); button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-label', 'Abrir navegación'); }
  button.addEventListener('click', function () { var open = sidebar.classList.toggle('nav-expanded'); button.setAttribute('aria-expanded', String(open)); button.setAttribute('aria-label', open ? 'Cerrar navegación' : 'Abrir navegación'); });
  sidebar.querySelectorAll('nav a').forEach(function (link) { link.addEventListener('click', closeMenu); });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && sidebar.classList.contains('nav-expanded')) { closeMenu(); button.focus(); } });
});

document.querySelectorAll('[data-directory]').forEach(input => {
 const list=document.createElement('div');list.className='recipient-suggestions';list.setAttribute('role','group');list.setAttribute('aria-label','Usuarios sugeridos');input.after(list);
 const status=document.createElement('small');status.setAttribute('role','status');list.after(status);
 let timer,controller,version=0;
 input.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();const current=++version;list.replaceChildren();status.textContent='';const q=input.value.trim();if(q.length<2||q.includes('@'))return;
 timer=setTimeout(async()=>{controller=new AbortController();try{
 const response=await fetch('directory.php?q='+encodeURIComponent(q),{signal:controller.signal,headers:{Accept:'application/json'}});
 if(!response.ok||response.redirected)return;const data=await response.json();if(current!==version)return;
 for(const user of data.results||[]){const button=document.createElement('button');button.type='button';button.className='button-ghost';button.textContent=user.label;button.addEventListener('click',()=>{input.value=user.username;list.replaceChildren();status.textContent='Destinatario: '+user.label;input.focus();});list.append(button);}
 if(data.results?.length===20)status.textContent='Mostrando 20 usuarios. Escribe más para afinar la búsqueda.';
 }catch(e){if(e.name!=='AbortError'&&current===version)status.textContent='No se pudieron cargar las sugerencias; escribe el usuario exacto.';}},250);});
 input.addEventListener('keydown',e=>{if(e.key==='ArrowDown'&&list.firstElementChild){e.preventDefault();list.firstElementChild.focus();}if(e.key==='Escape')list.replaceChildren();});
 list.addEventListener('keydown',e=>{if(e.key==='Escape'){list.replaceChildren();input.focus();}if(e.key==='ArrowDown'){e.preventDefault();e.target.nextElementSibling?.focus();}if(e.key==='ArrowUp'){e.preventDefault();(e.target.previousElementSibling||input).focus();}});
});
document.querySelectorAll('[data-provider-email]').forEach(input=>{
 const form=input.form,mode=form.querySelector('[data-provider-mode]'),fields=form.querySelector('[data-provider-fields]'),status=form.querySelector('[data-provider-status]');let timer,controller,version=0;
 function update(){fields.open=mode.value==='manual';clearTimeout(timer);controller?.abort();const current=++version;if(mode.value!=='auto')return;
 const email=input.value.trim();if(!input.validity.valid||!email)return;
 timer=setTimeout(async()=>{controller=new AbortController();status.textContent='Detectando proveedor…';try{
 const response=await fetch('provider.php?email='+encodeURIComponent(email),{signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok||response.redirected)return;
 const data=await response.json();if(current!==version)return;const profile=data.profile;
 if(!profile){status.textContent='No se reconoció el proveedor. Abre Configuración avanzada o consulta a administración.';return;}
 status.textContent=profile.provider+'. '+profile.hint;
 for(const key of ['imap_host','smtp_host','smtp_port'])if(form.elements[key])form.elements[key].value=profile[key];
 }catch(e){if(e.name!=='AbortError'&&current===version)status.textContent='No se pudo detectar. Puedes usar Configuración avanzada.';}},400);}
 input.addEventListener('input',update);mode.addEventListener('change',update);update();
});

document.querySelectorAll('[data-mail-actions]').forEach(button=>button.addEventListener('click',()=>{
 const row=button.closest('.mail-row-wrap'),open=!row.classList.contains('actions-open');
 row.classList.toggle('actions-open',open);button.setAttribute('aria-expanded',String(open));
}));
document.addEventListener('keydown',event=>{if(event.key==='Escape')document.querySelectorAll('.mail-row-wrap.actions-open').forEach(row=>{row.classList.remove('actions-open');row.querySelector('[data-mail-actions]')?.setAttribute('aria-expanded','false');});});
document.querySelectorAll('iframe[data-mail-view]').forEach(frame=>{
 function size(){try{const doc=frame.contentDocument;if(doc)frame.height=String(Math.max(140,Math.min(70000,doc.documentElement.scrollHeight+16)));}catch{}}
 frame.addEventListener('load',()=>{size();try{const doc=frame.contentDocument;if(doc){new ResizeObserver(size).observe(doc.body);doc.querySelectorAll('img').forEach(img=>img.addEventListener('load',size));}}catch{}});
 if(frame.contentDocument?.readyState==='complete')size();
});
