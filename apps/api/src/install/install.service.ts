import crypto from 'node:crypto';
import { Injectable, Logger, BadRequestException, ConflictException } from '@nestjs/common';
import { Client } from 'pg';
import { eq } from 'drizzle-orm';
import { createDb, runMigrations, syncPermissions, seedThemes, seedReference, seedJobCategories, schema as s } from '@yework/db';
import { CRITICAL_PERMISSIONS } from '@yework/permissions';
import { RuntimeConfig, type InstalledConfig } from '../config/runtime-config';
import { hashPassword, encrypt, hmac, normalizeUaPhone } from '../common/crypto';
import type { DbInput, RunInput } from './install.schemas';

export interface DbCheck { ok: boolean; serverVersion?: string; canCreate?: boolean; alreadyInstalled?: boolean; message: string; }

const pgClient = (d: DbInput) => new Client({ host: d.host, port: d.port, database: d.database, user: d.user, password: d.password, ssl: d.ssl ? { rejectUnauthorized: false } : undefined, connectionTimeoutMillis: 6000 });

/** Перекладає типові помилки PostgreSQL зрозумілою мовою. */
function explainPgError(e: any): string {
  switch (e?.code) {
    case '28P01': return 'Невірний логін або пароль бази даних.';
    case '3D000': return 'Бази даних з такою назвою не існує. Створіть її або перевірте назву.';
    case '28000': return 'Сервер БД не дозволяє вхід цьому користувачу (pg_hba.conf).';
    case 'ECONNREFUSED': return 'Сервер БД не відповідає за цією адресою і портом.';
    case 'ENOTFOUND': case 'EAI_AGAIN': return 'Не вдалося знайти сервер БД за вказаним хостом.';
  }
  if (/timeout/i.test(e?.message ?? '')) return 'Сервер БД не відповів за 6 секунд.';
  return 'Не вдалося підключитися до бази: ' + (e?.message ?? 'невідома помилка');
}

@Injectable()
export class InstallService {
  private readonly log = new Logger('Installer');
  private running = false;
  constructor(private readonly rc: RuntimeConfig) {}

  requirements() {
    const [major] = process.versions.node.split('.').map(Number);
    const w = this.rc.checkWritable();
    return [
      { key: 'node', label: 'Node.js 22 або новіший', ok: major >= 22, detail: `v${process.versions.node}` },
      { key: 'config', label: 'Папка конфігурації доступна для запису', ok: w.ok, detail: w.path },
      { key: 'https', label: 'HTTPS (Caddy або інший проксі)', ok: true, warn: true, detail: 'Перевіряється після встановлення за адресою сайту' },
    ];
  }

  async checkDb(d: DbInput): Promise<DbCheck> {
    const c = pgClient(d);
    try {
      await c.connect();
      const v = await c.query<{ v: string; n: number }>(`select current_setting('server_version') v, current_setting('server_version_num')::int n`);
      if (v.rows[0].n < 140000) return { ok: false, serverVersion: v.rows[0].v, message: `Потрібна PostgreSQL 14 або новіша, на сервері ${v.rows[0].v}.` };
      const priv = await c.query<{ ok: boolean }>(`select has_database_privilege(current_user, current_database(), 'CREATE') ok`);
      if (!priv.rows[0].ok) return { ok: false, serverVersion: v.rows[0].v, canCreate: false, message: 'Користувач не має права CREATE у цій базі. Надайте права або оберіть іншого користувача.' };
      const inst = await c.query<{ n: string }>(`select count(*) n from information_schema.tables where table_schema='identity' and table_name='users'`);
      let alreadyInstalled = false;
      if (Number(inst.rows[0].n) > 0) {
        const owners = await c.query<{ n: string }>(`select count(*) n from identity.users where is_owner`);
        alreadyInstalled = Number(owners.rows[0].n) > 0;
      }
      if (alreadyInstalled) return { ok: false, serverVersion: v.rows[0].v, canCreate: true, alreadyInstalled, message: 'У цій базі вже встановлено YEWORK. Оберіть порожню базу або відновіть сайт з резервної копії.' };
      return { ok: true, serverVersion: v.rows[0].v, canCreate: true, message: `Підключено. PostgreSQL ${v.rows[0].v}.` };
    } catch (e) {
      return { ok: false, message: explainPgError(e) };
    } finally {
      await c.end().catch(() => undefined);
    }
  }

  async run(input: RunInput, ip?: string) {
    if (this.running) throw new ConflictException({ message: 'Встановлення вже виконується.' });
    this.running = true;
    const steps: { step: string; ok: boolean }[] = [];
    const mark = (step: string) => steps.push({ step, ok: true });
    try {
      if (!this.rc.checkWritable().ok) throw new BadRequestException({ message: `Немає доступу на запис до ${this.rc.dir}.` });
      const check = await this.checkDb(input.db);
      if (!check.ok) throw new BadRequestException({ message: check.message, field: 'db' });

      let phone: string | null = null;
      if (input.admin.phone) {
        phone = normalizeUaPhone(input.admin.phone);
        if (!phone) throw new BadRequestException({ message: 'Телефон має бути українським номером, наприклад 067 123 45 67.', field: 'admin.phone' });
      }

      const appSecret = crypto.randomBytes(32).toString('base64');
      const dataKey = crypto.randomBytes(32).toString('base64');
      const d = input.db;
      const { pool, db } = createDb({ host: d.host, port: d.port, database: d.database, user: d.user, password: d.password, ssl: d.ssl ? { rejectUnauthorized: false } : undefined, max: 2 });
      try {
        await runMigrations(db); mark('Структура бази даних (міграції)');
        const passwordHash = await hashPassword(input.admin.password);
        await db.transaction(async (tx) => {
          await syncPermissions(tx as any); mark('Права та стартові ролі');
          await seedReference(tx as any); await seedJobCategories(tx as any); mark('Довідник громад і категорій');
          const email = input.admin.email || null;
          const [owner] = await tx.insert(s.users).values({
            login: input.admin.login, displayName: input.admin.displayName, passwordHash, isOwner: true,
            emailEnc: email ? encrypt(dataKey, email.toLowerCase()) : null, emailHash: email ? hmac(appSecret, email.toLowerCase()) : null,
            phoneEnc: phone ? encrypt(dataKey, phone) : null, phoneHash: phone ? hmac(appSecret, phone) : null,
            phoneVerifiedAt: phone ? new Date() : null,
          }).returning();
          const [ownerRole] = await tx.select().from(s.roles).where(eq(s.roles.slug, 'owner'));
          await tx.insert(s.userRoles).values({ userId: owner.id, roleId: ownerRole.id, grantedBy: owner.id });
          for (const code of CRITICAL_PERMISSIONS)
            await tx.insert(s.criticalGrants).values({ userId: owner.id, permissionCode: code, grantedBy: owner.id, reason: 'Власник платформи (встановлення)' });
          mark('Головний адміністратор');
          await seedThemes(tx as any, input.site.theme, owner.id); mark('6 тем оформлення');
          const settings: [string, unknown][] = [
            ['site.name', input.site.name], ['site.slogan', input.site.slogan], ['site.url', input.site.url.replace(/\/+$/, '')],
            ['site.launch_regions', ['Миронівка', 'Богуслав', 'Кагарлик']], ['security.admin_2fa_required', true],
            ['monetization.enabled', false], ['moderation.premoderation', { jobs: true, gigs: true, services: true }],
          ];
          for (const [key, value] of settings) await tx.insert(s.systemSettings).values({ key, value, updatedBy: owner.id }).onConflictDoUpdate({ target: s.systemSettings.key, set: { value } });
          mark('Налаштування сайту');
          await tx.insert(s.auditLog).values({ actorId: owner.id, action: 'install.completed', entityType: 'system', ip, data: { site: input.site.name, theme: input.site.theme, configFile: input.site.configFile, appVersion: '0.1.0' } });
        });
      } finally {
        await pool.end().catch(() => undefined);
      }
      const cfg: InstalledConfig = {
        format: 'yework-config', version: 1, installedAt: new Date().toISOString(),
        site: { name: input.site.name, slogan: input.site.slogan, url: input.site.url.replace(/\/+$/, '') },
        db: { ...input.db }, appSecret, dataKey,
      };
      this.rc.writeInstalled(input.site.configFile, cfg); mark('Файл конфігурації та блокування інсталятора');
      this.log.log(`Встановлення завершено: ${input.site.name}, адміністратор ${input.admin.login}`);
      return { ok: true, steps, configPath: `${this.rc.dir}/${input.site.configFile}` };
    } catch (e: any) {
      if (e?.status) throw e;
      if (e?.code === '23505') throw new ConflictException({ message: 'У цій базі вже є дані YEWORK. Оберіть порожню базу.' });
      this.log.error(e);
      throw new BadRequestException({ message: 'Встановлення не завершено: ' + (e?.message ?? 'невідома помилка'), steps });
    } finally {
      this.running = false;
    }
  }
}
