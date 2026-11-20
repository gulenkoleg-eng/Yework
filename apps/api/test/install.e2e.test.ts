/**
 * Повне встановлення на чистій PostgreSQL.
 * Потрібна тестова база: TEST_PG_HOST/PORT/USER/PASSWORD, користувач із правом CREATEDB.
 * Кожен запуск створює окрему базу й видаляє її в кінці.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { Client } from 'pg';
import request from 'supertest';
import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import type { INestApplication } from '@nestjs/common';

const PG = { host: process.env.TEST_PG_HOST ?? '127.0.0.1', port: Number(process.env.TEST_PG_PORT ?? 5544), user: process.env.TEST_PG_USER ?? 'yework', password: process.env.TEST_PG_PASSWORD ?? 'Secret123' };
const DBNAME = 'yw_e2e_' + Date.now();
const CSRF = { 'x-yework-csrf': '1' };

let app: INestApplication; let http: any; let dir: string; let key: string;
const admin = { login: 'oleh', displayName: 'Олег', email: 'owner@example.com', phone: '067 123 45 67', password: 'Миронівка2026Yes' };
const site = { name: 'YEWORK', slogan: 'Є робота!', url: 'https://yework.ua', configFile: 'yework-prod', theme: 'emerald' };
const db = { host: PG.host, port: PG.port, database: DBNAME, user: PG.user, password: PG.password, ssl: false };

async function sql(q: string, database = DBNAME) { const c = new Client({ ...PG, database }); await c.connect(); try { return (await c.query(q)).rows; } finally { await c.end(); } }

beforeAll(async () => {
  await sql(`create database ${DBNAME}`, 'postgres');
  dir = fs.mkdtempSync(path.join(os.tmpdir(), 'yw-config-'));
  process.env.YEWORK_CONFIG_DIR = dir;
  const { createApp } = await import('../src/app');
  app = await createApp(); await app.init(); http = app.getHttpServer();
  key = fs.readFileSync(path.join(dir, 'setup-key.txt'), 'utf8').trim();
});
afterAll(async () => { await app?.close(); await sql(`drop database if exists ${DBNAME} with (force)`, 'postgres'); });

describe('інсталятор', () => {
  it('до встановлення статус installed=false і є ключ', async () => {
    const r = await request(http).get('/api/install/status');
    expect(r.body).toMatchObject({ installed: false });
    expect(key).toMatch(/^[A-Z0-9]{4}(-[A-Z0-9]{1,4})+$/);
  });
  it('без CSRF-заголовка POST відхиляється', async () => {
    const r = await request(http).post('/api/install/verify-key').set('x-setup-key', key);
    expect(r.status).toBe(403);
  });
  it('неправильний ключ → 403, правильний → вимоги сервера', async () => {
    expect((await request(http).post('/api/install/verify-key').set(CSRF).set('x-setup-key', 'WRONG-KEY')).status).toBe(403);
    const r = await request(http).post('/api/install/verify-key').set(CSRF).set('x-setup-key', key.toLowerCase());
    expect(r.status).toBe(200);
    expect(r.body.requirements.find((x: any) => x.key === 'config').ok).toBe(true);
  });
  it('перевірка БД пояснює неправильний пароль і неіснуючу базу', async () => {
    const bad = await request(http).post('/api/install/check-db').set(CSRF).set('x-setup-key', key).send({ ...db, password: 'nope' });
    expect(bad.body).toMatchObject({ ok: false, message: 'Невірний логін або пароль бази даних.' });
    const none = await request(http).post('/api/install/check-db').set(CSRF).set('x-setup-key', key).send({ ...db, database: 'no_such_db_x' });
    expect(none.body.ok).toBe(false);
    expect(none.body.message).toMatch(/не існує/);
    const good = await request(http).post('/api/install/check-db').set(CSRF).set('x-setup-key', key).send(db);
    expect(good.body).toMatchObject({ ok: true });
  });
  it('слабкий пароль адміністратора і небезпечне ім’я файлу відхиляються', async () => {
    const r1 = await request(http).post('/api/install/run').set(CSRF).set('x-setup-key', key).send({ db, site, admin: { ...admin, password: 'short' } });
    expect(r1.status).toBe(400);
    const r2 = await request(http).post('/api/install/run').set(CSRF).set('x-setup-key', key).send({ db, site: { ...site, configFile: '../../etc/passwd' }, admin });
    expect(r2.status).toBe(400);
    expect(fs.existsSync(path.join(dir, 'install.lock'))).toBe(false);
  });
  it('повне встановлення', async () => {
    const r = await request(http).post('/api/install/run').set(CSRF).set('x-setup-key', key).send({ db, site, admin });
    expect(r.status).toBe(200);
    expect(r.body.ok).toBe(true);
    expect(r.body.steps.length).toBeGreaterThanOrEqual(7);
  });
  it('після встановлення: конфіг 600, lock, ключ видалено, інсталятор вимкнено', async () => {
    const cfgPath = path.join(dir, 'yework-prod.json');
    expect(fs.statSync(cfgPath).mode & 0o777).toBe(0o600);
    const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8'));
    expect(cfg.db.database).toBe(DBNAME);
    expect(Buffer.from(cfg.dataKey, 'base64')).toHaveLength(32);
    expect(fs.existsSync(path.join(dir, 'setup-key.txt'))).toBe(false);
    expect((await request(http).get('/api/install/status')).body.installed).toBe(true);
    const again = await request(http).post('/api/install/run').set(CSRF).set('x-setup-key', key).send({ db, site, admin });
    expect(again.status).toBe(410);
  });
  it('у БД: власник з argon2id, зашифрований телефон, ролі, критичні права, тема, журнал', async () => {
    const [u] = await sql(`select * from identity.users`);
    expect(u.login).toBe('oleh'); expect(u.is_owner).toBe(true);
    expect(u.password_hash).toMatch(/^\$argon2id\$/);
    expect(u.phone_enc).toMatch(/^v1\./); expect(u.phone_enc).not.toContain('067');
    expect((await sql(`select count(*)::int n from access.roles where is_system`))[0].n).toBe(9);
    expect((await sql(`select count(*)::int n from access.critical_grants`))[0].n).toBeGreaterThanOrEqual(9);
    expect((await sql(`select count(*)::int n from theming.themes`))[0].n).toBe(6);
    const [active] = await sql(`select t.slug from theming.theme_publications p join theming.theme_versions v on v.id=p.theme_version_id join theming.themes t on t.id=v.theme_id where p.is_active`);
    expect(active.slug).toBe('emerald');
    expect((await sql(`select count(*)::int n from reference.settlements`))[0].n).toBe(3);
    expect((await sql(`select action from audit.audit_log`)).map((x) => x.action)).toContain('install.completed');
  });
  it('журнал дій не можна змінити', async () => {
    await expect(sql(`delete from audit.audit_log`)).rejects.toThrow(/append-only/);
  });
  it('другого власника створити неможливо', async () => {
    await expect(sql(`insert into identity.users(login, display_name, password_hash, is_owner) values ('x','x','x', true)`)).rejects.toThrow(/users_single_owner_uq/);
  });
});

describe('вхід адміністратора', () => {
  let cookie: string;
  it('невірний пароль → 401', async () => {
    const r = await request(http).post('/api/auth/login').set(CSRF).send({ login: 'oleh', password: 'wrong' });
    expect(r.status).toBe(401);
  });
  it('вхід (логін без урахування регістру) і /me', async () => {
    const r = await request(http).post('/api/auth/login').set(CSRF).send({ login: 'OLEH', password: admin.password });
    expect(r.status).toBe(200);
    cookie = r.headers['set-cookie'][0];
    expect(cookie).toMatch(/HttpOnly/); expect(cookie).toMatch(/Secure/); expect(cookie).toMatch(/SameSite=Lax/);
    const me = await request(http).get('/api/auth/me').set('cookie', cookie);
    expect(me.body).toMatchObject({ login: 'oleh', isOwner: true, mfaRequired: true, mfaConfigured: false, siteName: 'YEWORK' });
    expect(me.body.roles.map((x: any) => x.slug)).toEqual(['owner']);
  });
  it('вихід відкликає сесію', async () => {
    await request(http).post('/api/auth/logout').set(CSRF).set('cookie', cookie);
    expect((await request(http).get('/api/auth/me').set('cookie', cookie)).status).toBe(401);
  });
  it('health показує підключену БД', async () => {
    expect((await request(http).get('/api/health')).body).toMatchObject({ status: 'ok', installed: true, db: 'ok' });
  });
});
