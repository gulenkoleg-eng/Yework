/* YEWORK — падаючий сніг (0.5.4). Один <canvas> поверх сторінки, кліки проходять крізь нього.
   Сніжинки малюються з готових спрайтів, тому навантаження мале. Анімація стоїть, коли вкладка прихована,
   і не запускається, якщо в системі ввімкнено «зменшити рух». Відвідувач може вимкнути сніг кнопкою ❄. */
(function () {
  'use strict';
  var root = document.documentElement;
  if (!root.classList.contains('ny-has-snow')) return;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)');
  var btns = document.querySelectorAll('.ny-snow-btn');
  var KEY = 'yw-snow';
  var canvas, ctx, flakes = [], sprites = [], raf = 0, last = 0, W = 0, H = 0, dpr = 1, t0 = performance.now();

  /* Без вибору людини: сніг є, крім випадку «зменшити рух» у системі. Тоді його можна ввімкнути кнопкою ❄ (0.5.13). */
  function stored() {
    var v = null;
    try { v = localStorage.getItem(KEY); } catch (e) {}
    if (v === 'on' || v === 'off') return v === 'on';
    return !(reduce && reduce.matches);
  }
  function store(on) { try { localStorage.setItem(KEY, on ? 'on' : 'off'); } catch (e) {} }

  /* Спрайти: м’які кружечки й кілька шестипроменевих кристалів. Білий з блакитним обідком, щоб було видно і на білому фоні. */
  function sprite(size, crystal) {
    var c = document.createElement('canvas'), s = Math.ceil(size * 2 + 4);
    c.width = c.height = s * dpr;
    var g = c.getContext('2d');
    g.scale(dpr, dpr);
    g.translate(s / 2, s / 2);
    if (!crystal) {
      var r = g.createRadialGradient(0, 0, 0, 0, 0, size);
      r.addColorStop(0, 'rgba(255,255,255,1)');
      r.addColorStop(.55, 'rgba(255,255,255,.95)');
      r.addColorStop(.72, 'rgba(150,185,220,.95)');
      r.addColorStop(1, 'rgba(150,185,220,0)');
      g.fillStyle = r;
      g.beginPath(); g.arc(0, 0, size, 0, Math.PI * 2); g.fill();
    } else {
      g.lineCap = 'round';
      [['rgba(110,150,195,.75)', 2.8], ['#fff', 1.3]].forEach(function (st) {
        g.strokeStyle = st[0]; g.lineWidth = st[1];
        for (var i = 0; i < 6; i++) {
          g.save(); g.rotate(i * Math.PI / 3);
          g.beginPath();
          g.moveTo(0, 0); g.lineTo(0, -size);
          g.moveTo(0, -size * .55); g.lineTo(-size * .28, -size * .78);
          g.moveTo(0, -size * .55); g.lineTo(size * .28, -size * .78);
          g.moveTo(0, -size * .3); g.lineTo(-size * .18, -size * .44);
          g.moveTo(0, -size * .3); g.lineTo(size * .18, -size * .44);
          g.stroke(); g.restore();
        }
      });
      g.fillStyle = '#fff'; g.beginPath(); g.arc(0, 0, 1.6, 0, Math.PI * 2); g.fill();
    }
    return { img: c, s: s };
  }

  function makeSprites() {
    sprites = [];
    [1.4, 2, 2.7, 3.4, 4.2].forEach(function (r) { sprites.push(sprite(r, false)); });
    [6, 8, 10.5].forEach(function (r) { sprites.push(sprite(r, true)); });
  }

  function flake(top) {
    var crystal = Math.random() < .16;
    var k = crystal ? 5 + Math.floor(Math.random() * 3) : Math.floor(Math.random() * 5);
    var depth = crystal ? .75 + Math.random() * .25 : (k + 1) / 5;   // більші — «ближче»: падають швидше
    return {
      x: Math.random() * W, y: top ? Math.random() * H : -20 - Math.random() * 40,
      k: k, d: depth,
      vy: 18 + depth * 42 + Math.random() * 10,       // пікселів за секунду
      ph: Math.random() * Math.PI * 2, sw: .6 + Math.random() * 1.2, amp: 8 + Math.random() * 22,
      rot: Math.random() * Math.PI, vr: (Math.random() - .5) * .9,
      a: .55 + depth * .45
    };
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    W = window.innerWidth; H = window.innerHeight;
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    makeSprites();
    var want = Math.min(W < 700 ? 55 : 150, Math.round(W * H / 11000));
    while (flakes.length < want) flakes.push(flake(true));
    flakes.length = want;
  }

  function frame(now) {
    raf = requestAnimationFrame(frame);
    var dt = Math.min((now - last) / 1000, .05); last = now;
    var t = (now - t0) / 1000;
    var wind = Math.sin(t * .23) * 14 + Math.sin(t * .071) * 10;   // повільні пориви вітру
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < flakes.length; i++) {
      var f = flakes[i];
      f.y += f.vy * dt;
      f.x += (wind * f.d + Math.cos(t * f.sw + f.ph) * f.amp * .6) * dt;
      f.rot += f.vr * dt;
      if (f.y > H + 20) { flakes[i] = flake(false); continue; }
      if (f.x > W + 20) f.x -= W + 40; else if (f.x < -20) f.x += W + 40;
      var sp = sprites[f.k], h = sp.s / 2;
      ctx.globalAlpha = f.a;
      if (f.k >= 5) {
        ctx.save(); ctx.translate(f.x, f.y); ctx.rotate(f.rot);
        ctx.drawImage(sp.img, -h, -h, sp.s, sp.s); ctx.restore();
      } else {
        ctx.drawImage(sp.img, f.x - h, f.y - h, sp.s, sp.s);
      }
    }
    ctx.globalAlpha = 1;
  }

  function start() {
    if (raf) return;
    if (!canvas) {
      canvas = document.createElement('canvas');
      canvas.className = 'ny-canvas';
      canvas.setAttribute('aria-hidden', 'true');
      document.body.appendChild(canvas);
      ctx = canvas.getContext('2d');
      resize();
    }
    canvas.style.display = '';
    last = performance.now();
    raf = requestAnimationFrame(frame);
  }

  function stop(hide) {
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    if (hide && canvas) { ctx.clearRect(0, 0, W, H); canvas.style.display = 'none'; }
  }

  var on = stored();
  function paint() {
    for (var i = 0; i < btns.length; i++) {
      var b = btns[i];
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.title = on ? 'Вимкнути сніг' : 'Увімкнути сніг';
      if (b.classList.contains('ny-snow-nav')) b.textContent = on ? '❄ Сніг: вимкнути' : '❄ Сніг: увімкнути';
    }
  }
  for (var i = 0; i < btns.length; i++) btns[i].addEventListener('click', function () {
    on = !on; store(on); paint();
    on ? start() : stop(true);
  });
  paint();

  var rt = 0;
  window.addEventListener('resize', function () {
    if (!canvas) return;
    clearTimeout(rt); rt = setTimeout(resize, 150);
  });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop(false); else if (on) start();
  });
  if (reduce && reduce.addEventListener) reduce.addEventListener('change', function () { on = stored(); paint(); on ? start() : stop(true); });

  if (on) start();
})();
