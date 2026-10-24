'use strict';
(() => {
  const input=document.getElementById('update-package'), zone=document.getElementById('upload-zone'), name=document.getElementById('upload-name');
  if(!input||!zone||!name)return;
  const show=()=>{name.textContent=input.files[0]?.name||'ZIP files only · maximum 20 MB';};
  input.addEventListener('change',show);
  for(const event of ['dragenter','dragover'])zone.addEventListener(event,e=>{e.preventDefault();if(!input.disabled)zone.classList.add('dragging');});
  for(const event of ['dragleave','drop'])zone.addEventListener(event,e=>{e.preventDefault();zone.classList.remove('dragging');});
  zone.addEventListener('drop',e=>{if(input.disabled)return;if(e.dataTransfer.files.length===1){input.files=e.dataTransfer.files;show();}});
})();
