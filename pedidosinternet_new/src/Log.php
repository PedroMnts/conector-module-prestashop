<?php

namespace PedidosInternet;

/**
 * Clase para el manejo de logs del módulo
 * 
 * Proporciona métodos para registrar eventos, errores, advertencias e información
 * en archivos de log y en la base de datos.
 */
class Log
{
    // Constantes de dirección de log
    public const API = 'API';
    public const PRESTASHOP = 'PRESTASHOP'; 
    
    // Niveles de log
    public const ERROR = 'ERROR';
    public const WARNING = 'WARNING';
    public const INFO = 'INFO';
    public const DEBUG = 'DEBUG';

    private const LOG_FILE_PATH = _PS_MODULE_DIR_ . 'pedidosinternet/logs/';
    private const LOG_PERMISSIONS = 0775; // Permisos: owner(rwx) group(rwx) others(r-x)
    
    public static $lastInsertedId = 0;

    /**
     * Registra un error en base de datos y en archivo
     *
     * @param string $message Mensaje de error
     * @param array $context Contexto adicional
     * @return void
     */
    public static function error(string $message, array $context = []): void 
    {
        self::logToFile(self::ERROR, $message, $context);
        self::logToDatabase(self::ERROR, $message, $context);
    }

    /**
     * Registra una advertencia en base de datos y en archivo
     *
     * @param string $message Mensaje de advertencia
     * @param array $context Contexto adicional
     * @return void
     */
    public static function warning(string $message, array $context = []): void 
    {
        self::logToFile(self::WARNING, $message, $context);
        self::logToDatabase(self::WARNING, $message, $context);
    }

    /**
     * Registra información en base de datos y en archivo
     *
     * @param string $message Mensaje informativo
     * @param array $context Contexto adicional
     * @return void
     */
    public static function info(string $message, array $context = []): void 
    {
        self::logToFile(self::INFO, $message, $context);
        self::logToDatabase(self::INFO, $message, $context);
    }

    /**
     * Registra debug en archivo (no en base de datos)
     *
     * @param string $message Mensaje de depuración
     * @param array $context Contexto adicional
     * @return void
     */
    public static function debug(string $message, array $context = []): void 
    {
        self::logToFile(self::DEBUG, $message, $context);
    }

    /**
     * Asegura que el directorio de logs existe y tiene permisos correctos
     *
     * @return bool True si el directorio está listo para escribir, false en caso contrario
     */
    private static function ensureLogDirectoryExists(): bool 
    {
        $logDir = self::LOG_FILE_PATH;
        
        // Verificar si el directorio no existe
        if (!is_dir($logDir)) {
            // Intentar crear el directorio con permisos adecuados
            $created = @mkdir($logDir, self::LOG_PERMISSIONS, true);
            
            if (!$created) {
                // Intentar obtener información del error
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Error desconocido';
                
                // Registrar el error en el log del sistema (error_log va al log del servidor web)
                error_log("PedidosInternet: No se pudo crear directorio de logs: {$errorMsg}");
                return false;
            }
        } else {
            // El directorio existe, verificar permisos
            if (!is_writable($logDir)) {
                // Intentar cambiar permisos
                $changed = @chmod($logDir, self::LOG_PERMISSIONS);
                
                if (!$changed) {
                    error_log("PedidosInternet: No se pudo modificar permisos del directorio de logs");
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Registra en archivo de log
     *
     * @param string $level Nivel de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    private static function logToFile(string $level, string $message, array $context = []): void 
    {
        if (!self::ensureLogDirectoryExists()) {
            // Si no podemos asegurar el directorio, intentamos usar el directorio temporal del sistema
            $systemTmpDir = sys_get_temp_dir();
            $logDir = rtrim($systemTmpDir, '/') . '/pedidosinternet_logs/';
            
            if (!is_dir($logDir)) {
                mkdir($logDir, self::LOG_PERMISSIONS, true);
            }
        } else {
            $logDir = self::LOG_FILE_PATH;
        }

        try {
            $date = new \DateTimeImmutable();
            $logEntry = [
                'timestamp' => $date->format('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message,
                'context' => $context
            ];

            $filename = $logDir . $date->format('Y-m-d') . '.log';
            
            // Intentar escribir el log
            $success = @file_put_contents(
                $filename, 
                json_encode($logEntry, JSON_PRETTY_PRINT) . PHP_EOL,
                FILE_APPEND
            );
            
            if ($success === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Error desconocido';
                error_log("PedidosInternet: Error al escribir en archivo de log: {$errorMsg}");
            }
        } catch (\Exception $e) {
            error_log("PedidosInternet: Excepción al escribir log: " . $e->getMessage());
        }
    }

    /**
     * Registra en base de datos
     *
     * @param string $level Nivel de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    private static function logToDatabase(string $level, string $message, array $context = []): void 
    {
        $initialTime = new \DateTimeImmutable();
        
        try {
            $db = \Db::getInstance();
            
            // Verificar si la tabla existe antes de intentar insertar
            $tableExists = $db->executeS("SHOW TABLES LIKE '" . _DB_PREFIX_ . "pedidosinternet_logs'");
            
            if (empty($tableExists)) {
                error_log("PedidosInternet: Tabla de logs no existe en la base de datos");
                return;
            }
            
            $inserted = $db->insert(
                "pedidosinternet_logs",
                [
                    'fecha_inicio' => $initialTime->format("Y-m-d H:i:s"),
                    'fecha_fin' => $initialTime->format("Y-m-d H:i:s"),
                    'direccion' => $level,
                    'url' => $message,
                    'contenido' => json_encode($context),
                    'respuesta' => null,
                ]
            );
            
            self::$lastInsertedId = ($inserted ? $db->getLink()->lastInsertId() : 0);
        } catch (\Exception $e) {
            // Si falla el registro en BD, al menos lo guardamos en archivo
            self::logToFile(
                self::ERROR,
                'Failed to log to database: ' . $e->getMessage(),
                ['original_message' => $message, 'original_context' => $context]
            );
        }
    }

	/**
	 * Actualiza una entrada de log existente
	 *
	 * @param int $id ID del log a actualizar
	 * @param array $content Contenido a guardar en la columna 'respuesta'
	 * @return void
	 */
	public static function update(int $id, array $content): void
	{
		if ($id <= 0) {
			self::warning("Intento de actualizar log con ID inválido", [
				'id' => $id
			]);
			return;
		}

		try {
			$db = \Db::getInstance();
			$db->update(
				"pedidosinternet_logs",
				[
					'fecha_fin' => (new \DateTimeImmutable())->format("Y-m-d H:i:s"),
					'respuesta' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
				],
				"id = " . (int)$id
			);
		} catch (\Exception $e) {
			self::logToFile(
				self::ERROR,
				'Failed to update log entry: ' . $e->getMessage(),
				['id' => $id, 'content' => $content]
			);
		}
	}

    /**
     * Limpia los logs de la base de datos y archivos antiguos
     *
     * @param int $daysToKeep Número de días de logs a conservar
     * @return void
     */
    public static function delete(int $daysToKeep = 30): void 
    {
        try {
            // Limpiar logs de la base de datos
            \Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'pedidosinternet_logs`');
            
            // Verificar si el directorio existe antes de buscar archivos
            if (!is_dir(self::LOG_FILE_PATH)) {
                return;
            }
            
            // Limpiar archivos de más de N días
            $files = glob(self::LOG_FILE_PATH . '*.log');
            
            if (empty($files)) {
                return;
            }
            
            $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    @unlink($file);
                }
            }
            
            self::info("Logs eliminados correctamente", [
                'days_kept' => $daysToKeep
            ]);
        } catch (\Exception $e) {
            self::logToFile(
                self::ERROR,
                'Failed to delete logs: ' . $e->getMessage(),
                []
            );
        }
    }
}
