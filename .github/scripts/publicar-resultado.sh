#!/usr/bin/env bash
#
# Publica el resultado de las pruebas en dos sitios, y por dos motivos
# distintos:
#
#   - El RESUMEN de la ejecucion es comodo de leer, pero solo lo ve quien ha
#     iniciado sesion en GitHub.
#   - Las ANOTACIONES las expone la API publica, asi que un fallo se puede
#     diagnosticar sin credenciales del repositorio. Descargar los logs de
#     Actions exige permisos de administrador.
#
# Vive en un archivo y no dentro del YAML a proposito: un bloque de shell
# incrustado en YAML se rompe con cada comilla y cada barra invertida, y el
# error no aparece hasta que la ejecucion ya fallo.

set -uo pipefail

SALIDA="${1:-salida.txt}"

if [ ! -f "$SALIDA" ]; then
  echo "No hay salida que publicar: $SALIDA no existe."
  exit 0
fi

# Sin codigos de color: en el resumen se ven como basura.
LIMPIA="$(mktemp)"
sed 's/\x1b\[[0-9;]*m//g' "$SALIDA" > "$LIMPIA"

{
  echo "## Resultado de las pruebas"
  echo
  echo '```'
  tail -n 150 "$LIMPIA"
  echo '```'
} >> "${GITHUB_STEP_SUMMARY:-/dev/null}"

# Las anotaciones solo tienen sentido cuando algo fallo. Si todo paso, basta
# con la linea de resumen: llenar la pestaña de anotaciones en cada ejecucion
# correcta hace que nadie las mire cuando de verdad importan.
if grep -q "FAILED\|Tests:.*failed\|Fatal error\|SQLSTATE" "$LIMPIA"; then
  tail -n 60 "$LIMPIA" | grep -v '^[[:space:]]*$' | while IFS= read -r linea; do
    # El % es el caracter de escape de los comandos de flujo de trabajo.
    echo "::error title=Pruebas::${linea//%/%25}"
  done
else
  grep -m1 "Tests:" "$LIMPIA" || true
fi

rm -f "$LIMPIA"
