#!/bin/bash
# zbx-user-migrate — install.sh

set -e

MODULE_ID="zbx-user-migrate"
ZABBIX_MODULES_DIR="/usr/share/zabbix/modules"
ZABBIX_WEB_USER="apache"
MODULE_SRC_DIR="$(cd "$(dirname "$0")" && pwd)"

if [ ! -d "$ZABBIX_MODULES_DIR" ]; then
    echo "[ERRO] Diretório não encontrado: $ZABBIX_MODULES_DIR"
    exit 1
fi

DEST="$ZABBIX_MODULES_DIR/$MODULE_ID"

echo "→ Copiando módulo para $DEST ..."
rm -rf "$DEST"
cp -r "$MODULE_SRC_DIR" "$DEST"

echo "→ Ajustando permissões ..."
chown -R "$ZABBIX_WEB_USER:$ZABBIX_WEB_USER" "$DEST"
find "$DEST" -type f -exec chmod 644 {} \;
find "$DEST" -type d -exec chmod 755 {} \;

echo ""
echo "✔  Módulo instalado em: $DEST"
echo ""
echo "Próximos passos:"
echo "  1. Acesse o Zabbix: Administração > Geral > Módulos"
echo "  2. Clique em 'Verificar módulos ausentes'"
echo "  3. Habilite o módulo 'User Migration'"
echo "  4. Acesse Usuários > Migração de Usuários"
echo ""
