document.querySelectorAll('[data-rich-editor]').forEach(el=>{
 const format=el.form.querySelector('[name="format"]');let editor=null;
 const change=()=>{if(editor){editor.destruct();editor=null;}if(format.value==='html'&&window.Jodit){editor=Jodit.make(el,{height:400,toolbarAdaptive:false,language:'es',defaultMode:1,useSplitMode:true,sourceEditor:'area',buttons:['source','|','bold','italic','underline','strikethrough','|','ul','ol','|','paragraph','align','|','link','image','table','hr','|','undo','redo','preview','fullsize'],uploader:{insertImageAsBase64URI:true},filebrowser:{ajax:{url:''}},disablePlugins:['file','video','speechRecognize'],askBeforePasteHTML:true});}};
 format.addEventListener('change',()=>{if(editor){el.value=editor.value;editor.destruct();editor=null;}change();});el.form.addEventListener('submit',()=>{if(editor)el.value=editor.value;});change();
});
