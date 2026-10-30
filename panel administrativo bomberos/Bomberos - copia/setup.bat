@echo off
title Configuracion del Sistema Administrativo
color 0A

echo ====================================================
echo  SISTEMA ADMINISTRATIVO - CUERPO DE BOMBEROS
echo ====================================================
echo.
echo Este script configurara el sistema para que puedas
echo acceder al panel administrativo.
echo.
echo Credenciales que se crearan:
echo   Usuario: admin123456789
echo   Contraseña: 123456789Yo
echo.
echo Presiona cualquier tecla para continuar...
pause > nul

echo.
echo [1/3] Ejecutando configuracion rapida...
php quick_setup.php

echo.
echo [2/3] Ejecutando diagnosticos...
php diagnostic.php

echo.
echo [3/3] Probando API...
php test_api.php

echo.
echo ====================================================
echo  CONFIGURACION COMPLETADA
echo ====================================================
echo.
echo Acceso al sistema:
echo   1. Abre tu navegador web
echo   2. Navega a: admin.html
echo   3. Usuario: admin123456789
echo   4. Contraseña: 123456789Yo
echo.
echo Si tienes problemas, revisa los mensajes de error
echo arriba y asegurate de que MySQL este corriendo.
echo.
echo Presiona cualquier tecla para salir...
pause > nul