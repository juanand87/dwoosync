-- Script para eliminar campos redundantes de la tabla subscribers
-- Ejecutar en la base de datos discogs_api

USE discogs_api;

-- Eliminar campo subscription_date (redundante con created_at)
ALTER TABLE subscribers DROP COLUMN subscription_date;

-- Eliminar campo expiration_date (redundante con licenses.expires_at)
ALTER TABLE subscribers DROP COLUMN expiration_date;

-- Verificar que los campos se eliminaron correctamente
DESCRIBE subscribers;
