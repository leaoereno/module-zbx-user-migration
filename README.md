# zbx-user-migrate

Modulo para **Zabbix 7.0 LTS** que adiciona o menu **Usuarios > Migracao de Usuarios**,
permitindo transferir todos os objetos vinculados a um usuario local para um usuario
provisionado via LDAP/JIT, sem manipulacao manual de banco de dados.

---

## Sumario

- [Contexto e problema](#contexto-e-problema)
- [O que e migrado](#o-que-e-migrado)
- [O que nao e migrado](#o-que-nao-e-migrado)
- [Requisitos](#requisitos)
- [Instalacao](#instalacao)
  - [1. Obter os arquivos](#1-obter-os-arquivos)
  - [2. Criar o diretorio de modulos](#2-criar-o-diretorio-de-modulos-se-nao-existir)
  - [3. Instalar via script](#3-instalar-via-script-recomendado)
  - [4. Instalacao manual](#4-instalacao-manual-alternativa)
  - [5. Habilitar no Zabbix](#5-habilitar-no-zabbix)
- [Verificacao](#verificacao)
- [Uso](#uso)
- [Desinstalar](#desinstalar)
- [Atualizar](#atualizar)
- [Solucao de problemas](#solucao-de-problemas)
- [Estrutura do modulo](#estrutura-do-modulo)
- [Seguranca](#seguranca)

---

## Contexto e problema

Em ambientes que transitam de autenticacao local para LDAP com provisionamento JIT
(ex: Authentik como broker OIDC), e comum que um usuario ja possua dashboards, mapas,
relatorios e configuracoes de notificacao criados com sua conta local antes de ser
provisionado via LDAP.

Apos o provisionamento, o usuario LDAP e uma conta nova e separada -- sem nenhum dos
objetos da conta original. A alternativa manual de reatribuir cada objeto individualmente
e inviavel em ambientes com muitos usuarios e objetos.

Este modulo resolve isso com uma interface visual que lista previamente tudo que sera
migrado e executa a transferencia em uma unica transacao atomica.

---

## O que e migrado

| Entidade | Tabela | Descricao |
|---|---|---|
| Dashboards (ownership) | `dashboard` | Transfere a propriedade dos dashboards criados pelo usuario |
| Permissoes de Dashboard | `dashboard_user` | Reatribui acessos a dashboards compartilhados |
| Mapas de Rede (ownership) | `sysmaps` | Transfere a propriedade dos mapas de rede |
| Permissoes de Mapa | `sysmap_user` | Reatribui acessos a mapas compartilhados |
| Relatorios Agendados | `report` | Transfere a propriedade dos relatorios |
| Destinatarios de Relatorio | `report_user` | Reatribui o usuario como destinatario de relatorios |
| Midias de Notificacao | `media` | Transfere configuracoes de e-mail, SMS, webhook, etc |
| Destinatarios de Action | `opmessage_usr` | Substitui o usuario em operacoes de Trigger Actions |
| API Tokens | `token` | Transfere tokens de API gerados pelo usuario |
| Grupos de Usuario | `users_groups` | Adiciona o destino nos grupos do origem (sem duplicar) |
| Preferencias de Interface | `profiles` | Migra filtros salvos, colunas e configuracoes de UI |
| Plantao - Telefones | `module_plantao_phones` | Transfere registros de telefone do modulo de plantao |
| Plantao - Escalas | `module_plantao_schedule` | Transfere escalas de plantao vinculadas ao usuario |

> As tabelas de plantao sao verificadas dinamicamente. Se nao existirem no banco,
> as entradas correspondentes sao ignoradas sem erro.

---

## O que nao e migrado

Os itens abaixo sao intencionalmente ignorados por serem dados historicos ou de sistema:

| Tabela | Motivo |
|---|---|
| `acknowledges` | Historico de reconhecimento de problemas -- imutavel |
| `alerts` | Log historico de alertas disparados |
| `auditlog` | Trilha de auditoria -- nao deve ser alterada |
| `event_recovery`, `event_suppress` | Eventos historicos |
| `problem` | Estado de problemas ativos -- gerenciado pelo servidor |
| `sessions`, `custom_user_sessions` | Sessoes ativas -- invalidas apos login |
| `mfa_totp_secret` | Segredo TOTP -- vinculado ao dispositivo do usuario |
| `user_scim_group`, `user_ugset` | Gerenciados automaticamente pelo LDAP/SCIM |

---

## Requisitos

| Componente | Versao minima |
|---|---|
| Zabbix Server + Frontend | 7.0 LTS |
| PHP | 8.0+ |
| Banco de dados | MySQL 8.0+ / MariaDB 10.5+ / PostgreSQL 13+ |
| Perfil no Zabbix | Super Admin |
| Sistema operacional | AlmaLinux 8/9, RHEL 8/9, Rocky Linux, Debian/Ubuntu |

> O modulo atua exclusivamente no frontend PHP.
> Nao requer alteracoes no Zabbix Server ou Agent.

---

## Instalacao

### 1. Obter os arquivos

**Via Git (recomendado):**

```bash
git clone https://github.com/leaoereno/zbx-user-migrate.git
cd zbx-user-migrate
```

**Via SCP (transferir ao servidor diretamente):**

```bash
# Na maquina local
scp zbx-user-migrate.tar.gz root@<ip-do-servidor>:/tmp/

# No servidor
cd /tmp
tar -xzf zbx-user-migrate.tar.gz
cd zbx-user-migrate
```

---

### 2. Criar o diretorio de modulos (se nao existir)

```bash
ls /usr/share/zabbix/modules/
```

Se o diretorio nao existir:

```bash
mkdir -p /usr/share/zabbix/modules
```

Identifique o usuario do servidor web:

```bash
# Apache (AlmaLinux / RHEL / Rocky)
ps aux | grep httpd | grep -v grep | awk '{print $1}' | head -1

# Nginx
ps aux | grep nginx | grep -v grep | awk '{print $1}' | head -1
```

O usuario normalmente e `apache` (RHEL/AlmaLinux) ou `www-data` (Debian/Ubuntu).

---

### 3. Instalar via script (recomendado)

```bash
chmod +x install.sh
sudo ./install.sh
```

**Saida esperada:**

```
-> Copiando modulo para /usr/share/zabbix/modules/zbx-user-migrate ...
-> Ajustando permissoes ...

OK  Modulo instalado em: /usr/share/zabbix/modules/zbx-user-migrate

Proximos passos:
  1. Acesse o Zabbix: Administracao > Geral > Modulos
  2. Clique em 'Verificar modulos ausentes'
  3. Habilite o modulo 'User Migration'
  4. Acesse Usuarios > Migracao de Usuarios
```

**Se o seu servidor web usa `www-data` (Debian/Ubuntu)**, edite a linha 8 do script antes de executar:

```bash
# install.sh linha 8
ZABBIX_WEB_USER="www-data"
```

---

### 4. Instalacao manual (alternativa)

```bash
MODULE_DEST="/usr/share/zabbix/modules/zbx-user-migrate"
WEB_USER="apache"   # ajuste se necessario

cp -r /tmp/zbx-user-migrate "$MODULE_DEST"
chown -R "$WEB_USER:$WEB_USER" "$MODULE_DEST"
find "$MODULE_DEST" -type f -exec chmod 644 {} \;
find "$MODULE_DEST" -type d -exec chmod 755 {} \;
```

Confirme a estrutura apos a copia:

```bash
find /usr/share/zabbix/modules/zbx-user-migrate -type f | sort
```

Resultado esperado:

```
/usr/share/zabbix/modules/zbx-user-migrate/Module.php
/usr/share/zabbix/modules/zbx-user-migrate/README.md
/usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigrateExecute.php
/usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigratePreview.php
/usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigrateView.php
/usr/share/zabbix/modules/zbx-user-migrate/install.sh
/usr/share/zabbix/modules/zbx-user-migrate/manifest.json
/usr/share/zabbix/modules/zbx-user-migrate/views/usermigrate.view.js
/usr/share/zabbix/modules/zbx-user-migrate/views/usermigrate.view.php
```

---

### 5. Habilitar no Zabbix

1. Acesse o Zabbix com uma conta **Super Admin**
2. Navegue ate **Administracao > Geral > Modulos**
3. Clique no botao **"Verificar modulos ausentes"** no canto superior direito
4. O modulo **"User Migration"** aparecera na lista com status `Desabilitado`
5. Clique no toggle para habilitar

Apos habilitar, o menu **Usuarios > Migracao de Usuarios** estara disponivel na navegacao lateral.

> Se o modulo nao aparecer apos clicar em "Verificar modulos ausentes",
> consulte a secao [Solucao de problemas](#solucao-de-problemas).

---

## Verificacao

Apos habilitar, confirme que tudo esta funcionando:

**1. Verifique o menu:**

- Navegue ate **Usuarios** na barra lateral
- O item **"Migracao de Usuarios"** deve aparecer no submenu

**2. Verifique a pagina:**

- Acesse **Usuarios > Migracao de Usuarios**
- A pagina deve exibir dois selects: "Usuario de Origem" e "Usuario de Destino"

**3. Teste o preview sem executar:**

- Selecione um usuario de origem e um de destino
- Clique em **"Verificar o que sera migrado"**
- A lista de objetos deve aparecer sem erros

**4. Verifique os logs do servidor web:**

```bash
# Apache - AlmaLinux/RHEL
tail -f /var/log/httpd/error_log | grep -i "usermigrate\|fatal\|error"
```

---

## Uso

### Fluxo completo

**Passo 1 — Selecionar usuarios**

Acesse **Usuarios > Migracao de Usuarios** e selecione:
- **Usuario de Origem:** conta local que sera esvaziada
- **Usuario de Destino:** conta LDAP que recebera os objetos

O botao **"Verificar o que sera migrado"** e habilitado apenas quando ambos os campos
estao preenchidos e sao diferentes entre si.

**Passo 2 — Revisar o preview**

Apos clicar em "Verificar", uma lista expansivel exibe todas as entidades encontradas,
agrupadas por tipo, com a contagem de objetos em cada categoria.

Clique em **"v expandir"** em cada secao para ver os nomes individuais dos objetos.

Nenhuma alteracao e feita nesta etapa.

**Passo 3 — Confirmar a migracao**

Clique em **"Confirmar Migracao"**. Um dialogo de confirmacao exibe os nomes dos
dois usuarios e um aviso de que a operacao e irreversivel.

Apos confirmar, a migracao e executada em uma transacao atomica:
- Se tudo correr bem: mensagem de sucesso com resumo do que foi migrado
- Se qualquer erro ocorrer: rollback completo, nenhuma alteracao e salva

**Exemplo de resultado:**

```
Migracao concluida: usuario_local -> usuario_ldap
3 dashboard(s), 2 mapa(s) de rede, 5 midia(s) de notificacao,
2 grupo(s) de usuario migrado(s) com sucesso.
```

### Comportamento com duplicatas

O modulo trata conflitos automaticamente:

- **Grupos:** apenas grupos que o destino ainda nao possui sao adicionados
- **Permissoes de dashboard/mapa:** registros duplicados sao removidos do origem antes da transferencia
- **Preferencias de interface:** apenas preferencias ausentes no destino sao migradas; as existentes sao preservadas

---

## Desinstalar

**1. Desabilitar no Zabbix:**

- **Administracao > Geral > Modulos**
- Desabilite o modulo "User Migration"

**2. Remover os arquivos:**

```bash
rm -rf /usr/share/zabbix/modules/zbx-user-migrate
```

**3. Verificar remocao:**

- Volte em **Administracao > Geral > Modulos**
- Clique em "Verificar modulos ausentes"
- O modulo nao deve mais aparecer

---

## Atualizar

```bash
cd /usr/share/zabbix/modules/zbx-user-migrate
git pull origin main
chown -R apache:apache .
systemctl restart php-fpm
```

Nao e necessario desabilitar o modulo antes de atualizar.

---

## Solucao de problemas

**Modulo nao aparece apos "Verificar modulos ausentes"**

Verifique se o manifest.json esta acessivel:

```bash
sudo -u apache cat /usr/share/zabbix/modules/zbx-user-migrate/manifest.json
```

Se retornar "Permission denied":

```bash
chown -R apache:apache /usr/share/zabbix/modules/zbx-user-migrate
```

---

**Menu "Migracao de Usuarios" nao aparece apos habilitar**

Verifique erros de PHP no log:

```bash
tail -30 /var/log/httpd/error_log | grep fatal
```

Teste a sintaxe do Module.php:

```bash
php -l /usr/share/zabbix/modules/zbx-user-migrate/Module.php
```

---

**Zabbix nao carrega apos habilitar o modulo**

Desabilite via banco para recuperar o acesso:

```bash
mysql -u root zabbix -e \
  "UPDATE module SET status=0 WHERE relative_path='modules/zbx-user-migrate';"
systemctl restart php-fpm
```

Depois verifique o log:

```bash
tail -20 /var/log/httpd/error_log | grep -i "fatal\|error"
```

---

**Preview retorna erro "Usuário não encontrado"**

Confirme que os dois userids existem no banco:

```bash
mysql -u root zabbix -e \
  "SELECT userid, username FROM users ORDER BY username;"
```

---

**Migracao executada mas objetos ainda aparecem no usuario de origem**

Verifique se o UPDATE foi aplicado:

```sql
-- Substitua <userid_dst> pelo ID do usuario destino
SELECT 'dashboards' as tipo, COUNT(*) as total
FROM dashboard WHERE userid = <userid_dst>
UNION ALL
SELECT 'mapas', COUNT(*) FROM sysmaps WHERE userid = <userid_dst>
UNION ALL
SELECT 'midias', COUNT(*) FROM media WHERE userid = <userid_dst>;
```

---

**Erro de sintaxe PHP ao habilitar**

```bash
php -l /usr/share/zabbix/modules/zbx-user-migrate/Module.php
php -l /usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigrateExecute.php
php -l /usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigratePreview.php
php -l /usr/share/zabbix/modules/zbx-user-migrate/actions/CControllerUserMigrateView.php
```

Todos devem retornar `No syntax errors detected`.

---

## Estrutura do modulo

```
zbx-user-migrate/
|
|-- manifest.json
|   Declara o modulo, versao, namespace e as 3 actions:
|   usermigrate.view, usermigrate.preview, usermigrate.execute
|
|-- Module.php
|   Classe principal. Registra o diretorio de views e injeta
|   o item "Migracao de Usuarios" no submenu de Usuarios.
|
|-- install.sh
|   Script Bash de instalacao. Copia arquivos e ajusta permissoes.
|
|-- actions/
|   |-- CControllerUserMigrateView.php
|   |   Carrega a lista de usuarios para popular os selects da UI.
|   |
|   |-- CControllerUserMigratePreview.php
|   |   Consulta todas as entidades vinculadas ao usuario de origem
|   |   e retorna o JSON do preview. Nao altera nenhum dado.
|   |
|   `-- CControllerUserMigrateExecute.php
|       Executa a migracao dentro de DBbegin/DBcommit/DBrollback.
|       Trata duplicatas em grupos, permissoes e preferencias.
|       Verifica dinamicamente tabelas de modulos customizados.
|
`-- views/
    |-- usermigrate.view.php
    |   Interface HTML com os dois selects, area de preview
    |   expansivel por categoria e barra de confirmacao.
    |
    `-- usermigrate.view.js
        Logica de interacao: habilita botoes, faz chamadas AJAX
        para preview e execute, renderiza resultados e trata erros.
```

---

## Seguranca

- **Escopo de permissao:** apenas usuarios com perfil Super Admin podem acessar
  as actions do modulo. Qualquer outro perfil recebe HTTP 403.
- **Preview sem efeito colateral:** a action `usermigrate.preview` e somente leitura.
  Nenhum dado e alterado ate a confirmacao explcita.
- **Transacao atomica:** a execucao usa `DBbegin/DBcommit/DBrollback`. Se qualquer
  operacao falhar, todas as alteracoes sao revertidas automaticamente.
- **Protecao contra duplicatas:** grupos e permissoes ja existentes no usuario
  destino sao detectados e ignorados antes de qualquer INSERT.
- **Tabelas de modulos customizados:** verificadas com `INFORMATION_SCHEMA` antes
  de qualquer acesso. A ausencia das tabelas nao gera erro.

---

## Autor

Rafael Leao -- [@leaoereno](https://github.com/leaoereno)
