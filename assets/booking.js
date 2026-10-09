document.querySelectorAll('.wpb-booking').forEach((root) => {
  const config = JSON.parse(root.dataset.config);
  const date = root.querySelector('.wpb-date');
  const start = root.querySelector('.wpb-start');
  const duration = root.querySelector('.wpb-duration');
  const summary = root.querySelector('.wpb-summary');
  const minutes = (time) => { const [h, m] = time.split(':').map(Number); return h * 60 + m; };
  const display = (m) => { const h = Math.floor(m / 60); return ((h + 11) % 12 + 1) + ':' + String(m % 60).padStart(2, '0') + (h >= 12 ? ' PM' : ' AM'); };
  const option = (value, text) => { const o = document.createElement('option'); o.value = String(value); o.textContent = text; return o; };
  const money = (n) => new Intl.NumberFormat(config.locale, {style:'currency', currency:config.currency}).format(n);
  function update() {
    const selected = date.value ? new Date(date.value + 'T12:00:00') : null;
    const weekday = selected ? (selected.getDay() || 7) : 0;
    const allowed = config.days.includes(weekday);
    const opening = minutes(config.open), closing = minutes(config.close);
    const previousStart = start.value, previousDuration = duration.value;
    start.replaceChildren(); duration.replaceChildren();
    if (!allowed || closing <= opening) {
      summary.textContent = 'This machine is closed on the selected day.';
      start.disabled = true; duration.disabled = true; return;
    }
    start.disabled = false; duration.disabled = false;
    for (let m = opening; m + config.step <= closing; m += config.step) start.add(option(m, display(m)));
    if (previousStart && [...start.options].some(o => o.value === previousStart)) start.value = previousStart;
    for (let n = config.step; n <= closing - Number(start.value); n += config.step) duration.add(option(n, (n / 60) + ' hour(s)'));
    if (previousDuration && [...duration.options].some(o => o.value === previousDuration)) duration.value = previousDuration;
    summary.textContent = display(Number(start.value)) + ' – ' + display(Number(start.value) + Number(duration.value)) + ' · ' + money(config.rate * Number(duration.value) / 60);
  }
  date.addEventListener('change', update);
  start.addEventListener('change', update);
  duration.addEventListener('change', update);
  update();
});
