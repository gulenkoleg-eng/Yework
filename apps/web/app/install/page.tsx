'use client';
import { useEffect, useMemo, useState } from 'react';
import { api, ApiError } from '@/lib/api';
import './install.css';

type Req = { key: string; label: string; ok: boolean; warn?: boolean; detail?: string };
type DbForm = { host: string; port: number; database: string; user: string; password: string; ssl: boolean };
type SiteForm = { name: string; slogan: string; url: string; configFile: string; theme: string };
type AdminForm = { login: string; displayName: string; email: string; phone: string; password: string; confirm: string };

const STEPS = ['Ключ встановлення', 'База даних', 'Сайт', 'Тема оформлення', 'Головний адміністратор', 'Встановлення'];
const THEMES = [
  { id: 'modern-light', name: 'Modern Light', d: 'Світла, мінімалізм', c: ['#F7F8FA', '#FFFFFF', '#3D5AFE', '#0F1115'] },
  { id: 'modern-dark', name: 'Modern Dark', d: 'Темна, технологічна', c: ['#0A0D12', '#131821', '#3462EE', '#FFFFFF'] },
  { id: 'corporate-blue', name: 'Corporate Blue', d: 'Синьо-біла, ділова', c: ['#F2F5F9', '#FFFFFF', '#0B4F9C', '#0B2A4F'] },
  { id: 'emerald', name: 'Emerald', d: 'Білий фон, зелені акценти', c: ['#FFFFFF', '#F6FBF9', '#047857', '#0B1F17'] },
  { id: 'premium', name: 'Premium', d: 'Стримана, виразна типографіка', c: ['#FAF8F5', '#FFFFFF', '#1E1B18', '#B08D57'] },
  { id: 'soft-ui', name: 'Soft UI', d: 'Мʼякі кольори, округлені картки', c: ['#F2F3F9', '#FFFFFF', '#6C5CE7', '#23243A'] },
];
const MIN = 12;
const pwRules = (p: string) => [
  { ok: p.length >= MIN, t: `Щонайменше ${MIN} символів` },
  { ok: /[a-zа-яіїєґ]/.test(p) && /[A-ZА-ЯІЇЄҐ]/.test(p), t: 'Великі й малі літери' },
  { ok: /\d/.test(p), t: 'Хоча б одна цифра' },
];

export default function InstallPage() {
  const [installed, setInstalled] = useState<boolean | null>(null);
  const [step, setStep] = useState(0);
  const [key, setKey] = useState('');
  const [reqs, setReqs] = useState<Req[] | null>(null);
  const [db, setDb] = useState<DbForm>({ host: 'postgres', port: 5432, database: 'yework', user: 'yework', password: '', ssl: false });
  const [dbCheck, setDbCheck] = useState<{ ok: boolean; message: string } | null>(null);
  const [site, setSite] = useState<SiteForm>({ name: 'YEWORK', slogan: 'Є робота!', url: '', configFile: 'yework.json', theme: 'modern-light' });
  const [admin, setAdmin] = useState<AdminForm>({ login: '', displayName: '', email: '', phone: '', password: '', confirm: '' });
  const [showPw, setShowPw] = useState(false);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [result, setResult] = useState<{ steps: { step: string }[]; configPath: string } | null>(null);

  useEffect(() => {
    api('/install/status').then((r) => setInstalled(r.installed)).catch(() => setInstalled(false));
    setSite((s) => ({ ...s, url: s.url || window.location.origin }));
  }, []);

  const go = (n: number) => { setErr(null); setStep(n); window.scrollTo({ top: 0, behavior: 'smooth' }); };
  const fail = (e: unknown) => setErr(e instanceof ApiError ? e.message : 'Невідома помилка');

  async function verifyKey() {
    setBusy(true); setErr(null);
    try { const r = await api('/install/verify-key', { body: {}, setupKey: key.trim() }); setReqs(r.requirements); }
    catch (e) { fail(e); } finally { setBusy(false); }
  }
  async function checkDb() {
    setBusy(true); setErr(null); setDbCheck(null);
    try { setDbCheck(await api('/install/check-db', { body: db, setupKey: key.trim() })); }
    catch (e) { fail(e); } finally { setBusy(false); }
  }
  async function install() {
    setBusy(true); setErr(null);
    const { confirm, ...a } = admin;
    try { const r = await api('/install/run', { body: { db, site, admin: a }, setupKey: key.trim() }); setResult(r); }
    catch (e) { fail(e); } finally { setBusy(false); }
  }

  const rules = pwRules(admin.password);
  const adminOk = /^[a-zA-Z0-9._-]{3,32}$/.test(admin.login) && admin.displayName.trim().length >= 2 && rules.every((r) => r.ok) && admin.password === admin.confirm;
  const siteOk = site.name.trim().length >= 2 && /^https?:\/\/.+/.test(site.url) && /^[a-z0-9][a-z0-9._-]{0,58}(\.json)?$/.test(site.configFile.toLowerCase());
  const reqsOk = reqs?.every((r) => r.ok) ?? false;
  const theme = useMemo(() => THEMES.find((t) => t.id === site.theme)!, [site.theme]);

  if (installed) return (
    <Shell step={-1}><div className="ist-card"><h2>YEWORK уже встановлено</h2><p className="muted">Інсталятор вимкнено з міркувань безпеки.</p><a className="btn pri" href="/admin/login">Увійти в адмін-панель</a></div></Shell>
  );

  return (
    <Shell step={result ? STEPS.length : step}>
      <div className="ist-card">
        {err && <div className="alert bad" role="alert">{err}</div>}

        {step === 0 && <>
          <header><span className="ist-n">Крок 1 із 6</span><h2>Ключ встановлення</h2>
            <p className="muted">Ключ захищає інсталятор від сторонніх, поки сайт не налаштовано. Його видно в журналі сервера або у файлі на сервері.</p></header>
          <div className="alert info"><div>Команда для перегляду ключа:<br /><code className="mono">docker compose logs api | grep -A1 "Ключ"</code><br />або файл <code className="mono">config/setup-key.txt</code></div></div>
          <div className="field"><label htmlFor="k">Ключ</label>
            <input id="k" className="input mono" value={key} onChange={(e) => setKey(e.target.value.toUpperCase())} placeholder="XXXX-XXXX-XXXX" autoComplete="off" spellCheck={false} />
          </div>
          {!reqs && <div className="ist-actions"><button className="btn pri" disabled={busy || key.trim().length < 8} onClick={verifyKey}>{busy ? 'Перевіряю…' : 'Перевірити ключ'}</button></div>}
          {reqs && <>
            <h3>Перевірка сервера</h3>
            <ul className="ist-reqs">{reqs.map((r) => <li key={r.key} className={r.ok ? (r.warn ? 'warn' : 'ok') : 'bad'}><b>{r.ok ? (r.warn ? '!' : '✓') : '✕'}</b><span>{r.label}<small>{r.detail}</small></span></li>)}</ul>
            <div className="ist-actions"><button className="btn pri" disabled={!reqsOk} onClick={() => go(1)}>Далі: база даних</button></div>
          </>}
        </>}

        {step === 1 && <>
          <header><span className="ist-n">Крок 2 із 6</span><h2>Підключення до бази даних</h2>
            <p className="muted">PostgreSQL 14 або новіша. База має бути порожньою, користувач — з правом створювати таблиці.</p></header>
          <div className="grid3">
            <F id="h" label="Хост" hint="У Docker: postgres. На сервері без Docker: localhost"><input id="h" className="input" value={db.host} onChange={(e) => { setDb({ ...db, host: e.target.value }); setDbCheck(null); }} /></F>
            <F id="p" label="Порт"><input id="p" className="input" inputMode="numeric" value={db.port} onChange={(e) => { setDb({ ...db, port: Number(e.target.value.replace(/\D/g, '')) || 0 }); setDbCheck(null); }} /></F>
          </div>
          <F id="d" label="Назва бази даних"><input id="d" className="input" value={db.database} onChange={(e) => { setDb({ ...db, database: e.target.value }); setDbCheck(null); }} autoComplete="off" /></F>
          <div className="grid2">
            <F id="u" label="Користувач (логін)"><input id="u" className="input" value={db.user} onChange={(e) => { setDb({ ...db, user: e.target.value }); setDbCheck(null); }} autoComplete="off" /></F>
            <F id="pw" label="Пароль"><input id="pw" className="input" type="password" value={db.password} onChange={(e) => { setDb({ ...db, password: e.target.value }); setDbCheck(null); }} autoComplete="new-password" /></F>
          </div>
          <label className="check"><input type="checkbox" checked={db.ssl} onChange={(e) => { setDb({ ...db, ssl: e.target.checked }); setDbCheck(null); }} /> Підключатися через SSL (для віддаленого сервера БД)</label>
          {dbCheck && <div className={'alert ' + (dbCheck.ok ? 'ok' : 'bad')} role="status">{dbCheck.ok ? '✓ ' : ''}{dbCheck.message}</div>}
          <div className="ist-actions">
            <button className="btn" onClick={() => go(0)}>Назад</button>
            <button className="btn" disabled={busy || !db.password} onClick={checkDb}>{busy ? 'Підключаюся…' : 'Перевірити підключення'}</button>
            <button className="btn pri" disabled={!dbCheck?.ok} onClick={() => go(2)}>Далі</button>
          </div>
        </>}

        {step === 2 && <>
          <header><span className="ist-n">Крок 3 із 6</span><h2>Сайт</h2><p className="muted">Назву й слоган можна буде змінити в адмін-панелі.</p></header>
          <div className="grid2">
            <F id="sn" label="Назва сайту"><input id="sn" className="input" value={site.name} onChange={(e) => setSite({ ...site, name: e.target.value })} /></F>
            <F id="ss" label="Слоган"><input id="ss" className="input" value={site.slogan} onChange={(e) => setSite({ ...site, slogan: e.target.value })} /></F>
          </div>
          <F id="su" label="Адреса сайту" hint="Повна адреса з https://. Від неї залежать посилання в листах і захищені cookie."><input id="su" className="input" value={site.url} onChange={(e) => setSite({ ...site, url: e.target.value })} placeholder="https://yework.ua" /></F>
          <F id="sf" label="Назва файлу конфігурації" hint="Файл створюється в папці config/ з доступом лише для сервера (600). У ньому пароль БД і ключі шифрування: збережіть копію в безпечному місці, без неї зашифровані телефони й email не відновити.">
            <input id="sf" className="input mono" value={site.configFile} onChange={(e) => setSite({ ...site, configFile: e.target.value.toLowerCase() })} spellCheck={false} /></F>
          <div className="ist-actions"><button className="btn" onClick={() => go(1)}>Назад</button><button className="btn pri" disabled={!siteOk} onClick={() => go(3)}>Далі</button></div>
        </>}

        {step === 3 && <>
          <header><span className="ist-n">Крок 4 із 6</span><h2>Тема оформлення</h2><p className="muted">Усі 6 тем буде встановлено. Обрана стане активною; змінити її можна будь-коли в розділі «Дизайн → Теми оформлення».</p></header>
          <div className="ist-themes" role="radiogroup" aria-label="Тема">
            {THEMES.map((t) => (
              <button key={t.id} role="radio" aria-checked={site.theme === t.id} className={'ist-theme' + (site.theme === t.id ? ' on' : '')} onClick={() => setSite({ ...site, theme: t.id })}>
                <span className="sw" style={{ background: t.c[0] }}><i style={{ background: t.c[1], borderColor: t.c[0] === '#FFFFFF' ? '#E3E6EB' : 'transparent' }}><em style={{ background: t.c[3] }} /><b style={{ background: t.c[2] }} /></i></span>
                <strong>{t.name}</strong><small>{t.d}</small>
              </button>
            ))}
          </div>
          <div className="ist-actions"><button className="btn" onClick={() => go(2)}>Назад</button><button className="btn pri" onClick={() => go(4)}>Далі</button></div>
        </>}

        {step === 4 && <>
          <header><span className="ist-n">Крок 5 із 6</span><h2>Головний адміністратор</h2><p className="muted">Власник платформи з усіма правами. Такий акаунт може бути лише один.</p></header>
          <div className="grid2">
            <F id="al" label="Логін" hint="Латиниця, цифри, . _ - (3–32)"><input id="al" className="input" value={admin.login} onChange={(e) => setAdmin({ ...admin, login: e.target.value.trim() })} autoComplete="username" /></F>
            <F id="an" label="Імʼя"><input id="an" className="input" value={admin.displayName} onChange={(e) => setAdmin({ ...admin, displayName: e.target.value })} autoComplete="name" /></F>
          </div>
          <div className="grid2">
            <F id="ae" label="Email (необовʼязково)" hint="Для відновлення доступу"><input id="ae" className="input" type="email" value={admin.email} onChange={(e) => setAdmin({ ...admin, email: e.target.value })} autoComplete="email" /></F>
            <F id="ap" label="Телефон (необовʼязково)"><input id="ap" className="input" inputMode="tel" value={admin.phone} onChange={(e) => setAdmin({ ...admin, phone: e.target.value })} placeholder="067 123 45 67" autoComplete="tel" /></F>
          </div>
          <div className="grid2">
            <F id="aw" label="Пароль"><input id="aw" className="input" type={showPw ? 'text' : 'password'} value={admin.password} onChange={(e) => setAdmin({ ...admin, password: e.target.value })} autoComplete="new-password" /></F>
            <F id="ac" label="Повторіть пароль"><input id="ac" className={'input' + (admin.confirm && admin.confirm !== admin.password ? ' err' : '')} type={showPw ? 'text' : 'password'} value={admin.confirm} onChange={(e) => setAdmin({ ...admin, confirm: e.target.value })} autoComplete="new-password" /></F>
          </div>
          <ul className="ist-pw">
            {rules.map((r) => <li key={r.t} className={r.ok ? 'ok' : ''}>{r.ok ? '✓' : '○'} {r.t}</li>)}
            <li className={admin.confirm && admin.confirm === admin.password ? 'ok' : ''}>{admin.confirm && admin.confirm === admin.password ? '✓' : '○'} Паролі збігаються</li>
          </ul>
          <label className="check"><input type="checkbox" checked={showPw} onChange={(e) => setShowPw(e.target.checked)} /> Показати пароль</label>
          <div className="alert warn">Після першого входу система попросить увімкнути двофакторну автентифікацію. Без неї адмін-панель для адміністраторів буде недоступна.</div>
          <div className="ist-actions"><button className="btn" onClick={() => go(3)}>Назад</button><button className="btn pri" disabled={!adminOk} onClick={() => go(5)}>Далі</button></div>
        </>}

        {step === 5 && !result && <>
          <header><span className="ist-n">Крок 6 із 6</span><h2>Усе готово до встановлення</h2><p className="muted">Перевірте дані. Встановлення займе кілька секунд.</p></header>
          <dl className="ist-sum">
            <dt>База даних</dt><dd className="mono">{db.user}@{db.host}:{db.port}/{db.database}{db.ssl ? ' (SSL)' : ''}</dd>
            <dt>Сайт</dt><dd>{site.name} · {site.slogan}<br /><span className="mono">{site.url}</span></dd>
            <dt>Файл конфігурації</dt><dd className="mono">config/{site.configFile.endsWith('.json') ? site.configFile : site.configFile + '.json'}</dd>
            <dt>Тема</dt><dd>{theme.name}</dd>
            <dt>Адміністратор</dt><dd>{admin.displayName} · <span className="mono">{admin.login}</span></dd>
          </dl>
          <div className="ist-actions"><button className="btn" disabled={busy} onClick={() => go(4)}>Назад</button><button className="btn pri" disabled={busy} onClick={install}>{busy ? 'Встановлюю…' : `Встановити ${site.name}`}</button></div>
        </>}

        {result && <>
          <header><span className="ist-n ok">Встановлено</span><h2>{site.name} готовий до роботи</h2><p className="muted">Інсталятор вимкнено. Ключ встановлення видалено.</p></header>
          <ul className="ist-reqs">{result.steps.map((s) => <li key={s.step} className="ok"><b>✓</b><span>{s.step}</span></li>)}</ul>
          <div className="alert info"><div>Збережіть копію файлу <code className="mono">{result.configPath}</code> поза сервером. Він потрібен для відновлення з резервної копії.</div></div>
          <div className="ist-actions"><a className="btn pri" href="/admin/login">Увійти в адмін-панель</a></div>
        </>}
      </div>
    </Shell>
  );
}

function F({ id, label, hint, children }: { id: string; label: string; hint?: string; children: React.ReactNode }) {
  return <div className="field"><label htmlFor={id}>{label}</label>{children}{hint && <span className="hint">{hint}</span>}</div>;
}

function Shell({ step, children }: { step: number; children: React.ReactNode }) {
  return (
    <main className="ist">
      <aside className="ist-side">
        <div className="logo"><i>YE</i><span>YEWORK<small>Є робота!</small></span></div>
        <div className="ist-side-t">Встановлення платформи</div>
        {step >= 0 && <ol className="ist-steps">{STEPS.map((s, i) => <li key={s} className={i < step ? 'done' : i === step ? 'cur' : ''}><b>{i < step ? '✓' : i + 1}</b>{s}</li>)}</ol>}
        <p className="ist-side-f">Версія 0.1.0 · Етап 2</p>
      </aside>
      <section className="ist-main">{children}</section>
    </main>
  );
}
