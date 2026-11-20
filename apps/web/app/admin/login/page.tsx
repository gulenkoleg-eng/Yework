'use client';
import { useState } from 'react';
import { api, ApiError } from '@/lib/api';
import '../../install/install.css';

export default function LoginPage() {
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); setErr(null);
    try { await api('/auth/login', { body: { login, password } }); window.location.href = '/admin'; }
    catch (e) { setErr(e instanceof ApiError ? e.message : 'Помилка входу'); setBusy(false); }
  }
  return (
    <main className="ist-main" style={{ minHeight: '100vh', alignItems: 'center' }}>
      <form className="ist-card" style={{ width: 'min(420px,100%)' }} onSubmit={submit}>
        <div className="logo"><i>YE</i><span>YEWORK<small>Є робота!</small></span></div>
        <header><h2>Вхід в адмін-панель</h2></header>
        {err && <div className="alert bad" role="alert">{err}</div>}
        <div className="field"><label htmlFor="l">Логін</label><input id="l" className="input" value={login} onChange={(e) => setLogin(e.target.value)} autoComplete="username" required /></div>
        <div className="field"><label htmlFor="p">Пароль</label><input id="p" className="input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required /></div>
        <button className="btn pri" disabled={busy}>{busy ? 'Входжу…' : 'Увійти'}</button>
      </form>
    </main>
  );
}
