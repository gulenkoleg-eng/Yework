import { createApp } from './app';

async function main() {
  const app = await createApp();
  const port = Number(process.env.PORT ?? 4000);
  await app.listen(port, '0.0.0.0');
}
main().catch((e) => { console.error(e); process.exit(1); });
