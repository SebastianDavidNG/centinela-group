#!/usr/bin/env bash
#
# Exporta la BD de Docker y reemplaza la URL local por la de producción.
# Uso: ./scripts/export-db-for-production.sh [URL_PRODUCCION]
# Ejemplo: ./scripts/export-db-for-production.sh https://centinelagroup.com
#
# Requiere: docker compose en ejecución (desde la raíz del proyecto).

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

URL_LOCAL="${URL_LOCAL:-http://localhost:8081}"
URL_PROD="${1:-https://centinelagroup.com}"
OUTPUT_RAW="centinela-group-export.sql"
OUTPUT_PROD="centinela-group-production.sql"

echo "→ Exportando base de datos desde Docker..."
docker compose exec -T db mysqldump -u "${WORDPRESS_DB_USER:-wordpress}" -p"${WORDPRESS_DB_PASSWORD:-wordpress}" "${WORDPRESS_DB_NAME:-wordpress}" \
  --single-transaction --routines --triggers > "$OUTPUT_RAW"

echo "→ Reemplazando URLs: $URL_LOCAL → $URL_PROD"
sed "s|${URL_LOCAL}|${URL_PROD}|g" "$OUTPUT_RAW" > "$OUTPUT_PROD"

echo "→ Listo."
echo "  - SQL sin reemplazar: $OUTPUT_RAW"
echo "  - SQL para producción: $OUTPUT_PROD"
echo "  Importa $OUTPUT_PROD en la BD de producción (no subas estos .sql al repo)."
