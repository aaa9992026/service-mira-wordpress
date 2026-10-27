/* Quote UI: display only. Prices and acceptance are always recalculated by PHP. */
(function (root) {
  'use strict';
  const money = cents => cents === null || cents === undefined ? 'Bestätigung ausstehend' : new Intl.NumberFormat('de-DE', {style: 'currency', currency: 'EUR'}).format(cents / 100);
  function calculate(s, id, hours) {
    const v = s.services[id];
    const raw = Number(String(hours).replace(',', '.'));
    const units = Math.round(raw * 4);
    if (!v || !Number.isFinite(units) || units < 1 || units > 400 || Math.abs(raw * 4 - units) > 0.00001) return null;
    if (s.minimum_units !== null && units < s.minimum_units) return {error: 'Die Mindestbuchung beträgt ' + s.minimum_units / 4 + ' Stunden.'};
    const base = v.cents === null ? null : Math.floor((v.cents * units + 2) / 4);
    const complete = base !== null && v.confirmed && v.scope.trim() !== '' && s.fee_cents !== null && s.fee_label.trim() !== '' && ['net', 'gross', 'exempt'].includes(s.vat_mode) && (s.vat_mode === 'exempt' || s.vat_bps !== null);
    let net = null, tax = null, gross = null;
    if (!complete && base !== null && s.vat_mode === 'net' && s.vat_bps !== null) { net = base; tax = Math.floor((net * s.vat_bps + 5000) / 10000); }
    if (complete) {
      const total = base + s.fee_cents;
      if (s.vat_mode === 'net') {net = total; tax = Math.floor((net * s.vat_bps + 5000) / 10000); gross = net + tax;}
      else if (s.vat_mode === 'gross') {gross = total; net = Math.floor((gross * 10000 + Math.floor((10000 + s.vat_bps) / 2)) / (10000 + s.vat_bps)); tax = gross - net;}
      else {net = gross = total; tax = 0;}
    }
    return {base, net, tax, gross, complete};
  }
  if (typeof module !== 'undefined') module.exports = {calculate, money};
  if (!root.document) return;
  root.document.querySelectorAll('.mira-form').forEach(form => {
    let s; try { s = JSON.parse(form.dataset.miraConfig); } catch (e) { return; }
    const serviceInputs = Array.from(form.querySelectorAll('input[name="service"]'));
    const hours = form.elements.hours;
    const date = form.querySelector('#mira-date');
    const dateValue = form.elements.preferred_date;
    const datePicker = form.querySelector('#mira-date-picker');
    const isoFromGerman = value => {
      const match = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(value.trim());
      if (!match) return '';
      const [, dd, mm, yyyy] = match;
      const iso = yyyy + '-' + mm + '-' + dd;
      const d = new Date(Date.UTC(Number(yyyy), Number(mm) - 1, Number(dd)));
      if (d.getUTCFullYear() !== Number(yyyy) || d.getUTCMonth() !== Number(mm) - 1 || d.getUTCDate() !== Number(dd)) return '';
      if (iso < datePicker.min) return '';
      const max = new Date(datePicker.min + 'T12:00:00Z');
      max.setUTCDate(max.getUTCDate() + 365);
      if (iso > max.toISOString().slice(0,10)) return '';
      return iso;
    };
    const syncGermanDate = () => {
      const iso = isoFromGerman(date.value);
      dateValue.value = iso;
      if (iso) datePicker.value = iso;
      date.setCustomValidity(date.value && !iso ? 'Bitte ein gültiges Datum im Format TT.MM.JJJJ innerhalb der nächsten 365 Tage eingeben.' : '');
    };
    const time = form.elements.preferred_time;
    const freqInputs = Array.from(form.querySelectorAll('input[name="frequency"]'));
    const durationButtons = Array.from(form.querySelectorAll('.mira-duration-button'));
    const serviceId = () => (serviceInputs.find(i => i.checked) || {}).value || '';
    const field = key => form.querySelector('[data-mira-summary="' + key + '"]');
    const write = (key, value) => {const node = field(key); if (node) node.textContent = value;};
    const chosenFrequency = () => (freqInputs.find(i => i.checked) || {}).value || 'once';
    const freqNames = {once:'Einmalig', weekly:'Wöchentlich', biweekly:'Alle 2 Wochen', monthly:'Monatlich'};
    const uiPrice = form.querySelector('.mira-price');
    const line = (parent, value, kind, className) => {const n = root.document.createElement(kind || 'p'); if (className) n.className = className; n.textContent = value; parent.appendChild(n);};
    const update = () => {
      const id = serviceId(), v = s.services[id], p = calculate(s, id, hours.value);
      const num = Number(hours.value), duration = Number.isFinite(num) && num > 0 ? String(num).replace('.', ',') + ' Std.' : 'Bitte wählen';
      durationButtons.forEach(b => {const selected = Number(b.dataset.hours) === num; b.setAttribute('aria-pressed', selected ? 'true' : 'false'); b.classList.toggle('is-active', selected);});
      write('service', v ? v.name : 'Noch nicht gewählt'); write('duration', duration);
      const germanDate = dateValue.value ? date.value : '';
      write('date', germanDate || 'Bitte auswählen');
      const dateHint = form.querySelector('#mira-date-hint');
      if (dateHint) dateHint.textContent = germanDate ? 'Gewähltes Datum: ' + germanDate : 'Datumsformat: TT.MM.JJJJ';
      write('time', time.value ? time.value + ' Uhr' : 'Bitte auswählen');
      write('frequency', freqNames[chosenFrequency()] || 'Bitte auswählen');
      uiPrice.replaceChildren();
      if (!v) {line(uiPrice, 'Wählen Sie eine Leistung.', 'strong'); line(uiPrice, 'Danach wird die Preisgrundlage angezeigt.', 'p'); return;}
      if (!p) {line(uiPrice, 'Bitte gültige Stunden auswählen.', 'strong'); return;}
      if (p.error) {line(uiPrice, p.error, 'strong'); return;}
      if (p.complete) {
        line(uiPrice, 'Gesamtpreis pro Termin', 'span', 'mira-price-label');
        line(uiPrice, money(p.gross), 'strong', 'mira-price-value');
        line(uiPrice, 'Leistung: ' + money(p.base) + ' · Gebühren: ' + money(s.fee_cents) + ' · Steuer: ' + money(p.tax), 'p');
        line(uiPrice, 'Ihr Terminwunsch muss noch bestätigt werden. Diese Anfrage bucht keinen Termin.', 'p');
      } else {
        line(uiPrice, p.base !== null ? 'Berechnungsbasis pro Termin' : 'Preis auf Anfrage', 'span', 'mira-price-label');
        line(uiPrice, p.base !== null ? money(p.base) : 'Individuelles Angebot', 'strong', 'mira-price-value');
        if (v.cents !== null) line(uiPrice, 'Stundensatz netto: ' + money(v.cents) + ' × ' + duration, 'p');
        if (p.tax !== null) line(uiPrice, '19 % MwSt. auf Leistung: ' + money(p.tax) + ' · Zwischensumme brutto ohne Anfahrt: ' + money(p.net + p.tax), 'p');
        line(uiPrice, 'Anfahrt: erste 10 km frei, darüber 0,50 € / km. Fahrstrecke noch nicht bestätigt – deshalb kein verbindlicher Gesamtpreis.', 'p', 'mira-warning');
      }
    };
    durationButtons.forEach(b => b.addEventListener('click', () => {hours.value = b.dataset.hours; update();}));
    serviceInputs.forEach(i => i.addEventListener('change', update));
    freqInputs.forEach(i => i.addEventListener('change', update));
    [hours, time].forEach(i => {i.addEventListener('input', update); i.addEventListener('change', update);});
    date.addEventListener('input', () => {syncGermanDate(); update();});
    date.addEventListener('change', () => {syncGermanDate(); update();});
    const calendarButton = form.querySelector('.mira-calendar-control');
    if (calendarButton && datePicker) {
      const wrapper = calendarButton.closest('.mira-german-date');
      const panel = root.document.createElement('div');
      panel.className = 'mira-custom-calendar';
      panel.id = 'mira-calendar-panel';
      panel.setAttribute('role', 'dialog');
      panel.setAttribute('aria-label', 'Datum auswählen');
      panel.hidden = true;
      wrapper.appendChild(panel);
      calendarButton.setAttribute('aria-controls', panel.id);
      calendarButton.setAttribute('aria-expanded', 'false');
      const todayIso = datePicker.min || new Date().toISOString().slice(0, 10);
      const latest = new Date(todayIso + 'T12:00:00Z');
      latest.setUTCDate(latest.getUTCDate() + 365);
      const lastIso = latest.toISOString().slice(0, 10);
      const asIso = (y,m,d) => String(y).padStart(4,'0') + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
      let shown = dateValue.value || todayIso;
      let year = Number(shown.slice(0,4)), month = Number(shown.slice(5,7)) - 1;
      const monthFormatter = new Intl.DateTimeFormat('de-DE', {month:'long', year:'numeric', timeZone:'UTC'});
      const close = (focus) => {panel.hidden = true;calendarButton.setAttribute('aria-expanded','false');if(focus) calendarButton.focus();};
      const render = () => {
        panel.replaceChildren();
        const header = root.document.createElement('div');header.className = 'mira-cal-nav';
        const back = root.document.createElement('button');back.type='button';back.textContent='‹';back.setAttribute('aria-label','Vorheriger Monat');
        const title = root.document.createElement('strong');title.textContent=monthFormatter.format(new Date(Date.UTC(year,month,1)));
        const forward = root.document.createElement('button');forward.type='button';forward.textContent='›';forward.setAttribute('aria-label','Nächster Monat');
        const firstMonth=asIso(year,month+1,1), lastMonth=asIso(year,month+1,new Date(Date.UTC(year,month+1,0)).getUTCDate());
        back.disabled=firstMonth<=todayIso;
        forward.disabled=lastMonth>=lastIso;
        back.addEventListener('click',()=>{if(month===0){month=11;year--;}else month--;render();});
        forward.addEventListener('click',()=>{if(month===11){month=0;year++;}else month++;render();});
        header.append(back,title,forward);panel.appendChild(header);
        const grid=root.document.createElement('div');grid.className='mira-cal-grid';
        ['Mo','Di','Mi','Do','Fr','Sa','So'].forEach(t=>{const n=root.document.createElement('span');n.className='mira-cal-weekday';n.textContent=t;grid.appendChild(n);});
        const offset=(new Date(Date.UTC(year,month,1)).getUTCDay()+6)%7;
        const count=new Date(Date.UTC(year,month+1,0)).getUTCDate();
        for(let i=0;i<offset;i++){const n=root.document.createElement('span');grid.appendChild(n);}
        for(let day=1;day<=count;day++){
          const iso=asIso(year,month+1,day), b=root.document.createElement('button');b.type='button';b.textContent=String(day);b.disabled=iso<todayIso||iso>lastIso;
          if(iso===dateValue.value)b.classList.add('is-selected');
          if(iso===todayIso)b.classList.add('is-today');
          b.setAttribute('aria-label',new Intl.DateTimeFormat('de-DE',{day:'numeric',month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(iso+'T12:00:00Z')));
          b.addEventListener('click',()=>{date.value=iso.slice(8,10)+'.'+iso.slice(5,7)+'.'+iso.slice(0,4);syncGermanDate();update();close(true);});
          grid.appendChild(b);
        }
        panel.appendChild(grid);
        const foot=root.document.createElement('div');foot.className='mira-cal-footer';
        const today=root.document.createElement('button');today.type='button';today.textContent='Heute';today.addEventListener('click',()=>{date.value=todayIso.slice(8,10)+'.'+todayIso.slice(5,7)+'.'+todayIso.slice(0,4);syncGermanDate();update();close(true);});
        foot.appendChild(today);panel.appendChild(foot);
      };
      calendarButton.addEventListener('click',()=>{
        if(!panel.hidden){close(false);return;}
        const selected=dateValue.value||todayIso;year=Number(selected.slice(0,4));month=Number(selected.slice(5,7))-1;
        render();panel.hidden=false;calendarButton.setAttribute('aria-expanded','true');
      });
      root.document.addEventListener('pointerdown',e=>{if(!panel.hidden&&!wrapper.contains(e.target))close(false);});
      form.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){close(true);e.preventDefault();}});
    }
    datePicker.addEventListener('change', () => {
      if (datePicker.value) date.value = datePicker.value.split('-').reverse().join('.');
      syncGermanDate(); update();
    });
    form.addEventListener('submit', e => {
      syncGermanDate();
      if (!dateValue.value) {e.preventDefault(); date.reportValidity();}
    });
    syncGermanDate();
    // Only recognize canonical service IDs and quarter-hour increments from navigation links.
    // This affects the visible form only; server-side price and booking checks are unchanged.
    try {
      const params = new URLSearchParams(root.location.search);
      const wanted = params.get('mira_service');
      const selected = serviceInputs.find(i => i.value === wanted);
      if (selected) selected.checked = true;
      const requested = params.get('mira_hours');
      if (requested !== null && /^(?:\d{1,2}|100)(?:\.(?:25|5|50|75))?$/.test(requested)) {
        const n = Number(requested);
        if(n >= 0.25 && n <= 100 && Number.isInteger(n * 4)) hours.value = String(n);
      }
    } catch(e) { /* no preselection on malformed URL */ }
    update();
  });
})(typeof window !== 'undefined' ? window : globalThis);
