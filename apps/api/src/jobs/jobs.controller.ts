import { BadRequestException, Body, Controller, ForbiddenException, Get, HttpCode, NotFoundException, Param, ParseUUIDPipe, Post, Query, Req } from '@nestjs/common';
import type { Request } from 'express';
import { and, asc, desc, eq, gte, ilike, inArray, isNull, lte, or, sql } from 'drizzle-orm';
import { z } from 'zod';
import { schema as s } from '@yework/db';
import { AccessService, Auth, User, type CurrentUser } from '../auth/session';
import { parse } from '../install/install.schemas';
import { normalizeUaPhone } from '../common/crypto';
import { MessagingService } from '../messaging/messaging.service';
import { RuntimeConfig } from '../config/runtime-config';
import { decrypt } from '../common/crypto';

const PAGE = 20;
const VACANCY_DAYS = 30;
const EMPLOYMENT = ['full', 'part', 'shift', 'seasonal', 'remote', 'one_time'] as const;
const PERIOD = ['month', 'day', 'hour', 'piece'] as const;

const employerSchema = z.object({
  kind: z.enum(['company', 'fop', 'person']),
  name: z.string().trim().min(2, 'Вкажіть назву').max(120),
  edrpou: z.string().trim().regex(/^(\d{8}|\d{10})?$/, 'ЄДРПОУ — 8 цифр, РНОКПП — 10').optional().or(z.literal('')),
  about: z.string().trim().max(3000).default(''),
  settlementId: z.string().uuid('Оберіть населений пункт').optional().or(z.literal('')),
  website: z.string().trim().url('Некоректна адреса').max(200).optional().or(z.literal('')),
});

const vacancySchema = z.object({
  employerId: z.string().uuid('Оберіть роботодавця'),
  title: z.string().trim().min(3, 'Назва закоротка').max(120),
  categoryId: z.string().uuid('Оберіть категорію'),
  settlementId: z.string().uuid('Оберіть населений пункт'),
  address: z.string().trim().max(200).optional().or(z.literal('')),
  employment: z.enum(EMPLOYMENT),
  salaryFrom: z.coerce.number().int().min(0).max(10_000_000).optional().nullable(),
  salaryTo: z.coerce.number().int().min(0).max(10_000_000).optional().nullable(),
  salaryPeriod: z.enum(PERIOD).default('month'),
  description: z.string().trim().min(30, 'Опишіть роботу детальніше (від 30 символів)').max(8000),
  requirements: z.string().trim().max(4000).default(''),
  offers: z.string().trim().max(4000).default(''),
  noExperience: z.boolean().default(false),
  contactName: z.string().trim().max(80).optional().or(z.literal('')),
  contactPhone: z.string().trim().max(20).optional().or(z.literal('')),
}).refine((v) => !v.salaryFrom || !v.salaryTo || v.salaryTo >= v.salaryFrom, { message: 'Зарплата «до» менша за «від»', path: ['salaryTo'] });

const rejectSchema = z.object({ reason: z.string().trim().min(5, 'Вкажіть причину для роботодавця').max(500) });
const applySchema = z.object({ message: z.string().trim().max(2000).default('') });

/** Поля вакансії для публічного показу. */
const publicCols = {
  id: s.vacancies.id, title: s.vacancies.title, employment: s.vacancies.employment, salaryFrom: s.vacancies.salaryFrom, salaryTo: s.vacancies.salaryTo,
  salaryPeriod: s.vacancies.salaryPeriod, noExperience: s.vacancies.noExperience, publishedAt: s.vacancies.publishedAt,
  employer: s.employers.name, employerVerified: s.employers.isVerified, settlement: s.settlements.name, category: s.categories.name, categorySlug: s.categories.slug,
};

@Controller()
export class JobsController {
  constructor(private readonly access: AccessService, private readonly msg: MessagingService, private readonly rc: RuntimeConfig) {}
  private db() { return this.access.db(); }

  private async premoderation(): Promise<boolean> {
    const [r] = await this.db().select().from(s.systemSettings).where(eq(s.systemSettings.key, 'moderation.premoderation'));
    const v = r?.value as any;
    return v === undefined ? true : typeof v === 'boolean' ? v : v?.jobs !== false;
  }

  /* ---------------- довідники ---------------- */

  @Get('reference')
  async reference() {
    const db = this.db();
    const settlements = await db.select({ id: s.settlements.id, name: s.settlements.name, kind: s.settlements.kind, community: s.communities.name })
      .from(s.settlements).innerJoin(s.communities, eq(s.communities.id, s.settlements.communityId)).orderBy(asc(s.settlements.name));
    const categories = await db.select({ id: s.categories.id, slug: s.categories.slug, name: s.categories.name, icon: s.categories.icon })
      .from(s.categories).where(eq(s.categories.isActive, true)).orderBy(asc(s.categories.sort));
    const counts = await db.select({ categoryId: s.vacancies.categoryId, n: sql<number>`count(*)::int` }).from(s.vacancies)
      .where(and(eq(s.vacancies.status, 'published'), isNull(s.vacancies.deletedAt))).groupBy(s.vacancies.categoryId);
    return { settlements, categories: categories.map((c) => ({ ...c, count: counts.find((x) => x.categoryId === c.id)?.n ?? 0 })) };
  }

  /* ---------------- публічні вакансії ---------------- */

  @Get('jobs')
  async list(@Query() q: Record<string, string>) {
    const db = this.db();
    const p = Math.max(1, parseInt(q.page, 10) || 1);
    const conds = [eq(s.vacancies.status, 'published'), isNull(s.vacancies.deletedAt)];
    const term = (q.q ?? '').trim().slice(0, 100);
    if (term) conds.push(or(ilike(s.vacancies.title, `%${term}%`), ilike(s.vacancies.description, `%${term}%`), ilike(s.employers.name, `%${term}%`))!);
    if (q.settlement && /^[0-9a-f-]{36}$/.test(q.settlement)) conds.push(eq(s.vacancies.settlementId, q.settlement));
    if (q.category) conds.push(eq(s.categories.slug, q.category));
    if (q.employment && (EMPLOYMENT as readonly string[]).includes(q.employment)) conds.push(eq(s.vacancies.employment, q.employment));
    if (q.noExperience === '1') conds.push(eq(s.vacancies.noExperience, true));
    if (q.salary && +q.salary > 0) conds.push(or(gte(s.vacancies.salaryTo, +q.salary), gte(s.vacancies.salaryFrom, +q.salary))!);
    const where = and(...conds);
    const base = () => db.select(publicCols).from(s.vacancies)
      .innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId))
      .innerJoin(s.settlements, eq(s.settlements.id, s.vacancies.settlementId))
      .innerJoin(s.categories, eq(s.categories.id, s.vacancies.categoryId));
    const [{ n }] = await db.select({ n: sql<number>`count(*)::int` }).from(s.vacancies)
      .innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId)).innerJoin(s.categories, eq(s.categories.id, s.vacancies.categoryId)).where(where);
    const order = q.sort === 'salary' ? [desc(sql`coalesce(${s.vacancies.salaryTo}, ${s.vacancies.salaryFrom}, 0)`)] : [desc(s.vacancies.publishedAt)];
    const items = await base().where(where).orderBy(...order).limit(PAGE).offset((p - 1) * PAGE);
    return { total: n, page: p, pageSize: PAGE, items };
  }

  @Get('jobs/:id')
  async one(@Param('id', ParseUUIDPipe) id: string) {
    const db = this.db();
    const [v] = await db.select({ v: s.vacancies, employer: s.employers, settlement: s.settlements.name, category: s.categories.name, categorySlug: s.categories.slug })
      .from(s.vacancies).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId))
      .innerJoin(s.settlements, eq(s.settlements.id, s.vacancies.settlementId)).innerJoin(s.categories, eq(s.categories.id, s.vacancies.categoryId))
      .where(and(eq(s.vacancies.id, id), eq(s.vacancies.status, 'published'), isNull(s.vacancies.deletedAt)));
    if (!v) throw new NotFoundException({ message: 'Вакансію не знайдено або її знято з публікації.' });
    await db.update(s.vacancies).set({ views: sql`${s.vacancies.views} + 1` }).where(eq(s.vacancies.id, id));
    const x = v.v;
    return {
      id: x.id, title: x.title, employment: x.employment, salaryFrom: x.salaryFrom, salaryTo: x.salaryTo, salaryPeriod: x.salaryPeriod,
      description: x.description, requirements: x.requirements, offers: x.offers, noExperience: x.noExperience, address: x.address,
      contactName: x.contactName, contactPhone: x.contactPhone, publishedAt: x.publishedAt, expiresAt: x.expiresAt, views: x.views + 1,
      settlement: v.settlement, category: v.category, categorySlug: v.categorySlug,
      employer: { id: v.employer.id, name: v.employer.name, kind: v.employer.kind, about: v.employer.about, verified: v.employer.isVerified, website: v.employer.website },
    };
  }

  @Post('jobs/:id/apply') @HttpCode(200) @Auth()
  async apply(@Param('id', ParseUUIDPipe) id: string, @Body() body: unknown, @User() u: CurrentUser) {
    const { message } = parse(applySchema, body);
    const db = this.db();
    const [v] = await db.select({ v: s.vacancies, ownerId: s.employers.ownerId }).from(s.vacancies).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId))
      .where(and(eq(s.vacancies.id, id), eq(s.vacancies.status, 'published')));
    if (!v) throw new NotFoundException({ message: 'Вакансію не знайдено.' });
    if (v.ownerId === u.id) throw new BadRequestException({ message: 'Це ваша вакансія.' });
    const [row] = await db.insert(s.applications).values({ vacancyId: id, userId: u.id, message }).onConflictDoNothing().returning();
    if (!row) throw new BadRequestException({ message: 'Ви вже відгукнулися на цю вакансію.' });
    return { ok: true };
  }

  /* ---------------- кабінет роботодавця ---------------- */

  @Get('me/employers') @Auth()
  async myEmployers(@User() u: CurrentUser) {
    return this.db().select().from(s.employers).where(and(eq(s.employers.ownerId, u.id), isNull(s.employers.deletedAt))).orderBy(asc(s.employers.createdAt));
  }

  @Post('me/employers') @HttpCode(200) @Auth()
  async saveEmployer(@Body() body: unknown, @User() u: CurrentUser, @Req() req: Request) {
    const b = parse(employerSchema, body);
    const id = (body as any)?.id as string | undefined;
    const db = this.db();
    const data = { kind: b.kind, name: b.name, edrpou: b.edrpou || null, about: b.about, settlementId: b.settlementId || null, website: b.website || null };
    if (id) {
      const [e] = await db.update(s.employers).set({ ...data, updatedAt: new Date() }).where(and(eq(s.employers.id, id), eq(s.employers.ownerId, u.id))).returning();
      if (!e) throw new NotFoundException({ message: 'Профіль не знайдено.' });
      return e;
    }
    const mine = await db.select({ n: sql<number>`count(*)::int` }).from(s.employers).where(eq(s.employers.ownerId, u.id));
    if (mine[0].n >= 5) throw new BadRequestException({ message: 'Можна мати до 5 профілів роботодавця.' });
    const [e] = await db.insert(s.employers).values({ ...data, ownerId: u.id }).returning();
    await db.insert(s.auditLog).values({ actorId: u.id, action: 'jobs.employer_created', entityType: 'employer', entityId: e.id, ip: req.ip });
    return e;
  }

  @Get('me/vacancies') @Auth()
  async myVacancies(@User() u: CurrentUser) {
    const db = this.db();
    const rows = await db.select({ v: s.vacancies, employer: s.employers.name, settlement: s.settlements.name,
      applications: sql<number>`(select count(*)::int from jobs.applications a where a.vacancy_id = ${s.vacancies.id})` })
      .from(s.vacancies).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId)).innerJoin(s.settlements, eq(s.settlements.id, s.vacancies.settlementId))
      .where(and(eq(s.employers.ownerId, u.id), isNull(s.vacancies.deletedAt))).orderBy(desc(s.vacancies.updatedAt));
    return rows.map((r) => ({ ...r.v, employer: r.employer, settlement: r.settlement, applications: r.applications }));
  }

  @Get('me/vacancies/:id') @Auth()
  async myVacancy(@Param('id', ParseUUIDPipe) id: string, @User() u: CurrentUser) {
    const v = await this.ownVacancy(id, u.id);
    const db = this.db();
    const key = this.rc.config().dataKey;
    const apps = await db.select({ a: s.applications, name: s.users.displayName, phoneEnc: s.users.phoneEnc, emailEnc: s.users.emailEnc })
      .from(s.applications).innerJoin(s.users, eq(s.users.id, s.applications.userId)).where(eq(s.applications.vacancyId, id)).orderBy(desc(s.applications.createdAt));
    // Контакти кандидата бачить лише автор вакансії, на яку той сам відгукнувся.
    return { ...v, applications: apps.map((x) => ({ id: x.a.id, at: x.a.createdAt, message: x.a.message, status: x.a.status, name: x.name,
      phone: x.phoneEnc ? decrypt(key, x.phoneEnc) : null, email: x.emailEnc ? decrypt(key, x.emailEnc) : null })) };
  }

  private async ownVacancy(id: string, userId: string) {
    const [r] = await this.db().select({ v: s.vacancies }).from(s.vacancies).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId))
      .where(and(eq(s.vacancies.id, id), eq(s.employers.ownerId, userId), isNull(s.vacancies.deletedAt)));
    if (!r) throw new NotFoundException({ message: 'Вакансію не знайдено.' });
    return r.v;
  }

  /** Створення або редагування. submit=true — відправити на модерацію (або одразу опублікувати, якщо премодерацію вимкнено). */
  @Post('me/vacancies') @HttpCode(200) @Auth()
  async saveVacancy(@Body() body: unknown, @User() u: CurrentUser, @Req() req: Request) {
    const b = parse(vacancySchema, body);
    const id = (body as any)?.id as string | undefined;
    const submit = (body as any)?.submit === true;
    const db = this.db();
    const [emp] = await db.select().from(s.employers).where(and(eq(s.employers.id, b.employerId), eq(s.employers.ownerId, u.id), isNull(s.employers.deletedAt)));
    if (!emp) throw new BadRequestException({ message: 'Спочатку створіть профіль роботодавця.', fields: { employerId: 'Оберіть роботодавця' } });
    if (emp.status !== 'active') throw new ForbiddenException({ message: 'Профіль роботодавця заблоковано.' });
    let phone: string | null = null;
    if (b.contactPhone) { phone = normalizeUaPhone(b.contactPhone); if (!phone) throw new BadRequestException({ message: 'Некоректний телефон', fields: { contactPhone: 'Формат: 067 123 45 67' } }); }
    const data = {
      employerId: b.employerId, title: b.title, categoryId: b.categoryId, settlementId: b.settlementId, address: b.address || null, employment: b.employment,
      salaryFrom: b.salaryFrom ?? null, salaryTo: b.salaryTo ?? null, salaryPeriod: b.salaryPeriod, description: b.description, requirements: b.requirements,
      offers: b.offers, noExperience: b.noExperience, contactName: b.contactName || null, contactPhone: phone,
    };
    let v;
    if (id) {
      const cur = await this.ownVacancy(id, u.id);
      // Будь-яка зміна опублікованої вакансії знову проходить модерацію.
      const status = cur.status === 'published' || cur.status === 'rejected' ? 'draft' : cur.status;
      [v] = await db.update(s.vacancies).set({ ...data, status, updatedAt: new Date(), version: sql`${s.vacancies.version} + 1` }).where(eq(s.vacancies.id, id)).returning();
    } else {
      const [{ n }] = await db.select({ n: sql<number>`count(*)::int` }).from(s.vacancies).where(and(eq(s.vacancies.authorId, u.id), gte(s.vacancies.createdAt, sql`now() - interval '1 day'`)));
      if (n >= 20) throw new BadRequestException({ message: 'Ліміт: до 20 нових вакансій на добу.' });
      [v] = await db.insert(s.vacancies).values({ ...data, authorId: u.id, status: 'draft' }).returning();
    }
    if (submit) v = await this.submit(v.id, u.id, req.ip);
    return v;
  }

  private async submit(id: string, userId: string, ip?: string) {
    const db = this.db();
    const pre = await this.premoderation();
    const now = new Date();
    const set = pre
      ? { status: 'pending', submittedAt: now, rejectReason: null }
      : { status: 'published', submittedAt: now, publishedAt: now, expiresAt: new Date(now.getTime() + VACANCY_DAYS * 864e5), rejectReason: null };
    const [v] = await db.update(s.vacancies).set(set).where(eq(s.vacancies.id, id)).returning();
    await db.insert(s.auditLog).values({ actorId: userId, action: pre ? 'jobs.vacancy_submitted' : 'jobs.vacancy_published', entityType: 'vacancy', entityId: id, ip });
    return v;
  }

  @Post('me/vacancies/:id/archive') @HttpCode(200) @Auth()
  async archive(@Param('id', ParseUUIDPipe) id: string, @User() u: CurrentUser, @Req() req: Request) {
    await this.ownVacancy(id, u.id);
    await this.db().update(s.vacancies).set({ status: 'archived', updatedAt: new Date() }).where(eq(s.vacancies.id, id));
    await this.db().insert(s.auditLog).values({ actorId: u.id, action: 'jobs.vacancy_archived', entityType: 'vacancy', entityId: id, ip: req.ip });
    return { ok: true };
  }

  @Get('me/applications') @Auth()
  async myApplications(@User() u: CurrentUser) {
    return this.db().select({ id: s.applications.id, at: s.applications.createdAt, status: s.applications.status, vacancyId: s.vacancies.id, title: s.vacancies.title, employer: s.employers.name, vacancyStatus: s.vacancies.status })
      .from(s.applications).innerJoin(s.vacancies, eq(s.vacancies.id, s.applications.vacancyId)).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId))
      .where(eq(s.applications.userId, u.id)).orderBy(desc(s.applications.createdAt));
  }

  /* ---------------- модерація ---------------- */

  @Get('admin/moderation/vacancies') @Auth('jobs.listing:moderate')
  async queue(@Query('status') status = 'pending') {
    const st = ['pending', 'published', 'rejected'].includes(status) ? status : 'pending';
    const rows = await this.db().select({ v: s.vacancies, employer: s.employers.name, employerVerified: s.employers.isVerified, settlement: s.settlements.name, category: s.categories.name, author: s.users.displayName })
      .from(s.vacancies).innerJoin(s.employers, eq(s.employers.id, s.vacancies.employerId)).innerJoin(s.settlements, eq(s.settlements.id, s.vacancies.settlementId))
      .innerJoin(s.categories, eq(s.categories.id, s.vacancies.categoryId)).innerJoin(s.users, eq(s.users.id, s.vacancies.authorId))
      .where(and(eq(s.vacancies.status, st), isNull(s.vacancies.deletedAt)))
      .orderBy(st === 'pending' ? asc(s.vacancies.submittedAt) : desc(s.vacancies.moderatedAt)).limit(100);
    return rows.map((r) => ({ ...r.v, employer: r.employer, employerVerified: r.employerVerified, settlement: r.settlement, category: r.category, author: r.author }));
  }

  @Post('admin/vacancies/:id/approve') @HttpCode(200) @Auth('jobs.listing:moderate')
  async approve(@Param('id', ParseUUIDPipe) id: string, @User() u: CurrentUser, @Req() req: Request) {
    return this.decide(id, u, req.ip, 'published');
  }

  @Post('admin/vacancies/:id/reject') @HttpCode(200) @Auth('jobs.listing:moderate')
  async reject(@Param('id', ParseUUIDPipe) id: string, @Body() body: unknown, @User() u: CurrentUser, @Req() req: Request) {
    return this.decide(id, u, req.ip, 'rejected', parse(rejectSchema, body).reason);
  }

  private async decide(id: string, u: CurrentUser, ip: string | undefined, to: 'published' | 'rejected', reason?: string) {
    const db = this.db();
    const now = new Date();
    const set = to === 'published'
      ? { status: to, moderatedBy: u.id, moderatedAt: now, publishedAt: now, expiresAt: new Date(now.getTime() + VACANCY_DAYS * 864e5), rejectReason: null }
      : { status: to, moderatedBy: u.id, moderatedAt: now, rejectReason: reason };
    // Модератор не може модерувати власну вакансію.
    const [v] = await db.update(s.vacancies).set(set)
      .where(and(eq(s.vacancies.id, id), inArray(s.vacancies.status, to === 'rejected' ? ['pending', 'published'] : ['pending']), sql`${s.vacancies.authorId} <> ${u.id}`)).returning();
    if (!v) throw new BadRequestException({ message: 'Вакансія вже оброблена, або це ваша власна вакансія.' });
    await db.insert(s.auditLog).values({ actorId: u.id, action: to === 'published' ? 'jobs.vacancy_approved' : 'jobs.vacancy_rejected', entityType: 'vacancy', entityId: id, ip, data: reason ? { reason } : {} });
    return { ok: true };
  }

  /** Зняття прострочених вакансій (викликається при старті та щогодини). */
  async expire() {
    await this.db().update(s.vacancies).set({ status: 'archived' }).where(and(eq(s.vacancies.status, 'published'), lte(s.vacancies.expiresAt, new Date())));
  }
}
