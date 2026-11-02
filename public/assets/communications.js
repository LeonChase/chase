(() => {
 let loading = false;
 const csrf = () => document.querySelector('meta[name="portal-csrf"]')?.content || '';
 const workspace = () => document.querySelector('.chat-workspace');
 const selected = () => workspace()?.querySelector('[data-chat-recipient]')?.dataset.chatRecipient;
 const currentUrl = () => '/messages?recipientId=' + encodeURIComponent(selected() || '');
 const streamBottom = () => { const stream = document.querySelector('.chat-stream'); if (stream) stream.scrollTop = stream.scrollHeight; };
 async function markRead() {
  if (!document.querySelector('[data-message-unread="true"]') || !selected() || document.hidden) return;
  try { const body = new URLSearchParams({_token: csrf()}); const r = await fetch('/messages/thread/' + encodeURIComponent(selected()) + '/read', {method:'POST',body,headers:{'X-Requested-With':'XMLHttpRequest'}}); if(r.ok){document.querySelectorAll('[data-message-unread]').forEach(el=>el.dataset.messageUnread='false');} } catch (_) {}
 }
 async function openThread(url, push = true) {
  if (loading) return;
  const draft = document.querySelector('[data-chat-send] textarea');
  if (draft?.value.trim() && !window.confirm('Ungesendete Nachricht verwerfen?')) return;
  loading = true;
  try { const target = new URL(url, location.origin); target.searchParams.set('partial','workspace'); const response = await fetch(target, {headers:{'X-Requested-With':'XMLHttpRequest'}}); if(!response.ok) throw new Error(); const html = await response.text(); const temp = document.createElement('template'); temp.innerHTML = html.trim(); const next = temp.content.querySelector('.chat-workspace'); if(!next) throw new Error(); workspace().replaceWith(next); if(push) history.pushState({},'',url); streamBottom(); await markRead(); } catch (_) { location.assign(url); } finally { loading = false; }
 }
 document.addEventListener('click', event => {
  const link = event.target.closest('[data-chat-thread]'); if(link && !event.ctrlKey && !event.metaKey){event.preventDefault();openThread(link.href);}
  const filter = event.target.closest('[data-chat-mailbox]'); if(filter){workspace().querySelectorAll('[data-chat-mailbox]').forEach(b=>b.classList.toggle('active',b===filter));workspace().querySelectorAll('[data-chat-thread]').forEach(t=>t.hidden=filter.dataset.chatMailbox!=='all'&&t.dataset[filter.dataset.chatMailbox]!=='true');}
 });
 document.addEventListener('submit', async event => {
  const form = event.target;
  if(form.matches('.chat-picker,.chat-welcome form')) {event.preventDefault();const data=new FormData(form);openThread('/messages?'+new URLSearchParams(data).toString());return;}
  if(!form.matches('[data-chat-send]')) return;
  event.preventDefault(); if(!form.reportValidity())return;
  const button=form.querySelector('button[type="submit"]'); button.disabled=true; const status=form.querySelector('[role="status"]'); status.classList.add('sr-only');status.textContent='Wird gesendet …';
  try { const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}}); if(response.status===201){const data=await response.json();form.querySelector('textarea').value='';await openThread(data.url,false);history.replaceState({},'',data.url);}else if(response.ok){const html=await response.text();const temp=document.createElement('template');temp.innerHTML=html;const next=temp.content.querySelector('.chat-workspace');if(next){workspace().replaceWith(next);streamBottom();}else throw new Error();}else throw new Error(); } catch (_) {status.classList.remove('sr-only');status.textContent='Senden fehlgeschlagen. Die Nachricht bleibt hier. Bitte erneut versuchen.';} finally {button.disabled=false;}
 });
 async function refreshThread(forceBottom=false){const stream=document.querySelector('.chat-stream');const person=selected();if(!stream||!person||loading)return;try{const nearBottom=stream.scrollHeight-stream.scrollTop-stream.clientHeight<70;const r=await fetch(currentUrl()+'&partial=thread',{headers:{'X-Requested-With':'XMLHttpRequest'}});if(!r.ok)return;const html=await r.text();if(person!==selected())return;const template=document.createElement('template');template.innerHTML=html;const ids=[...template.content.querySelectorAll('[data-message-id]')].map(el=>el.dataset.messageId).join(',');const current=[...stream.querySelectorAll('[data-message-id]')].map(el=>el.dataset.messageId).join(',');if(ids!==current||forceBottom){stream.innerHTML=html;if(nearBottom||forceBottom)streamBottom();}await markRead();}catch(_){}}
 document.addEventListener('keydown',event=>{if(event.target.matches('[data-chat-send] textarea')&&event.key==='Enter'&&(event.ctrlKey||event.metaKey)){event.preventDefault();event.target.form.requestSubmit();}});
 window.addEventListener('popstate',()=>{if(location.pathname==='/messages')openThread(location.href,false);});
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshThread();});
 streamBottom();markRead();setInterval(()=>{if(!document.hidden)refreshThread();},15000);
})();
