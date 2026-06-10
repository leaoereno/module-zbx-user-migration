# module-zbx-user-migration

Modulo para **Zabbix 7.0 LTS** que adiciona o menu **Users > User Migration**,
permitindo transferir todos os objetos vinculados a um usuario local para um usuario
provisionado via LDAP, SAML ou qualquer outro IdP, sem manipulacao manual de banco.

> **Compatibilidade de banco:** MySQL 8.0+ e MariaDB 10.5+.
> PostgreSQL nao e suportado nesta versao.

---

## Sumario

- [Contexto e problema](#contexto-e-problema)
- [O que e migrado](#o-que-e-migrado)
- [O que nao e migrado](#o-que-nao-e-migrado)
- [Requisitos](#requisitos)
- [Instalacao](#instalacao)
  - [1. Obter os arquivos](#1-obter-os-arquivos)
  - [2. Instalar via script](#2-instalar-via-script-recomendado)
  - [3. Instalacao manual](#3-instalacao-manual-alternativa)
  - [4. Habilitar no Zabbix](#4-habilitar-no-zabbix)
- [Verificacao](#verificacao)
- [Uso](#uso)
- [Seguranca](#seguranca)
- [Desinstalar](#desinstalar)
- [Atualizar](#atualizar)
- [Solucao de problemas](#solucao-de-problemas)
- [Estrutura do modulo](#estrutura-do-modulo)

---

## Contexto e problema

Em ambientes que transitam de autenticacao local para LDAP/JIT (ex: Authentik como broker OIDC),
um usuario pode ter dashboards, mapas, relatorios e configuracoes criados com sua conta local
antes de ser provisionado via IdP. Apos o provisionamento, a conta IdP e nova e nao possui
nenhum desses objetos.

Este modulo resolve isso com uma interface visual que lista previamente tudo que sera migrado
e executa a transferencia em uma unica transacao atomica com rollback automatico em caso de erro.

---

## O que e migrado

| Entidade | Tabela | Descricao |
|---|---|---|
| Dashboards (ownership) | `dashboard` | Transfere a propriedade dos dashboards |
| Permissoes de Dashboard | `dashboard_user` | Reatribui acessos a dashboards compartilhados |
| Mapas de Rede (ownership) | `sysmaps` | Transfere a propriedade dos mapas |
| Permissoes de Mapa | `sysmap_user` | Reatribui acessos a mapas compartilhados |
| Relatorios Agendados | `report` | Transfere a propriedade dos relatorios |
| Destinatarios de Relatorio | `report_user` | Reatribui destinatarios de relatorios |
| Midias de Notificacao | `media` | Transfere e-mail, SMS, webhook, etc |
| Destinatarios de Action | `opmessage_usr` | Substitui usuario em Trigger Actions |
| API Tokens | `token` | Transfere tokens de API |
| Grupos de Usuario | `users_groups` | Adiciona destino nos grupos do origem (sem duplicar) |
| Preferencias de Interface | `profiles` | Migra filtros salvos e configuracoes de UI |
| Plantao - Telefones | `module_plantao_phones` | Se existir no banco |
| Plantao - Escalas | `module_plantao_schedule` | Se existir no banco |

---

## O que nao e migrado

| Tabela | Motivo |
|---|---|
| `acknowledges`, `alerts`, `auditlog` | Historico imutavel |
| `event_recovery`, `event_suppress`, `problem` | Estado gerenciado pelo servidor |
| `sessions`, `custom_user_sessions` | Sessoes ativas — invalidas apos login |
| `mfa_totp_secret` | Vinculado ao dispositivo do usuario |
| `user_scim_group`, `user_ugset` | Gerenciados pelo LDAP/SCIM |

---

## Requisitos

| Componente | Versao minima |
|---|---|
| Zabbix Server + Frontend | 7.0 LTS |
| PHP | 8.0+ |
| Banco de dados | MySQL 8.0+ ou MariaDB 10.5+ |
| Perfil no Zabbix | Super Admin |
| SO | AlmaLinux 8/9, RHEL 8/9, Rocky Linux, Debian/Ubuntu |

---

## Instalacao

### 1. Obter os arquivos

```bash
git clone https://github.com/leaoereno/module-zbx-user-migration.git
cd module-zbx-user-migration
```

Via SCP:

```bash
scp -r module-zbx-user-migration/ root@<ip-do-servidor>:/tmp/
ssh root@<ip-do-servidor>
cd /tmp/module-zbx-user-migration
```

> **Atencao ao clonar via Git como root:** o `git clone` cria os arquivos com
> `root:root`, impedindo o Apache/Nginx de ler o modulo. Apos o clone, sempre
> ajuste as permissoes antes de habilitar:
>
> ```bash
> # Apache (AlmaLinux/RHEL/Rocky)
> chown -R apache:apache /usr/share/zabbix/modules/module-zbx-user-migration
>
> # Nginx / Docker
> chown -R nginx:nginx /usr/share/zabbix/modules/module-zbx-user-migration
> ```
>
> O `install.sh` ja faz isso automaticamente. O aviso se aplica apenas a
> instalacoes manuais via `git clone` direto no diretorio de modulos.

---

### 2. Instalar via script (recomendado)

```bash
chmod +x install.sh
sudo ./install.sh
```

Saida esperada:

```
-> Copiando modulo para /usr/share/zabbix/modules/module-zbx-user-migration ...
-> Ajustando permissoes ...

OK  Modulo instalado em: /usr/share/zabbix/modules/module-zbx-user-migration
```

Se o servidor web usa `www-data` (Debian/Ubuntu), edite a linha 8 do script:

```bash
ZABBIX_WEB_USER="www-data"
```

**Docker com Nginx:** as permissoes devem ser do usuario `nginx`:

```bash
docker exec <container-frontend> chown -R nginx:nginx \
  /usr/share/zabbix/modules/module-zbx-user-migration
```

---

### 3. Instalacao manual (alternativa)

```bash
MODULE_DEST="/usr/share/zabbix/modules/module-zbx-user-migration"
WEB_USER="apache"

cp -r /tmp/module-zbx-user-migration "$MODULE_DEST"
chown -R "$WEB_USER:$WEB_USER" "$MODULE_DEST"
find "$MODULE_DEST" -type f -exec chmod 644 {} \;
find "$MODULE_DEST" -type d -exec chmod 755 {} \;
```

Estrutura esperada apos instalacao:

```
/usr/share/zabbix/modules/module-zbx-user-migration/
|-- Module.php
|-- README.md
|-- actions/
|   |-- CControllerUserMigrateExecute.php
|   |-- CControllerUserMigratePreview.php
|   `-- CControllerUserMigrateView.php
|-- assets/js/
|   `-- usermigrate.js
|-- install.sh
|-- manifest.json
`-- views/
    `-- usermigrate.view.php
```

---

### 4. Habilitar no Zabbix

1. Acesse com conta **Super Admin**
2. **Administracao > Geral > Modulos**
3. Clique em **"Verificar modulos ausentes"**
4. Habilite **"User Migration"**
5. O menu **Users > User Migration** aparece na navegacao lateral

---

## Verificacao

```bash
# Confirma permissoes (Apache)
sudo -u apache cat /usr/share/zabbix/modules/module-zbx-user-migration/manifest.json

# Sintaxe PHP
php -l /usr/share/zabbix/modules/module-zbx-user-migration/Module.php
php -l /usr/share/zabbix/modules/module-zbx-user-migration/actions/CControllerUserMigrateExecute.php

# Log de erros
tail -f /var/log/httpd/error_log | grep -i "usermigrate\|fatal"
```

Apos habilitar:
- Navegue a **Users > User Migration**
- Selecione origem e destino — badges de autenticacao aparecem (LOCAL/LDAP/SAML/SYSTEM)
- Clique em **"Verificar o que sera migrado"** — lista preview sem alterar nada

---

## Uso

**Passo 1 — Selecionar usuarios**

Selecione o usuario de origem (conta local a ser esvaziada) e o de destino (conta IdP que recebera os objetos). Os badges indicam o tipo de autenticacao de cada usuario.

**Passo 2 — Revisar preview**

Clique em **"Verificar o que sera migrado"**. A lista exibe todas as entidades encontradas agrupadas por tipo. Avisos em amarelo aparecem se o usuario de origem for Super Admin ou Admin nativo.

Nenhuma alteracao e feita nesta etapa.

**Passo 3 — Confirmar**

Clique em **"Confirmar Migracao"**. Um prompt solicita que voce **digite o username do usuario de origem** para confirmar. Apos a confirmacao correta, a migracao e executada em transacao atomica.

**Resultado:**

```
Migracao concluida: usuario_local -> usuario_ldap
3 dashboard(s), 2 mapa(s) de rede, 5 midia(s) migrado(s) com sucesso.
```

A operacao e registrada no **auditlog nativo do Zabbix** (Administracao > Auditoria).

---

## Seguranca

- **Permissao:** apenas Super Admin pode acessar o modulo
- **CSRF:** token validado no execute para prevenir ataques CSRF
- **Confirmacao por username:** o usuario deve digitar o username de origem antes de executar
- **Bloqueio do Admin nativo:** usuarios com ID 1 nao podem ser migrados
- **Aviso de Super Admin:** alerta visual se o usuario de origem tiver role privilegiada
- **Transacao atomica:** rollback completo em caso de qualquer erro
- **Sem duplicatas:** grupos e permissoes ja existentes no destino sao detectados e ignorados
- **Auditoria:** toda migracao e registrada no auditlog do Zabbix com admin, IPs e resumo
- **TABLE_SCHEMA dinamico:** usa `DATABASE()` em vez de string hardcoded `'zabbix'`

---

## Desinstalar

1. **Administracao > Geral > Modulos** — desabilite "User Migration"
2. `rm -rf /usr/share/zabbix/modules/module-zbx-user-migration`

---

## Atualizar

```bash
cd /usr/share/zabbix/modules/module-zbx-user-migration
git pull origin main
chown -R apache:apache .   # ou nginx:nginx em Docker
systemctl restart php-fpm
```

---

## Solucao de problemas

**Modulo nao aparece apos "Verificar modulos ausentes"**

```bash
sudo -u apache cat /usr/share/zabbix/modules/module-zbx-user-migration/manifest.json
# Se "Permission denied": chown -R apache:apache /usr/share/zabbix/modules/module-zbx-user-migration
```

**Zabbix nao carrega apos habilitar**

```bash
# Desabilita via banco
mysql -u root zabbix -e \
  "UPDATE module SET status=0 WHERE relative_path='modules/module-zbx-user-migration';"
systemctl restart php-fpm

# Verifica erro
tail -20 /var/log/httpd/error_log | grep fatal
```

**Docker:**

```bash
docker exec zabbix-frontend bash -c \
  'mysql -h $DB_SERVER_HOST -u $MYSQL_USER -p$MYSQL_PASSWORD $MYSQL_DATABASE \
  -e "UPDATE module SET status=0 WHERE relative_path LIKE \"%migr%\";"'
```

**Botao "Verificar" nao habilita**

Abra F12 > Console e verifique se o JS carregou. Se der MIME type error:

```bash
# Confirma o src gerado pela view
grep "script src" /usr/share/zabbix/modules/module-zbx-user-migration/views/usermigrate.view.php
# O caminho usa basename(dirname(__DIR__)) — deve corresponder ao diretorio fisico
```

**Verificar auditoria apos migracao**

```sql
SELECT * FROM auditlog
WHERE resourcetype = 11
AND details LIKE '%migration%'
ORDER BY clock DESC
LIMIT 10;
```

---

## Estrutura do modulo

```
module-zbx-user-migration/
|-- manifest.json               Declara o modulo e as 3 actions
|-- Module.php                  Registra views e menu Users > User Migration
|-- install.sh                  Copia arquivos e ajusta permissoes
|-- actions/
|   |-- CControllerUserMigrateView.php
|   |   Carrega usuarios com gui_access resolvido via JOIN em usrgrp
|   |-- CControllerUserMigratePreview.php
|   |   Consulta entidades vinculadas ao usuario de origem (somente leitura)
|   |   Emite avisos para usuarios Super Admin e Admin nativo
|   `-- CControllerUserMigrateExecute.php
|       Executa migracao em DBbegin/DBcommit/DBrollback
|       Gera id de users_groups via MAX(id)+1 (sem auto_increment)
|       DELETE de duplicatas usando INNER JOIN (sem subselect na mesma tabela)
|       Usa SELECT COUNT antes do UPDATE (sem ROW_COUNT)
|       Usa DATABASE() no lugar de string hardcoded no INFORMATION_SCHEMA
|       Registra operacao no auditlog nativo do Zabbix
|       Bloqueia migracao do Admin nativo (ID 1)
`-- assets/js/
    `-- usermigrate.js
        Badge dinamico por tipo de IdP (LOCAL/LDAP/SAML/SYSTEM/DISABLED)
        CSRF token enviado no POST do execute
        Confirmacao por digitacao do username de origem
        Exibe avisos de Super Admin no preview
```

---

## Créditos
- **Mantenedor do fork:** Rafael M. A. Leão Ereno (MALE)
- **LinkedIn:** https://www.linkedin.com/in/leaoereno/
- **Projeto original:** NOC Team
- **Inspirado no projeto da Monzphere**

---

## Licença

MIT — use, modifique e distribua livremente.
