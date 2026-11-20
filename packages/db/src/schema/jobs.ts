import { pgSchema, uuid, text, integer, boolean, index, uniqueIndex } from 'drizzle-orm/pg-core';
import { sql } from 'drizzle-orm';
import { ts, lifecycle } from './types';
import { users } from './identity';
import { settlements } from './system';

export const jobs = pgSchema('jobs');

export const categories = jobs.table('categories', {
  id: uuid('id').primaryKey().defaultRandom(),
  slug: text('slug').notNull(),
  name: text('name').notNull(),
  icon: text('icon').notNull().default('briefcase'),
  sort: integer('sort').notNull().default(100),
  isActive: boolean('is_active').notNull().default(true),
}, (t) => [uniqueIndex('jobs_categories_slug_uq').on(t.slug)]);

/** Профіль роботодавця. Один користувач може вести кілька (наприклад, ФОП і підприємство). */
export const employers = jobs.table('employers', {
  id: uuid('id').primaryKey().defaultRandom(),
  ownerId: uuid('owner_id').notNull().references(() => users.id, { onDelete: 'restrict' }),
  kind: text('kind').notNull().default('company'), // company | fop | person
  name: text('name').notNull(),
  edrpou: text('edrpou'),
  about: text('about').notNull().default(''),
  settlementId: uuid('settlement_id').references(() => settlements.id),
  website: text('website'),
  isVerified: boolean('is_verified').notNull().default(false),
  status: text('status').notNull().default('active'), // active | blocked
  ...lifecycle(),
}, (t) => [index('employers_owner_idx').on(t.ownerId)]);

export const VACANCY_STATUSES = ['draft', 'pending', 'published', 'rejected', 'archived'] as const;
export const EMPLOYMENT_TYPES = ['full', 'part', 'shift', 'seasonal', 'remote', 'one_time'] as const;

export const vacancies = jobs.table('vacancies', {
  id: uuid('id').primaryKey().defaultRandom(),
  employerId: uuid('employer_id').notNull().references(() => employers.id, { onDelete: 'restrict' }),
  authorId: uuid('author_id').notNull().references(() => users.id, { onDelete: 'restrict' }),
  title: text('title').notNull(),
  categoryId: uuid('category_id').notNull().references(() => categories.id),
  settlementId: uuid('settlement_id').notNull().references(() => settlements.id),
  address: text('address'),
  employment: text('employment').notNull().default('full'),
  salaryFrom: integer('salary_from'),
  salaryTo: integer('salary_to'),
  salaryPeriod: text('salary_period').notNull().default('month'), // month | day | hour | piece
  description: text('description').notNull(),
  requirements: text('requirements').notNull().default(''),
  offers: text('offers').notNull().default(''),
  noExperience: boolean('no_experience').notNull().default(false),
  contactName: text('contact_name'),
  /** Робочий телефон вакансії — публічний за задумом, тому не шифрується. */
  contactPhone: text('contact_phone'),
  status: text('status').notNull().default('draft'),
  rejectReason: text('reject_reason'),
  moderatedBy: uuid('moderated_by').references(() => users.id),
  moderatedAt: ts('moderated_at'),
  submittedAt: ts('submitted_at'),
  publishedAt: ts('published_at'),
  expiresAt: ts('expires_at'),
  views: integer('views').notNull().default(0),
  ...lifecycle(),
}, (t) => [
  index('vacancies_public_idx').on(t.status, t.publishedAt).where(sql`${t.deletedAt} is null`),
  index('vacancies_settlement_idx').on(t.settlementId),
  index('vacancies_category_idx').on(t.categoryId),
  index('vacancies_employer_idx').on(t.employerId),
]);

/** Відгук шукача на вакансію. */
export const applications = jobs.table('applications', {
  id: uuid('id').primaryKey().defaultRandom(),
  vacancyId: uuid('vacancy_id').notNull().references(() => vacancies.id, { onDelete: 'cascade' }),
  userId: uuid('user_id').notNull().references(() => users.id, { onDelete: 'cascade' }),
  message: text('message').notNull().default(''),
  status: text('status').notNull().default('new'), // new | viewed | invited | declined
  createdAt: ts('created_at').notNull().defaultNow(),
  updatedAt: ts('updated_at').notNull().defaultNow(),
}, (t) => [uniqueIndex('applications_uq').on(t.vacancyId, t.userId)]);
