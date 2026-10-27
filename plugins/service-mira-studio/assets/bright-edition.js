/* Bright Edition interaction: only presentation. Existing quotation logic is untouched. */
(function () {
  'use strict';
  var root=document.querySelector('[data-mx-root]');
  if (!root) return;
  var select=root.querySelector('#mx-service-select');
  // Progressive enhancement: retain the real native select as the source of truth.
  if(select && !root.querySelector('.mx-service-picker')) {
    var wrapper=document.createElement('div'); wrapper.className='mx-service-picker';
    var trigger=document.createElement('button'); trigger.type='button';trigger.className='mx-service-picker-trigger';trigger.setAttribute('aria-haspopup','listbox');trigger.setAttribute('aria-expanded','false');
    var list=document.createElement('div');list.className='mx-service-picker-list';list.setAttribute('role','listbox');list.hidden=true;
    var listId='mx-service-picker-options';list.id=listId;trigger.setAttribute('aria-controls',listId);
    var opts=[];var sync=function(){trigger.textContent=select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent : 'Leistung wählen';opts.forEach(function(o){o.setAttribute('aria-selected',o.dataset.value===select.value?'true':'false');});};
    var close=function(){list.hidden=true;trigger.setAttribute('aria-expanded','false');};
    Array.prototype.forEach.call(select.options,function(option){var o=document.createElement('button');o.type='button';o.setAttribute('role','option');o.dataset.value=option.value;o.textContent=option.textContent;o.addEventListener('click',function(){select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();close();trigger.focus();});list.appendChild(o);opts.push(o);});
    trigger.addEventListener('click',function(){var opening=list.hidden;list.hidden=!opening;trigger.setAttribute('aria-expanded',opening?'true':'false');if(opening){var selected=opts.find(function(o){return o.dataset.value===select.value;});(selected||opts[0]).focus();}});
    wrapper.addEventListener('keydown',function(e){if(e.key==='Escape'){close();trigger.focus();}else if(!list.hidden&&(e.key==='ArrowDown'||e.key==='ArrowUp')){e.preventDefault();var i=opts.indexOf(document.activeElement);opts[(i+(e.key==='ArrowDown'?1:-1)+opts.length)%opts.length].focus();}else if(!list.hidden&&e.key==='Home'){e.preventDefault();opts[0].focus();}else if(!list.hidden&&e.key==='End'){e.preventDefault();opts[opts.length-1].focus();}});
    document.addEventListener('click',function(e){if(!wrapper.contains(e.target))close();});
    select.addEventListener('change',sync); select.parentNode.insertBefore(wrapper,select.nextSibling);wrapper.appendChild(trigger);wrapper.appendChild(list);
    sync();select.classList.add('mx-service-native-hidden');select.tabIndex=-1;select.setAttribute('aria-hidden','true');
  }
  var hours=root.querySelector('#mx-hours');
  var status=root.querySelector('#mx-quote-status');
  if(select && hours && status) {
    var update=function () {
      var n=Number(hours.value);
      if(!Number.isFinite(n)||n<.25||n>1000||Math.round(n*4)!==n*4){
        status.textContent='Bitte einen gültigen Stundenumfang in Viertelstunden-Schritten auswählen.';
        return;
      }
      if(select.value){
        status.textContent='Auswahl: '+select.value+' · '+n.toLocaleString('de-DE')+' Stunden. Bitte Leistung und Umfang auf der Angebotsseite nochmals bestätigen; verbindliche Preise stehen noch aus.';
      } else {
        status.textContent='Wählen Sie eine Leistung. Die endgültigen Preise und Konditionen werden vor einer Buchung bestätigt.';
      }
    };
    select.addEventListener('change',update);hours.addEventListener('input',update);
  }
  // Safe UI-only handoff: quote plugin validates these values against known services and allowed durations.
  var quoteGo=root.querySelector('.mx-quote-go');
  if (quoteGo && select && hours) {
    var serviceIds={'Unterhaltsreinigung':'unterhalt','Einkaufshilfe':'einkauf','Gartenarbeit':'garten','Haushaltsreinigung':'haushalt','Treppenreinigung':'treppen','Treppenhaus':'treppen','Büroreinigung':'buero','Praxisreinigung':'praxis','Bauendreinigung':'bau','Grundreinigung':'grund','Fensterreinigung':'fenster'};
    quoteGo.addEventListener('click',function(e){
      var id=serviceIds[select.value], n=Number(hours.value);
      if(!id || !Number.isFinite(n) || n<.25 || n>100 || Math.round(n*4)!==n*4){ e.preventDefault();status.textContent='Bitte Leistung und eine gültige Dauer auswählen.';return; }
      var u=new URL(quoteGo.href,location.href);u.searchParams.set('mira_service',id);u.searchParams.set('mira_hours',String(n));quoteGo.href=u.toString();
    });
  }
  var compare=root.querySelector('[data-mx-compare]');
  if (compare) {
    var range=compare.querySelector('.mx-compare-range');
    var updateCompare=function () {
      var p=Number(range.value);
      compare.style.setProperty('--mx-split',p+'%');
      compare.style.setProperty('--mx-split-num',String(Math.max(.01,p/100)));
      var image=compare.querySelector('.mx-compare-right');
      if(image) image.style.width=String(10000/Math.max(1,p))+'%';
    };
    range.addEventListener('input',updateCompare);
    root.querySelectorAll('[data-mx-nudge]').forEach(function(btn){
      btn.addEventListener('click',function(){
        var next=Math.min(95,Math.max(5,Number(range.value)+Number(btn.getAttribute('data-mx-nudge'))));
        range.value=String(next);updateCompare();range.focus({preventScroll:true});
      });
    });
    updateCompare();
  }
})();
