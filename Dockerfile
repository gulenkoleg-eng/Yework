# YEWORK — один Dockerfile, дві цілі: api і web.
#   docker build --target api -t yework-api .
#   docker build --target web -t yework-web .
FROM node:22-alpine AS base
RUN corepack enable && corepack prepare pnpm@10.28.0 --activate
WORKDIR /app

FROM base AS build
COPY pnpm-lock.yaml pnpm-workspace.yaml package.json tsconfig.base.json ./
COPY packages/permissions/package.json packages/permissions/
COPY packages/db/package.json packages/db/
COPY apps/api/package.json apps/api/
COPY apps/web/package.json apps/web/
RUN pnpm install --frozen-lockfile
COPY packages packages
COPY apps apps
# Адреса API всередині docker-мережі (зашивається в маршрути Next під час збірки).
ARG API_URL=http://api:4000
ENV API_URL=$API_URL NEXT_TELEMETRY_DISABLED=1
RUN pnpm build
RUN pnpm --filter @yework/api deploy --legacy --prod /out/api

FROM node:22-alpine AS api
ENV NODE_ENV=production PORT=4000 YEWORK_CONFIG_DIR=/app/config
WORKDIR /app
COPY --from=build /out/api ./
RUN mkdir -p /app/config /app/storage && chown -R node:node /app/config /app/storage
USER node
EXPOSE 4000
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:4000/api/health || exit 1
CMD ["node", "dist/main.js"]

FROM node:22-alpine AS web
ENV NODE_ENV=production PORT=3000 HOSTNAME=0.0.0.0 NEXT_TELEMETRY_DISABLED=1 API_URL=http://api:4000
WORKDIR /app
COPY --from=build --chown=node:node /app/apps/web/.next/standalone ./
COPY --from=build --chown=node:node /app/apps/web/.next/static ./apps/web/.next/static
USER node
EXPOSE 3000
CMD ["node", "apps/web/server.js"]
