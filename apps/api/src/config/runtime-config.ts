import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { Injectable, Logger, OnModuleDestroy } from '@nestjs/common';
import { createDb, type YeworkDb } from '@yework/db';
import type { Pool } from 'pg';

export interface DbParams { host: string; port: number; database: string; user: string; password: string; ssl: boolean; }
export interface InstalledConfig {
  format: 'yework-config'; version: 1;
  installedAt: string;
  site: { name: string; slogan: string; url: string };
  db: DbParams;
  /** Ключ підпису сесій та HMAC. */ appSecret: string;
  /** Ключ шифрування персональних даних (AES-256). Втрата ключа = втрата телефонів/email. */ dataKey: string;
}
interface Lock { configFile: string; installedAt: string; }

/** Дозволене ім'я файлу конфігурації: латиниця, цифри, крапка, дефіс, підкреслення; закінчується на .json. */
export const CONFIG_FILE_RE = /^[a-z0-9][a-z0-9._-]{0,58}\.json$/;

/**
 * Стан встановлення та підключення до БД.
 * До встановлення: config/setup-key.txt (одноразовий ключ інсталятора).
 * Після: config/<ваш-файл>.json (налаштування, секрети) + config/install.lock (вказує, який файл читати).
 */
@Injectable()
export class RuntimeConfig implements OnModuleDestroy {
  private readonly log = new Logger('Config');
  readonly dir = path.resolve(process.env.YEWORK_CONFIG_DIR || path.join(process.cwd(), 'config'));
  private cfg: InstalledConfig | null = null;
  private conn: { pool: Pool; db: YeworkDb } | null = null;

  get lockPath() { return path.join(this.dir, 'install.lock'); }
  get setupKeyPath() { return path.join(this.dir, 'setup-key.txt'); }

  isInstalled(): boolean { return fs.existsSync(this.lockPath); }

  config(): InstalledConfig {
    if (this.cfg) return this.cfg;
    const lock: Lock = JSON.parse(fs.readFileSync(this.lockPath, 'utf8'));
    if (!CONFIG_FILE_RE.test(lock.configFile)) throw new Error('install.lock пошкоджено');
    this.cfg = JSON.parse(fs.readFileSync(path.join(this.dir, lock.configFile), 'utf8'));
    return this.cfg!;
  }

  db(): YeworkDb {
    if (!this.conn) {
      const d = this.config().db;
      this.conn = createDb({ host: d.host, port: d.port, database: d.database, user: d.user, password: d.password, ssl: d.ssl ? { rejectUnauthorized: false } : undefined });
    }
    return this.conn.db;
  }

  /** Створює одноразовий ключ інсталятора, якщо сайт ще не встановлено. */
  ensureSetupKey(): string | null {
    if (this.isInstalled()) return null;
    fs.mkdirSync(this.dir, { recursive: true, mode: 0o700 });
    if (!fs.existsSync(this.setupKeyPath)) {
      const key = crypto.randomBytes(9).toString('base64url').toUpperCase().replace(/[^A-Z0-9]/g, '7').match(/.{1,4}/g)!.join('-');
      fs.writeFileSync(this.setupKeyPath, key + '\n', { mode: 0o600 });
    }
    const key = fs.readFileSync(this.setupKeyPath, 'utf8').trim();
    this.log.warn(`\n\n  ┌────────────────────────────────────────────┐\n  │  YEWORK ще не встановлено.                  │\n  │  Ключ встановлення: ${key.padEnd(23)}│\n  │  Відкрийте https://<ваш-домен>/install      │\n  └────────────────────────────────────────────┘\n`);
    return key;
  }
  setupKey(): string | null { try { return fs.readFileSync(this.setupKeyPath, 'utf8').trim(); } catch { return null; } }

  checkWritable(): { ok: boolean; path: string } {
    try { fs.mkdirSync(this.dir, { recursive: true, mode: 0o700 }); fs.accessSync(this.dir, fs.constants.W_OK); return { ok: true, path: this.dir }; }
    catch { return { ok: false, path: this.dir }; }
  }

  /** Атомарно записує конфігурацію, потім lock (lock — останній крок встановлення). */
  writeInstalled(configFile: string, cfg: InstalledConfig) {
    if (!CONFIG_FILE_RE.test(configFile) || configFile === 'install.lock') throw new Error('Некоректне ім’я файлу');
    const target = path.join(this.dir, configFile);
    const tmp = target + '.tmp';
    fs.writeFileSync(tmp, JSON.stringify(cfg, null, 2), { mode: 0o600 });
    fs.renameSync(tmp, target);
    fs.writeFileSync(this.lockPath, JSON.stringify({ configFile, installedAt: cfg.installedAt } satisfies Lock, null, 2), { mode: 0o600 });
    try { fs.unlinkSync(this.setupKeyPath); } catch { /* вже видалено */ }
    this.cfg = cfg;
  }

  async onModuleDestroy() { await this.conn?.pool.end().catch(() => undefined); }
}
