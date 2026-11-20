'use client';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import '../install/install.css';

type Me = { displayName: string; login: string; isOwner: boolean; roles: { slug: string; name: string }[]; mfaConfigured: boolean; siteName: string };

export default function AdminHome() {
  const [me, setMe] = useState<Me | null>(null);
  useEffect(() => { api<Me>('/auth/me').then(setMe).catch(() => { window.location.href = '/admin/login'; }); }, []);
  async function logout() { await api('/auth/logout', { body: {} }).catch(() => undefined); window.location.href = '/admin/login'; }
  if (!me) return <main className="ist-main"><p className="muted">Завантаження…</p></main>;
  return (
    <main className="ist-main">
      <div className="ist-card">
        <div className="logo"><i>YE</i><span>{me.siteName}<small>Адмін-панель</small></span></div>
        <header><h2>Вітаємо, {me.displayName}</h2><p className="muted">Ви увійшли як <b>{me.login}</b> · {me.roles.map((r) => r.name).join(', ')}</p></header>
        {!me.mfaConfigured && <div className="alert warn">Двофакторна автентифікація ще не налаштована. Налаштування 2FA зʼявиться в наступному оновленні, і воно стане обовʼязковим.</div>}
        <h3>Що вже працює</h3>
        <ul className="ist-reqs">
          {['База даних і міграції', 'Ролі, права та критичні права власника', 'Журнал дій', '6 тем оформлення', 'Безпечний вхід і сесії'].map((t) => <li key={t} className="ok"><b>✓</b><span>{t}</span></li>)}
        </ul>
        <div className="ist-actions"><button className="btn" onClick={logout}>Вийти</button></div>
      </div>
    </main>
  );
}
