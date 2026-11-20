import { Injectable, Logger } from '@nestjs/common';
import { eq } from 'drizzle-orm';
import nodemailer from 'nodemailer';
import { schema as s } from '@yework/db';
import { RuntimeConfig } from '../config/runtime-config';
import { encrypt, decrypt } from '../common/crypto';

/**
 * Адаптер вихідних повідомлень.
 * SMS: провайдера ще не обрано, тому за замовчуванням драйвер «log» — повідомлення пишеться в журнал сервера
 *      (зручно для перевірки). Новий провайдер = новий клас SmsDriver, без змін у решті коду.
 * Email: «log» або «smtp» (налаштування в settings.system_settings → messaging.email).
 */
export type Channel = 'sms' | 'email';
export interface Message { channel: Channel; to: string; subject?: string; text: string; template: string; }
interface Driver { name: string; send(m: Message): Promise<void>; }

export interface SmtpSettings { driver: 'smtp'; host: string; port: number; secure: boolean; user: string; passEnc?: string; from: string; }
export type EmailSettings = { driver: 'log' } | SmtpSettings;
export type SmsSettings = { driver: 'log' };

@Injectable()
export class MessagingService {
  private readonly log = new Logger('Messaging');
  constructor(private readonly rc: RuntimeConfig) {}

  private async setting<T>(key: string, def: T): Promise<T> {
    const [r] = await this.rc.db().select().from(s.systemSettings).where(eq(s.systemSettings.key, key));
    return (r?.value as T) ?? def;
  }

  private logDriver(): Driver {
    return { name: 'log', send: async (m) => { this.log.warn(`[${m.channel.toUpperCase()} → ${m.to}] ${m.subject ? m.subject + ': ' : ''}${m.text}`); } };
  }

  private async driver(channel: Channel): Promise<Driver> {
    if (channel === 'sms') {
      await this.setting<SmsSettings>('messaging.sms', { driver: 'log' });
      return this.logDriver();
    }
    const cfg = await this.setting<EmailSettings>('messaging.email', { driver: 'log' });
    if (cfg.driver !== 'smtp') return this.logDriver();
    const pass = cfg.passEnc ? decrypt(this.rc.config().dataKey, cfg.passEnc) : undefined;
    const tr = nodemailer.createTransport({ host: cfg.host, port: cfg.port, secure: cfg.secure, auth: cfg.user ? { user: cfg.user, pass } : undefined });
    return { name: 'smtp', send: async (m) => { await tr.sendMail({ from: cfg.from, to: m.to, subject: m.subject ?? 'YEWORK', text: m.text }); } };
  }

  /** Записує повідомлення в чергу (зашифровано) і одразу намагається надіслати. */
  async send(m: Message): Promise<{ ok: boolean; driver: string }> {
    const db = this.rc.db();
    const key = this.rc.config().dataKey;
    const d = await this.driver(m.channel);
    const [row] = await db.insert(s.outbox).values({ channel: m.channel, driver: d.name, toEnc: encrypt(key, m.to), subject: m.subject, bodyEnc: encrypt(key, m.text), template: m.template }).returning({ id: s.outbox.id });
    try {
      await d.send(m);
      await db.update(s.outbox).set({ status: 'sent', attempts: 1, sentAt: new Date() }).where(eq(s.outbox.id, row.id));
      return { ok: true, driver: d.name };
    } catch (e) {
      const msg = e instanceof Error ? e.message : String(e);
      this.log.error(`Не вдалося надіслати ${m.channel}: ${msg}`);
      await db.update(s.outbox).set({ status: 'failed', attempts: 1, error: msg.slice(0, 500) }).where(eq(s.outbox.id, row.id));
      return { ok: false, driver: d.name };
    }
  }

  async channelsInfo() {
    const email = await this.setting<EmailSettings>('messaging.email', { driver: 'log' });
    return { sms: 'log', email: email.driver };
  }

  async saveEmailSettings(v: Omit<SmtpSettings, 'passEnc'> & { pass?: string } | { driver: 'log' }, actorId: string) {
    const db = this.rc.db();
    let value: EmailSettings;
    if (v.driver === 'smtp') {
      const prev = await this.setting<EmailSettings>('messaging.email', { driver: 'log' });
      const passEnc = v.pass ? encrypt(this.rc.config().dataKey, v.pass) : prev.driver === 'smtp' ? prev.passEnc : undefined;
      value = { driver: 'smtp', host: v.host, port: v.port, secure: v.secure, user: v.user, from: v.from, passEnc };
    } else value = { driver: 'log' };
    await db.insert(s.systemSettings).values({ key: 'messaging.email', value, updatedBy: actorId })
      .onConflictDoUpdate({ target: s.systemSettings.key, set: { value, updatedBy: actorId, updatedAt: new Date() } });
  }
}
