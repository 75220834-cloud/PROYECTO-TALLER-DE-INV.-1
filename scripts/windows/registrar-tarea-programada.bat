@echo off
REM ==================================================================
REM Registra la tarea programada del sistema de incidencias.
REM ------------------------------------------------------------------
REM Una sola tarea de Windows que cada minuto ejecuta el programador del
REM sistema. Ese programador se encarga de todo lo demas:
REM   - indexar los documentos que lee la IA (cada minuto)
REM   - marcar borradores abandonados (cada hora)
REM   - recalcular indicadores y riesgo (de madrugada)
REM
REM Uso: clic derecho sobre este archivo > Ejecutar como administrador.
REM Para ver la orden sin registrar nada:  registrar-tarea-programada.bat /prueba
REM
REM Sin bloques entre parentesis a proposito: si la ruta de la carpeta
REM tiene un parentesis, cmd.exe cerraria el bloque antes de tiempo.
REM ==================================================================
setlocal

REM Carpeta del sistema: dos niveles arriba de este script.
for %%I in ("%~dp0..\..") do set "RAIZ=%%~fI"
set "PHP=C:\xampp\php\php.exe"
set "TAREA=Incidencias - programador"

if not exist "%PHP%" goto sin_php
if not exist "%RAIZ%\artisan" goto sin_sistema

if /I "%~1"=="/prueba" goto prueba

REM Sin permisos de administrador, schtasks no puede crear una tarea que
REM corra aunque nadie haya iniciado sesion.
net session >nul 2>&1
if errorlevel 1 goto sin_admin

schtasks /Create /TN "%TAREA%" /TR "\"%PHP%\" \"%RAIZ%\artisan\" schedule:run" /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /F
if errorlevel 1 goto fallo

echo.
echo Tarea registrada: "%TAREA%"
echo Corre cada minuto, aunque nadie haya iniciado sesion en la PC.
echo Para comprobarla: abre "Programador de tareas" y buscala por su nombre.
pause
exit /b 0

:prueba
echo Carpeta del sistema: "%RAIZ%"
echo Se ejecutaria:
echo schtasks /Create /TN "%TAREA%" /TR "\"%PHP%\" \"%RAIZ%\artisan\" schedule:run" /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /F
exit /b 0

:sin_php
echo No encuentro PHP en "%PHP%".
echo Si XAMPP esta en otra carpeta, corrige la linea "set PHP=" de este archivo.
pause
exit /b 1

:sin_sistema
echo No encuentro el sistema en "%RAIZ%".
pause
exit /b 1

:sin_admin
echo Este archivo tiene que abrirse con clic derecho, Ejecutar como administrador.
pause
exit /b 1

:fallo
echo No se pudo registrar la tarea.
pause
exit /b 1
