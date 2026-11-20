import crypto from 'node:crypto';
import { BadRequestException, HttpException, HttpStatus, Injectable, UnauthorizedException } from '@nestjs/common';
import { and, desc, eq, gt, isNull, sql } from 'drizzle-orm';
import { schema as s } from '@yework/db';
import { RuntimeConfig } from '../config/runtime-config';
import { verifyPassword, hashPassword, randomToken, sha256, hmac, encrypt, decrypt, normalizeUaPhone } from '../common/crypto';
import { RateLimiter } from '../common/rate-limit';
import { MessagingService } from '../messaging/messaging.service';
import { AccessService, STAFF_TTL_MS, USER_TTL_MS, maskContact } from './session';

const loginByIp = new RateLimiter(20, 15 * 60_000);
const loginByAccount = new RateLimiter(5, 15 * 60_000);
const codesByIp = new RateLimiter(10, 60 * 60_000);
/** Хеш-заглушка, щоб час відповіді не видавав, чи існує логін. */
const DUMMY = '$argon2id$v=19$m=19456,t=2,p=1$c29tZXNhbHRzb21lc2FsdA$0n3d6pJ8Jx8V0bq8Xm0Qq4mJ4b1a1vYcJbGkqzv5e0Y';

export const CODE_TTL_MS = 10 * 60_000;
export const CODE_RESEND_MS = 60_000;
export const CODE_MAX_ATTEMPTS = 5;
export const CODES_PER_HOUR = 5;

export type Contact = { kind: 'phone' | 'email'; value: string; channel: 'sms' | 'email' };
type Purpose = 'register' | 'recover';

/** Телефон або email із довільного введення. */
export function parseContact(raw: string): Contact | null {
  const v = raw.trim();
  if (v.includes('@')) {
    const e = v.toLowerCase();
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(e) && e.length <= 254 ? { kind: 'email', value: e, channel: 'email' } : null;
  }
  const p = normalizeUaPhone(v);
  return p ? { kind: 'phone', value: p, channel: 'sms' } : null;
}

@Injectable()
export class AuthService {
  constructor(private readonly rc: RuntimeConfig, private readonly access: AccessService, private readonly msg: MessagingService) {}
  private db() { return this.access.db(); }
  private secret() { return this.rc.config().appSecret; }
  private contactHash(c: Contact) { return hmac(this.secret(), c.value); }
  private codeHash(purpose: string, target: string, code: string) { return hmac(this.secret(), `${purpose}:${target}:${code}`); }

  private async userByContact(c: Contact) {
    const h = this.contactHash(c);
    const col = c.kind === 'phone' ? s.users.phoneHash : s.users.emailHash;
    const [u] = await this.db().select().from(s.users).where(and(eq(col, h), isNull(s.users.deletedAt)));
    return u;
  }

  /* ---------------- коди підтвердження ---------------- */

  private async issueCode(purpose: Purpose, c: Contact, userId: string | null, ip?: string) {
    const db = this.db();
    const target = this.contactHash(c);
    codesByIp.hit('code:' + ip, 'Забагато запитів коду з цієї адреси. Спробуйте за годину.');
    const [last] = await db.select().from(s.verificationCodes)
      .where(and(eq(s.verificationCodes.targetHash, target), eq(s.verificationCodes.purpose, purpose))).orderBy(desc(s.verificationCodes.createdAt)).limit(1);
    if (last && Date.now() - last.createdAt.getTime() < CODE_RESEND_MS) {
      const wait = Math.ceil((CODE_RESEND_MS - (Date.now() - last.createdAt.getTime())) / 1000);
      throw new HttpException({ message: `Новий код можна запросити через ${wait} с.`, retryAfterSec: wait }, HttpStatus.TOO_MANY_REQUESTS);
    }
    const [{ n }] = await db.select({ n: sql<number>`count(*)::int` }).from(s.verificationCodes)
      .where(and(eq(s.verificationCodes.targetHash, target), gt(s.verificationCodes.createdAt, new Date(Date.now() - 3600_000))));
    if (n >= CODES_PER_HOUR) throw new HttpException({ message: 'Забагато кодів на цей контакт. Спробуйте за годину.' }, HttpStatus.TOO_MANY_REQUESTS);
    const code = String(crypto.randomInt(0, 1_000_000)).padStart(6, '0');
    await db.insert(s.verificationCodes).values({ purpose, channel: c.channel, targetHash: target, userId, codeHash: this.codeHash(purpose, target, code), expiresAt: new Date(Date.now() + CODE_TTL_MS), ip });
    return code;
  }

  /** Перевіряє код. Після 5 помилок код згорає. Успіх — код позначається використаним. */
  private async consumeCode(purpose: Purpose, c: Contact, code: string) {
    const db = this.db();
    const target = this.contactHash(c);
    const [row] = await db.select().from(s.verificationCodes)
      .where(and(eq(s.verificationCodes.targetHash, target), eq(s.verificationCodes.purpose, purpose), isNull(s.verificationCodes.consumedAt), gt(s.verificationCodes.expiresAt, new Date())))
      .orderBy(desc(s.verificationCodes.createdAt)).limit(1);
    const bad = () => new BadRequestException({ message: 'Невірний або прострочений код.', fields: { code: 'Невірний код' } });
    if (!row) throw bad();
    if (row.attempts >= CODE_MAX_ATTEMPTS) throw new BadRequestException({ message: 'Забагато невдалих спроб. Запросіть новий код.', fields: { code: 'Запросіть новий код' } });
    const ok = /^\d{6}$/.test(code) && crypto.timingSafeEqual(Buffer.from(row.codeHash), Buffer.from(this.codeHash(purpose, target, code)));
    if (!ok) { await db.update(s.verificationCodes).set({ attempts: row.attempts + 1 }).where(eq(s.verificationCodes.id, row.id)); throw bad(); }
    const [used] = await db.update(s.verificationCodes).set({ consumedAt: new Date() }).where(and(eq(s.verificationCodes.id, row.id), isNull(s.verificationCodes.consumedAt))).returning();
    if (!used) throw bad();
    return row;
  }

  private async siteName() {
    const [r] = await this.db().select().from(s.systemSettings).where(eq(s.systemSettings.key, 'site.name'));
    return (r?.value as string) ?? 'YEWORK';
  }

  /* ---------------- реєстрація ---------------- */

  /** Відповідь однакова, чи існує акаунт: так не можна перебором дізнатися, хто зареєстрований. */
  async registerStart(c: Contact, ip?: string) {
    const site = await this.siteName();
    const existing = await this.userByContact(c);
    if (existing) {
      await this.issueCode('recover', c, existing.id, ip).then(
        (code) => this.msg.send({ channel: c.channel, to: c.value, template: 'register_exists', subject: `${site}: у вас уже є акаунт`,
          text: `${site}: цей ${c.kind === 'phone' ? 'номер' : 'email'} уже зареєстровано. Код для відновлення доступу: ${code}. Якщо це не ви, просто проігноруйте.` }),
        () => undefined,
      );
    } else {
      const code = await this.issueCode('register', c, null, ip);
      await this.msg.send({ channel: c.channel, to: c.value, template: 'register_code', subject: `${site}: код підтвердження`, text: `${site}: ваш код реєстрації ${code}. Діє 10 хвилин. Нікому його не повідомляйте.` });
    }
    return { ok: true, channel: c.channel, to: maskContact(c.value), ttlSec: CODE_TTL_MS / 1000, resendSec: CODE_RESEND_MS / 1000 };
  }

  async registerFinish(c: Contact, code: string, displayName: string, password: string, ip?: string, ua?: string) {
    await this.consumeCode('register', c, code);
    const db = this.db();
    const { dataKey } = this.rc.config();
    const h = this.contactHash(c);
    const login = 'u' + crypto.randomBytes(5).toString('hex');
    const passwordHash = await hashPassword(password);
    let user;
    try {
      [user] = await db.insert(s.users).values({
        login, displayName, passwordHash,
        ...(c.kind === 'phone' ? { phoneHash: h, phoneEnc: encrypt(dataKey, c.value), phoneVerifiedAt: new Date() } : { emailHash: h, emailEnc: encrypt(dataKey, c.value), emailVerifiedAt: new Date() }),
      }).returning();
    } catch (e: any) {
      if (e?.code === '23505' || e?.cause?.code === '23505') throw new BadRequestException({ message: 'Цей контакт уже зареєстровано. Скористайтеся входом або відновленням доступу.' });
      throw e;
    }
    await db.insert(s.auditLog).values({ actorId: user.id, action: 'auth.registered', entityType: 'user', entityId: user.id, ip, data: { via: c.kind } });
    return this.createSession(user.id, false, ip, ua);
  }

  /* ---------------- відновлення доступу ---------------- */

  async recoverStart(c: Contact, ip?: string) {
    const u = await this.userByContact(c);
    if (u && u.status === 'active') {
      const site = await this.siteName();
      const code = await this.issueCode('recover', c, u.id, ip);
      await this.msg.send({ channel: c.channel, to: c.value, template: 'recover_code', subject: `${site}: відновлення доступу`, text: `${site}: код для зміни пароля ${code}. Діє 10 хвилин. Якщо ви не запитували, проігноруйте.` });
    } else {
      codesByIp.hit('code:' + ip, 'Забагато запитів коду з цієї адреси. Спробуйте за годину.');
    }
    return { ok: true, channel: c.channel, to: maskContact(c.value), ttlSec: CODE_TTL_MS / 1000, resendSec: CODE_RESEND_MS / 1000 };
  }

  async recoverFinish(c: Contact, code: string, password: string, ip?: string, ua?: string) {
    const row = await this.consumeCode('recover', c, code);
    const db = this.db();
    const u = await this.userByContact(c);
    if (!u || u.id !== row.userId || u.status !== 'active') throw new BadRequestException({ message: 'Невірний або прострочений код.' });
    await db.update(s.users).set({ passwordHash: await hashPassword(password), updatedAt: new Date() }).where(eq(s.users.id, u.id));
    await db.update(s.sessions).set({ revokedAt: new Date() }).where(and(eq(s.sessions.userId, u.id), isNull(s.sessions.revokedAt)));
    await db.insert(s.auditLog).values({ actorId: u.id, action: 'auth.password_reset', entityType: 'user', entityId: u.id, ip, data: { via: c.kind } });
    const isStaff = u.isOwner || (await this.access.rolesOf(u.id)).length > 0;
    return this.createSession(u.id, isStaff, ip, ua);
  }

  /* ---------------- вхід ---------------- */

  /** Вхід за логіном, телефоном або email. */
  async login(identifier: string, password: string, ip?: string, ua?: string) {
    const db = this.db();
    const id = identifier.trim();
    loginByIp.hit('ip:' + ip, 'Забагато спроб входу з цієї адреси. Зачекайте 15 хвилин.');
    loginByAccount.hit('acc:' + id.toLowerCase(), 'Забагато спроб входу в цей акаунт. Зачекайте 15 хвилин.');
    const c = /^[a-zA-Z][a-zA-Z0-9._-]{2,31}$/.test(id) ? null : parseContact(id);
    const u = c ? await this.userByContact(c) : (await db.select().from(s.users).where(and(sql`${s.users.login} = ${id}`, isNull(s.users.deletedAt))))[0];
    const ok = await verifyPassword(u?.passwordHash ?? DUMMY, password);
    if (!u || !ok) {
      await db.insert(s.auditLog).values({ actorId: u?.id ?? null, action: 'auth.login_failed', ip, data: { by: c?.kind ?? 'login' } });
      throw new UnauthorizedException({ message: 'Невірний логін або пароль.' });
    }
    if (u.status !== 'active') throw new UnauthorizedException({ message: 'Акаунт заблоковано. Зверніться до підтримки.' });
    loginByAccount.reset('acc:' + id.toLowerCase());
    await db.update(s.users).set({ lastLoginAt: new Date() }).where(eq(s.users.id, u.id));
    await db.insert(s.auditLog).values({ actorId: u.id, action: 'auth.login', ip });
    const isStaff = u.isOwner || (await this.access.rolesOf(u.id)).length > 0;
    return this.createSession(u.id, isStaff, ip, ua);
  }

  private async createSession(userId: string, isStaff: boolean, ip?: string, ua?: string) {
    const token = randomToken();
    const ttl = isStaff ? STAFF_TTL_MS : USER_TTL_MS;
    await this.db().insert(s.sessions).values({ userId, tokenHash: sha256(token), ip, userAgent: ua?.slice(0, 300), expiresAt: new Date(Date.now() + ttl) });
    return { token, ttl };
  }

  async me(token: string | undefined) {
    const cu = await this.access.resolve(token);
    if (!cu) return null;
    const db = this.db();
    const [u] = await db.select().from(s.users).where(eq(s.users.id, cu.id));
    const { dataKey } = this.rc.config();
    const mfa = await db.select().from(s.mfaFactors).where(and(eq(s.mfaFactors.userId, cu.id), sql`${s.mfaFactors.confirmedAt} is not null`));
    return {
      id: cu.id, login: cu.login, displayName: cu.displayName, isOwner: cu.isOwner, isStaff: cu.isStaff, roles: cu.roles,
      permissions: [...cu.perms],
      phone: maskContact(u.phoneEnc ? decrypt(dataKey, u.phoneEnc) : null), email: maskContact(u.emailEnc ? decrypt(dataKey, u.emailEnc) : null),
      mfaConfigured: mfa.length > 0, mfaRequired: cu.isStaff, siteName: await this.siteName(),
    };
  }

  async updateProfile(userId: string, displayName: string) {
    await this.db().update(s.users).set({ displayName, updatedAt: new Date() }).where(eq(s.users.id, userId));
  }

  async changePassword(userId: string, current: string, next: string, sessionId: string, ip?: string) {
    const db = this.db();
    const [u] = await db.select().from(s.users).where(eq(s.users.id, userId));
    if (!(await verifyPassword(u.passwordHash, current))) throw new BadRequestException({ message: 'Поточний пароль невірний.', fields: { current: 'Невірний пароль' } });
    await db.update(s.users).set({ passwordHash: await hashPassword(next), updatedAt: new Date() }).where(eq(s.users.id, userId));
    // Вийти з усіх інших пристроїв.
    await db.update(s.sessions).set({ revokedAt: new Date() }).where(and(eq(s.sessions.userId, userId), isNull(s.sessions.revokedAt), sql`${s.sessions.id} <> ${sessionId}`));
    await db.insert(s.auditLog).values({ actorId: userId, action: 'auth.password_changed', entityType: 'user', entityId: userId, ip });
  }

  async logout(token: string | undefined, ip?: string) {
    if (!token || !this.rc.isInstalled()) return;
    const db = this.db();
    const [row] = await db.update(s.sessions).set({ revokedAt: new Date() }).where(eq(s.sessions.tokenHash, sha256(token))).returning();
    if (row) await db.insert(s.auditLog).values({ actorId: row.userId, action: 'auth.logout', ip });
  }
}
