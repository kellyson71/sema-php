---
name: rodar-projeto
description: Sobe o ambiente Docker local do sema-php (app PHP, MariaDB, phpMyAdmin) e valida que a aplicação está respondendo. Use quando o usuário pedir para rodar, iniciar, subir ou testar o projeto localmente.
---

# Rodar o sema-php localmente

Ambiente via Docker Compose. Não existe servidor PHP embutido nem
`composer serve` — sempre via containers.

## Subir

```bash
./scripts/start.sh
```

Isso builda a imagem `web` (pode demorar alguns minutos na primeira vez
ou após mudanças no `Dockerfile`/dependências — o build envia bastante
contexto ao daemon, é normal), sobe `db` (MariaDB) e `pma` (phpMyAdmin),
espera o MariaDB responder a `mysqladmin ping` e tenta abrir o navegador
automaticamente (`xdg-open`/`open`/`wslview` — silenciosamente ignorado
se não houver um disponível, ex. ambiente headless).

Rode em background (`run_in_background`) pois o script bloqueia
aguardando o banco ficar pronto e depois continua rodando os logs do
compose.

## Portas

As portas publicadas vêm do `docker-compose.yml` do projeto — **não
são fixas**, pois esse arquivo é frequentemente ajustado localmente
(pode já estar com mudanças não commitadas). Sempre confirme com:

```bash
docker compose ps
```

Os valores documentados no `CLAUDE.md` (8090 app / 8091 phpMyAdmin /
3307 MariaDB) são o padrão, mas trate-os como referência, não verdade
absoluta — confira `docker compose ps` antes de acessar.

## Validar que subiu

```bash
docker compose ps                          # todos os serviços "Up"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:<porta_web>/
```

Espera-se `200`. Se vier `502`/conexão recusada, o container `web`
ainda pode estar inicializando — reconsulte `docker compose ps`.

## Banco de dados

Para popular o banco com dados de teste:

```bash
./scripts/inject-sql.sh                    # usa database/u492577848_SEMA.sql
./scripts/inject-sql.sh outro.sql
```

## Parar

```bash
./scripts/stop.sh
```

## Detecção de ambiente

`includes/config.php` alterna para credenciais locais quando a env var
`DOCKER_ENV=1` está setada (isso é feito pelo próprio
`docker-compose.yml`). Não é necessário configurar nada manualmente.
