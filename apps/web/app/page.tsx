import { redirect } from 'next/navigation';
import './install/install.css';

export const dynamic = 'force-dynamic';

async function health(): Promise<{ installed: boolean; site?: string } | null> {
  try {
    const r = await fetch(`${process.env.API_URL ?? 'http://localhost:4000'}/api/health`, { cache: 'no-store' });
    return await r.json();
  } catch { return null; }
}

export default async function Home() {
  const h = await health();
  if (h && !h.installed) redirect('/install');
  return (
    <main className="ist-main" style={{ minHeight: '100vh', alignItems: 'center' }}>
      <div className="ist-card" style={{ textAlign: 'center', alignItems: 'center' }}>
        <div className="logo"><i>YE</i><span>{h?.site ?? 'YEWORK'}<small>Є робота!</small></span></div>
        <h2>Робота, підробіток і майстри поруч з вами</h2>
        <p className="muted">Сайт готується до запуску в Миронівці, Богуславі та Кагарлику.</p>
        {!h && <div className="alert bad">Сервер API недоступний.</div>}
      </div>
    </main>
  );
}
