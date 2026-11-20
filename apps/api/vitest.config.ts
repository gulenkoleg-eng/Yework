import { defineConfig } from 'vitest/config';
import swc from 'unplugin-swc';
// SWC потрібен, щоб у тестах працювали декоратори NestJS (emitDecoratorMetadata).
export default defineConfig({ test: { include: ['test/**/*.test.ts'], testTimeout: 60000, hookTimeout: 60000, fileParallelism: false }, plugins: [swc.vite()] });
