<?php

/*
 * ************************************************************************* SARA Copyright (c) 2013 UNIVERSIDAD DISTRITAL Francisco José de Caldas **************************************************************************
 */

// IMPORTANTE
// Cada base de datos MYSQL que este registrada en el sistema debe tener un nombre de usuario diferente
// Se recomienda que se manejen diferentes perfiles por cada subsistema
class Mysql extends ConectorDB {
    
    // Fin del método obtener_enlace
    
    /**
     *
     * @name conectar_db
     * @return void
     * @access public
     */
    function conectar_db() {
        
        $this->enlace = @mysqli_connect ( $this->servidor, $this->usuario, $this->clave );

        if ($this->enlace) {
            
            $base = mysqli_select_db ( $this->enlace, $this->db );
            if ($base) {
                return $this->enlace;
            } else {
                $this->error = mysqli_errno ();
            }
        } else {
            
            $this->error = 'Unable to connect to Database';
        }
    
    }
    
    // Fin del método conectar_db
    
    /**
     *
     * @name probar_conexion
     * @return void
     * @access public
     */
    function probar_conexion() {
        
        return $this->enlace;
    }
    
    /**
     *
     * @name desconectar_db
     * @param
     *            resource enlace
     * @return void
     * @access public
     */
    function desconectar_db() {
        
        mysqli_close ( $this->enlace );
    
    }
    
    // Fin del método desconectar_db
    
    // Funcion para el acceso a las bases de datos
    function ejecutarAcceso($cadena, $tipo = "", $numeroRegistros = 0) {
        
        if (! is_object ( $this->enlace )) {
            error_log ( "NO HAY ACCESO A LA BASE DE DATOS!!!" );
            return "error";
        }
        
        $cadena = $this->tratarCadena ( $cadena );
        //Begin LOG
        if ($tipo === 'busqueda' || $tipo === 'acceso' || $tipo === ''){
        	//No LOG
        } else if (strpos($tipo, '_nolog') !== false){
        	$tipo = str_replace('_nolog','',$tipo);
		} else {
        	$this->registar_log($tipo);
        }
		//End LOG
        if ($tipo == "busqueda") {
            return $this->ejecutar_busqueda ( $cadena, $numeroRegistros );
        } else {
            return $this->ejecutar_acceso_db ( $cadena );
        }
    
    }
	
	/**
	 * La función que registra las variables de acceso en un log
	 */
    function registar_log($tipo){    	
		require_once ('core/log/logger.class.php');
    	$this->logger = new \logger ();//Se agrega para log
    	$registro = $_REQUEST;
		$registro['opcion'] = $tipo;
		$this->logger->log_usuario($registro);
    }
    
    /**
     *
     * @name obtener_error
     * @param
     *            string cadena_sql
     * @param
     *            string conexion_id
     * @return boolean
     * @access public
     */
    function obtener_error() {
        
        return $this->error;
    
    }
    
    // Fin del método obtener_error
    
    /**
     *
     * @name registro_db
     * @param
     *            string cadena_sql
     * @param
     *            int numero
     * @return boolean
     * @access public
     */
    function registro_db($cadena, $numeroRegistros = 0) {
        
        if (! is_object ( $this->enlace )) {
            error_log ( "NO HAY ACCESO A LA BASE DE DATOS!!!" );
            return NULL;
        }
        
        $busqueda = $this->enlace->query ( $cadena );
        
        if ($busqueda) {
            
            return $this->procesarResultado ( $busqueda, $numeroRegistros );
        
        } else {
            unset ( $this->registro );
            $this->error = mysqli_error ( $this->enlace );
            return 0;
        }
    
    }
    
    // Fin del método registro_db
    
 private function procesarResultado($busqueda, $numeroRegistros=0) {
        unset ( $this->registro );
        $this->campo = $busqueda->field_count;
        $this->conteo = $busqueda->num_rows;
        
        if ($numeroRegistros == 0) {
            
            $numeroRegistros = $this->conteo;
        }
        /**
         * Obtener el nombre de las columnas
         */
        $salida = $busqueda->fetch_array ( MYSQLI_BOTH );
        
        if ($salida) {
            $this->keys = array_keys ( $salida );
            $i = 0;
            foreach ( $this->keys as $clave => $valor ) {
                if (is_string ( $valor )) {
                    $this->claves [$i] = $valor;
                    $i ++;
                }
            }
            for($unCampo = 0; $unCampo < $this->campo; $unCampo ++) { 
                $this->registro [0] [$unCampo] = $salida [$unCampo];
                $this->registro [0] [$this->claves [$unCampo]] = $salida [$unCampo];
            }
        }
        
        for($j = 1; $j < $numeroRegistros; $j ++) {
            
            $salida = $busqueda->fetch_array ( MYSQLI_BOTH );
            
            for($unCampo = 0; $unCampo < $this->campo; $unCampo ++) {
                $this->registro [$j] [$unCampo] = $salida [$unCampo];
                $this->registro [$j] [$this->claves [$unCampo]] = $salida [$unCampo];
            }
        }
        $busqueda->free ();
        return $this->conteo;
    }
    
    function obtenerCadenaListadoTablas($variable) {
        
        return "SHOW TABLES FROM " . $variable;
    
    }
    
    // Fin del método obtener_conteo_db
    function ultimo_insertado($unEnlace = "") {
        
        if ($unEnlace != "") {
            return mysqli_insert_id ( $unEnlace );
        } else {
            return mysqli_insert_id ( $this->enlace );
        }
    
    }
    
    /**
     *
     * @name transaccion
     * @return boolean resultado
     * @access public
     */
    function transaccion($clausulas) {
        
        $acceso = true;
        
        /* Desactivar el autocommit */
        mysqli_autocommit ( $this->enlace, FALSE );
        $this->instrucciones = count ( $clausulas );
        for($contador = 0; $contador < $this->instrucciones; $contador ++) {
            $acceso &= $this->ejecutar_acceso_db ( $clausulas [$contador] );
        }
        
        if ($acceso) {
            $resultado = mysqli_commit ( $this->enlace );
        } else {
            mysqli_rollback ( $this->enlace );
            $resultado = false;
        }
        /* Activar el autocommit */
        
        mysqli_autocommit ( $this->enlace, TRUE );
        
        return $resultado;
    
    }
    
    // Fin del método transaccion
    
    function limpiarVariables($variables) {
        
        if (is_array ( $variables )) {
            foreach ( $variables as $key => $value ) {
                $variables [$key] = mysqli_real_escape_string ( $value );
            }
        } else {
            $variables = mysqli_real_escape_string ( $variables );
        }
        
        return $variables;
    
    }
    
    /**
     *
     * @name db_admin
     *      
     */
    function __construct($registro) {
        
        if (is_string ( $registro )) {
            $registro = array_map ( 'trim', $registro );
        }
        
        $this->servidor = $registro ["dbdns"];
        $this->db = $registro ["dbnombre"];
        $this->usuario = $registro ["dbusuario"];
        $this->clave = $registro ["dbclave"];
        $this->dbsys = $registro ["dbsys"];
        $this->enlace = $this->conectar_db ();
    
    }
    
    // Fin del método db_admin
    
    private function ejecutar_busqueda($cadena, $numeroRegistros = 0) {
        
        $this->registro_db ( $cadena, $numeroRegistros );
        return $this->getRegistroDb ();
    
    }
    
    /**
     *
     * @name ejecutar_acceso_db
     * @param
     *            string cadena_sql
     * @param
     *            string conexion_id
     * @return boolean
     * @access private
     */
    private function ejecutar_acceso_db($cadena) {
        
        if (! $this->enlace->query ( $cadena )) {
            $this->error = $this->enlace->errno;
            return false;
        } else {
            return true;
        }
    
    }
    function vaciar_temporales($datosConfiguracion, $sesion) {
        
        $this->esta_sesion = $sesion;
        $this->cadena_sql = "DELETE ";
        $this->cadena_sql .= "FROM ";
        $this->cadena_sql .= $datosConfiguracion ["prefijo"] . "registrado_borrador ";
        $this->cadena_sql .= "WHERE ";
        $this->cadena_sql .= "identificador<" . (time () - 3600);
        $this->ejecutar_acceso_db ( $this->cadena_sql );
    
    }
    
    function tratarCadena($cadena) {
        
        return str_replace ( "<AUTOINCREMENT>", "NULL", $cadena );
    
    }

}

// Fin de la clase db_admin
?>
