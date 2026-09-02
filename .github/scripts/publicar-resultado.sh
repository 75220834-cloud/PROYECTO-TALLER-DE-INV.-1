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

# La linea de totales, siempre.
grep -m1 "Tests:" "$LIMPIA" || true

if ! grep -q "FAILED\|Fatal error\|SQLSTATE" "$LIMPIA"; then
  rm -f "$LIMPIA"
  exit 0
fi

# UNA sola anotacion con todo el bloque del fallo.
#
# GitHub solo conserva diez anotaciones por paso: emitir una por linea
# hacia que el detalle util —el diff de la asercion— se perdiera detras de
# las primeras diez lineas, que son las que menos dicen.
BLOQUE="$(grep -n "FAILED" "$LIMPIA" | head -1 | cut -d: -f1)"

if [ -n "$BLOQUE" ]; then
  DESDE=$(( BLOQUE > 25 ? BLOQUE - 25 : 1 ))
  DETALLE="$(sed -n "${DESDE},$(( BLOQUE + 35 ))p" "$LIMPIA")"
else
  DETALLE="$(tail -n 60 "$LIMPIA")"
fi

# %25, %0D y %0A son los escapes que exigen los comandos de flujo de trabajo:
# sin ellos, un salto de linea corta la anotacion por la mitad.
DETALLE="${DETALLE//'%'/%25}"
DETALLE="${DETALLE//$'\r'/%0D}"
DETALLE="${DETALLE//$'\n'/%0A}"

echo "::error title=Detalle del fallo::${DETALLE}"

rm -f "$LIMPIA"
