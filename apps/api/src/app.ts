import 'reflect-metadata';
import { NestFactory } from '@nestjs/core';
import type { NestExpressApplication } from '@nestjs/platform-express';
import cookieParser from 'cookie-parser';
import { AppModule } from './app.module';
import { RuntimeConfig } from './config/runtime-config';

export async function createApp() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule, { logger: ['error', 'warn', 'log'] });
  app.setGlobalPrefix('api');
  app.set('trust proxy', 1); // за Caddy/Nginx: реальний IP клієнта з X-Forwarded-For
  app.disable('x-powered-by');
  app.use(cookieParser());
  app.useBodyParser('json', { limit: '100kb' });
  app.enableShutdownHooks();
  app.get(RuntimeConfig).ensureSetupKey();
  return app;
}
