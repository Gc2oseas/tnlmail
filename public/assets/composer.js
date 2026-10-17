document.querySelectorAll('[data-rich-editor]').forEach(el=>{
 let editor=null;
 if(window.Jodit){editor=Jodit.make(el,{height:300,autofocus:!!el.value,cursorAfterAutofocus:'start',toolbarAdaptive:false,language:'es',defaultMode:1,useSplitMode:true,sourceEditor:'area',buttons:['source','|','bold','italic','underline','|','ul','ol','|','paragraph','align','|','link','image','table','|','undo','redo','fullsize'],uploader:{insertImageAsBase64URI:true},filebrowser:{ajax:{url:''}},disablePlugins:['file','video','speechRecognize'],askBeforePasteHTML:true});}
 el.form.addEventListener('submit',()=>{if(editor)el.value=editor.value;});
});
if(window.parent!==window&&document.body.classList.contains('composer-popup')&&document.body.dataset.composerResult==='sent')window.parent.postMessage({type:'tnl-compose-sent'},location.origin);
if(window.parent===window){
 let modal=null,frame=null,trigger=null;
 document.addEventListener('click',event=>{const link=event.target.closest('a[data-compose-modal]');if(!link||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;const url=new URL(link.href,location.href);if(url.origin!==location.origin)return;event.preventDefault();trigger=link;
  if(!modal){modal=document.createElement('dialog');modal.className='compose-modal';modal.setAttribute('aria-label','Redactar mensaje');const header=document.createElement('header');header.className='compose-modal-header';const title=document.createElement('strong');title.textContent='Redactar mensaje';const close=document.createElement('button');close.type='button';close.className='button-secondary';close.textContent='Cerrar';close.addEventListener('click',()=>modal.close());header.append(title,close);frame=document.createElement('iframe');frame.title='Editor del mensaje';modal.append(header,frame);document.body.append(modal);modal.addEventListener('close',()=>{document.body.classList.remove('compose-modal-open');frame.removeAttribute('src');trigger?.focus();});}
  url.searchParams.set('modal','1');frame.src=url.href;modal.showModal();document.body.classList.add('compose-modal-open');
 });
 window.addEventListener('message',event=>{if(event.origin===location.origin&&frame&&event.source===frame.contentWindow&&event.data?.type==='tnl-compose-sent'){modal.close();location.reload();}});
}

document.querySelectorAll('[data-send-account]').forEach(select=>{const note=select.form.querySelector('[data-personal-warning]');if(!note)return;const update=()=>{note.hidden=select.selectedOptions[0]?.dataset.personal!=='1';};select.addEventListener('change',update);update();});
