(function(){
'use strict';
function init(){
 var original=document.querySelector('body.sm-page-contact .sm-contact-original');
 if(!original || original.dataset.modernized==='1')return;
 var text=(original.innerText||original.textContent||'').replace(/\u00a0/g,' ').replace(/\s+/g,' ').trim();
 var email=(text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i)||[])[0];
 var phoneMatch=text.match(/(?:Tel(?:efon)?\s*[:.]?\s*)(\+?[\d][\d\s/()\-]{7,})/i);
 var phone=phoneMatch&&phoneMatch[1]&&phoneMatch[1].trim();
 if(!email||!phone)return; // never replace unrecognized original contacts
 function hours(day){
  var re=new RegExp(day+'[\\s\\S]{0,45}?(\\d{1,2})\\s*[:.]\\s*(\\d{2})\\s*(?:bis|[-–—])\\s*(\\d{1,2})\\s*[:.]\\s*(\\d{2})','i');
  var m=text.match(re);
  return m?[m[1].padStart(2,'0')+':'+m[2],m[3].padStart(2,'0')+':'+m[4]]:null;
 }
 var weekday=hours('Mo\\s*[-–—]\\s*Fr');
 var weekend=hours('Sa\\s*[-–—]\\s*So');
 var card=document.createElement('div');card.className='sm-contact-modern';
 function row(icon,label,value,href){
   var outer=document.createElement('div');outer.className='sm-contact-modern-row';
   var symbol=document.createElement('span');symbol.className='sm-contact-modern-icon';symbol.textContent=icon;symbol.setAttribute('aria-hidden','true');
   var copy=document.createElement('div');copy.className='sm-contact-modern-copy';
   var title=document.createElement('span');title.className='sm-contact-modern-label';title.textContent=label;
   var val=href?document.createElement('a'):document.createElement('span');val.className='sm-contact-modern-value';val.textContent=value;if(href)val.href=href;
   copy.append(title,val);outer.append(symbol,copy);card.append(outer);
 }
 row('✉','E-Mail',email,'mailto:'+email);
 row('☎','Telefon',phone,'tel:'+phone.replace(/[^+\d]/g,''));
 var web=Array.from(original.querySelectorAll('a[href^="http"]')).find(function(a){try{return /(^|\.)service-mira\.de$/i.test(new URL(a.href).hostname);}catch(e){return false;}});
 if(web)row('↗','Website',web.textContent.trim()||'service-mira.de',web.href);
 if(weekday||weekend){
  var schedule=document.createElement('section');schedule.className='sm-contact-modern-hours';
  var heading=document.createElement('div');heading.className='sm-contact-modern-hours-title';heading.textContent='Öffnungszeiten';schedule.append(heading);
  function add(label,range){if(!range)return;var line=document.createElement('div');line.className='sm-contact-modern-hours-line';var a=document.createElement('span');a.textContent=label;var b=document.createElement('strong');b.textContent=range[0]+'–'+range[1]+' Uhr';line.append(a,b);schedule.append(line);}
  add('Mo–Fr',weekday);add('Sa–So',weekend);card.append(schedule);
 }
 original.parentNode.insertBefore(card,original);
 if(weekday&&weekend){original.hidden=true;original.style.display='none';}else{
  var detail=document.createElement('details');detail.className='sm-contact-original-details';var summary=document.createElement('summary');summary.textContent='Weitere Angaben zu den Öffnungszeiten';detail.append(summary);original.parentNode.insertBefore(detail,original);detail.append(original);
 }
 original.dataset.modernized='1';document.body.classList.add('sm-contact-enhanced');
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
