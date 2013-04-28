<?php
require_once('../SBClientSDK/SBApp.php');

class AvInt extends SBApp
{
	protected $sbUserSBCode;

    // Métodos protegidos
    protected function onError($errorType_)
    {
		error_log($errorType_);
    }

    protected function onNewVote(SBUser $user_,$newVote_,$oldRating_,$newRating_)
    {
		$this->replyOrFalse("Gracias por votarme con ".$newVote_." estrella(s) :)");
    }	

    protected function onNewContactSubscription(SBUser $user_)
    {
		if(($userName = $user_->getSBUserNameOrFalse()))
		{
			$texto = "¡Hola ".$userName."! ¡Bienvenid@ a AvInt!\n".
					 "El juego de aventuras donde tú eres el protagonista.\n\n".
					 "Escribe 'menu' para ver la lista de aventuras disponibles...";

			$this->replyOrFalse(utf8_encode($texto));		
		}	
    }

    protected function onNewContactUnSubscription(SBUser $user_)
	{
		if(($sbUserSBCode = $user_->getSBUserSBCodeOrFalse())) {

			$this->sbUserSBCode = $sbUserSBCode;

			// Compruebo si el jugador tiene alguna partida a medio, para finalizar...
			if($res = $this->partidaActual($this->sbUserSBCode)) {
				if($res != -1) {
					$this->terminaPartida();
				}
			}

		}

    	if(($userName = $user_->getSBUserNameOrFalse()))
		{
	    	error_log($userName." se ha ido...");
		}
    }
    
    protected function onNewMessage(SBMessage $msg_)
	{
		if(($sbUser = $msg_->getSBMessageFromUserOrFalse())) {
			if(($sbUserSBCode = $sbUser->getSBUserSBCodeOrFalse())) {
				$this->sbUserSBCode = $sbUserSBCode;
			}
		}

		if(($messageText = $msg_->getSBMessageTextOrFalse())) {
			$this->procesaEntrada($messageText);			
		}
	}

	/*****************************************************************************
	 * procesaEntrada
	 * 	@Def.: Este procedimiento procesa el comando que ha escrito el usuario.
	 * 	@Param:
	 * 		- $comando_: Comando introducido por el usuario.
	 * 	@Return: N/A
	 * **************************************************************************/
	private function procesaEntrada($comando_)
	{
		$salida = False;
		$comando = strtolower($comando_);
		switch($comando) {
		case 'menu':
			// Muestra el menú principal	
			$salida = $this->muestraMenu();
			break;

		case 'ayuda':
			// Muestra la ayuda
			$salida = $this->muestraAyuda();
			break;

		case 'iniciar 1':
			// Compruebo que el usuario no tiene alguna partida a medio...
			$res = $this->partidaActual($this->sbUserSBCode);

			switch ($res) {
			case 1:
				$salida = "Ya tienes una partida a medio en la aventura '".$this->tituloAventura($res)."'.\n".
			 		   	  "Sólo se puede jugar una partida simultáneamente.\n\n".
			 	   	      "[ Para continuar con la partida, escribe 'continuar' ]\n".
						  "[ Para terminar la partida, escribe 'fin' ]";
			break;

			case -1:
				$aventura = substr($comando,8,1);
				if( $res = $this->iniciaPartida($aventura) ) {
					if( !($res = $this->continuaPartida()) ) {
						$this->terminaPartida();
					}
				}
				$salida = $res;
				break;

			default:
				$salida = $res;
			}
			break;
				
		case 'partida':
			// Compruebo si el usuario tiene alguna partida a medio...
			$res = $this->partidaActual($this->sbUserSBCode);

			switch ($res) {
			case -1:
				$salida = "No tienes ninguna partida en curso.";
				break;
			case 0 :
				$salida = $res;
				break;
			default:
				$salida = "Actualmente estás jugando en la aventura: '".$this->tituloAventura($res)."'.\n\n".
					 	  "[ Para continuar con la partida, escribe 'continuar' ]\n".
						  "[ Para terminar la partida, escribe 'fin' ]";
			}
			break;

		case 'continuar':
			// Compruebo si el usuario tiene alguna partida a medio...
			$res = $this->partidaActual($this->sbUserSBCode);

			switch ($res) {
			case -1 :
				$salida = "No tienes ninguna partida en curso. No hay nada que continuar.";
				break;
			case 0 :
				$salida = $res;
				break;
			default :
				// Muestra el nodo actual
				$salida = $this->continuaPartida();
			}
			break;

		case 'avanzar 1':
		case 'avanzar 2':
			// Compruebo si el usuario tiene alguna partida a medio...
			$res = $this->partidaActual($this->sbUserSBCode);

			switch ($res) {
			case -1 :
				$salida = "No tienes ninguna partida en curso. No hay nada en lo que avanzar.";
				break;
			case 0 :
				$salida = $res;
				break;
			default :
				// Avanza de nodo y lo muestra
				$salida = $this->avanzaPartida(substr($comando,8,1));
				break;
			}	
			break;

		case 'fin':
			// Compruebo si el usuario tiene alguna partida a medio...
			$res = $this->partidaActual($this->sbUserSBCode);

			switch ($res) {
			case -1 :
				$salida = "No tienes ninguna partida en curso. No hay nada que finalizar.";
				break;
			case 0 :
				$salida = $res;
				break;
			default :
				if( ($res = $this->terminaPartida()) ) {
					$salida = "Partida finalizada correctamente.";	
				} else {
					$salida = $res;
				}
			}
			break;

		default :
			$salida = "Comando incorrecto, escribe 'ayuda' para ver los comandos disponibles...";
		}

		if($salida) {
			$this->replyOrFalse(utf8_encode($salida));
		} else {
			$this->replyOrFalse(utf8_encode("Ha ocurrido algún error. Inténtalo más adelante."));
		}
	}

	/*****************************************************************************
	 * muestraMenu
	 * 	@Def.: Esta función devuelve el texto del menú principal.
	 * 	@Param: N/A
	 * 	@Return: 
	 * 		- Cadena con el texto del menú principal, si todo ha dido bien.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function muestraMenu()
	{
		$texto = "En estos momentos no hay aventuras disponibles.";

		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}
			
		// Hago la consulta...
		$query = "SELECT * FROM Aventuras";

		if($result = $mysqli->query($query)) {

			if(($num_rows = $result->num_rows) != 0) {			
				$texto = "Aventuras disponibles: \n\n";

				$contador = 0;
				while($row = $result->fetch_assoc()) {
					$contador++;
					$texto .= "1.- ".$row["titulo"].".\n";
					if($contador == $num_rows) {
						$texto .= "\n";
					}
				}
				$texto .= "[ Escribe 'iniciar [n]' siendo [n] el número correspondiente a la aventura elegida. ]\n";
			}
			$result->close();
		}
		$mysqli->close();

		return $texto;
	}

	/*****************************************************************************
	 * muestraAyuda
	 * 	@Def.: Esta función devuelve el texto de la ayuda.
	 * 	@Param: N/A
	 * 	@Return: 
	 * 		- Cadena con el texto de la ayuda, si todo ha dido bien.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function muestraAyuda()
	{
		$texto = "COMANDOS DISPONIBLES\n\n".
				 "menu -> Muestra el menu de aventuras.\n".
				 "ayuda -> Muestra esta ayuda.\n".
				 "iniciar [n] -> Inicia partida de la aventura [n].\n".
				 "partida -> Muestra la aventura de la partida en curso.\n".
				 "continuar -> Continua la partida en curso.\n".
				 "avanzar [n] -> Avanza por la opción [n] en la partida.\n".
				 "fin -> Termina la partida actual.\n".
				 "créditos -> Muestra la información de los créditos de la aplicación.";
		return $texto;
	}

	/*****************************************************************************
	 * iniciaPartida
	 * 	@Def.: Esta función comienza una partida nueva.
	 * 	@Param: 
	 * 		- $aventura_: Número de la aventura a iniciar.
	 * 	@Return: 
	 * 		- 'True', si se ha iniciado la partida correctamente.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function iniciaPartida($aventura_)
	{
		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Inicializo los datos de la nueva partida...
		$query = "INSERT INTO Partidas VALUES ('{$this->sbUserSBCode}',{$aventura_},1,NOW(),NOW())";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$mysqli->close();
		return True;
	}

	/*****************************************************************************
	 * partidaActual
	 * 	@Def.: Esta función comprueba cuál es la partida actual de un jugador.
	 * 	@Param: 
	 * 		- $sbUserSBCode_: Id del jugador.
	 * 	@Return: 
	 * 		- '[id_aventura]', si hay alguna partida en curso.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function partidaActual($sbUserSBCode_)
	{
		$res = -1;

		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Hago la consulta...
		$query = "SELECT * FROM Partidas WHERE id_jugador = '{$sbUserSBCode_}'";

		if(!($result = $mysqli->query($query)))	{

			$mysqli->close();
			return False;
		}

		if($result->num_rows != 0) {
			$row = $result->fetch_assoc();
			$res = $row["aventura"];
		} 
		$result->close(); 	
		$mysqli->close();

		return $res;
	}

	/*****************************************************************************
	 * tituloAventura
	 * 	@Def.: Esta función devuelve el título de una aventura dada.
	 * 	@Param: 
	 * 		- $id_aventura_: Id de la aventura.
	 * 	@Return: 
	 * 		- '[nombre]', si existe la aventura con id dada.
	 * 		- 'False' si ha habido algún error.
	 * **************************************************************************/
	private function tituloAventura($id_aventura_)
	{
		$res = "";
		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Hago la consulta...
		$query = "SELECT * FROM Aventuras WHERE id_aventura = {$id_aventura_}";

		if(!($result = $mysqli->query($query))) {
			$mysqli->close();
			return False;
		}

		if($result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$res = $row["titulo"];
		}
		$result->close();
		$mysqli->close();
		
		return $res;
	}

	/*****************************************************************************
	 * terminaPartida
	 * 	@Def.: Esta función finaliza la partida en curso de un jugador.
	 * 	@Param: N/A.
	 * 	@Return: 
	 * 		-'True', si se ha finalizado la partida correctamente.
	 * 		-'False', si ha habido algún error.
	 * **************************************************************************/
	private function terminaPartida()
	{
		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Borro los datos de partida...
		$query = "DELETE FROM Partidas WHERE id_jugador = '{$this->sbUserSBCode}'";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$mysqli->close();

		return True;
	}

	/*****************************************************************************
	 * continuaPartida
	 * 	@Def.: Esta función devuelve el texto asociado al nodo actual de la 
	 * 			partida en curso.
	 * 	@Param: N/A.
	 * 	@Return: 
	 * 		-El texto asociado al nodo actual, si todo ha ido bien.
	 * 		-'False', si ha habido algún error.
	 * **************************************************************************/
	private function continuaPartida()
	{
		$texto = False;
		if($nodoActual = $this->nodoActual()) {
			$idNodo = $nodoActual["id_nodo"];
			$idAventura = $nodoActual["id_aventura"];
			$texto = $this->muestraNodo($idNodo,$idAventura);
		}
		return $texto;
	}

	/*****************************************************************************
	 * avanzaPartida
	 * 	@Def.: Esta función actualiza el nodo actual y lo muestra.
	 * 	@Param:
	 * 		- $opcion_: Opción elegida.
	 * 	@Return: 
	 * 		- El texto asociado al nuevo nodo actual, si todo ha ido bien.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function avanzaPartida($opcion_)
	{
		$resul = 0;

		$res = $this->esNodoHoja();
		switch( $res ) {
		case 0:
			if( !($nodoActual = $this->nodoActual()) ) {
				return False;
			}

			$idNodoAnt = $nodoActual["id_nodo"];
			if($opcion_ == 1) {
				$idNodoHijo = $nodoActual["opcion_1"];
			}
			else if($opcion_ == 2) {
				$idNodoHijo = $nodoActual["opcion_2"];
			} else {
				return False;
			}

			if( !($this->actualizaNodoActual($idNodoHijo)) ) {
				return False;
			}
			if( !($texto = $this->continuaPartida()) ) {
				$this->actualizaNodoActual($idNodoAnt);
			}
			$resul = $texto;
			break;
		
		case 1:
			$resul = "Tu aventura ha llegado a su final.\n\n".
					 "[ Escribe 'fin' para terminar la partida. ]";
		}
		return $resul;
	}
	
	/* Funciones para gestionar los nodos */

	/*****************************************************************************
	 * nodoActual
	 * 	@Def.: Esta función devuelve el el nodo actual en la partida en curso.
	 * 	@Param: N/A.
	 * 	@Return: 
	 * 		- 'Nodo actual', si todo ha ido bien.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function nodoActual()
	{
		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Busco el nodo actual...
		$query = "SELECT * FROM Partidas WHERE id_jugador = '{$this->sbUserSBCode}'";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$row = $result->fetch_assoc();
		$result->close();

		$nodo = $row["nodo"];
		$aventura = $row["aventura"];

		// Extraigo su información del Itineraio
		$query = "SELECT * FROM Itinerario WHERE id_nodo = {$nodo} and id_aventura = {$aventura}";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$row = $result->fetch_assoc();
		$result->close();

		$mysqli->close();

		return $row;
	}

	/*****************************************************************************
	 * muestraNodo
	 * 	@Def.: Esta función devuelve el texto asociado al nodo dado.
	 * 	@Param:
	 * 		- $idNodo_: Id del nodo a mostrar.
	 * 		- $aventura_: Aventura a la que pertenece el nodo.
	 * 	@Return: 
	 * 		-El texto asociado al nodo, si todo ha ido bien.
	 * 		-'False', si ha habido algún error.
	 * **************************************************************************/
	private function muestraNodo($idNodo_, $aventura_)
	{
		$fiche = 'http://agarrido83server.zz.mu/AvInt/'.$aventura_.'/n'.$idNodo_.'.txt';

		if(!($canal = fopen($fiche, "r"))) {
			return False;
		}
		
		if(substr(($control = fgets($canal)),0,3) != '###') {
			return False;
		}

		$texto = "";
		while ($linea = fgets($canal)) {
			$texto .= $linea;
		}
		fclose($canal);

		return $texto;
	}

	/*****************************************************************************
	 * actualizaNodoActual
	 * 	@Def.: Esta función actualiza el nodo actual de la partida en curso.
	 * 	@Param:
	 * 		- $idNuevoNodoActual_: Opción elegida.
	 * 	@Return: 
	 * 		- 'True', si todo ha ido bien.
	 * 		- 'False', si ha habido algún error.
	 * **************************************************************************/
	private function actualizaNodoActual($idNuevoNodoActual_)
	{
		// Hago la conexión a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Hago la consulta...
		$query = "UPDATE Partidas SET nodo = {$idNuevoNodoActual_}, fecha_actu = NOW() ".
			     "WHERE id_jugador = '{$this->sbUserSBCode}'";

		if(!($result = $mysqli->query($query))) {
		
			$this->replyOrFalse("k");

			$mysqli->close();
			return False;

		}
		$mysqli->close();

		return True;
	}

	/*****************************************************************************
	 * esNodoHoja
	 * 	@Def.: Esta función comprueba si el nodo actual es un nodo hoja.
	 * 	@Param: N/A.
	 * 	@Return: 
	 * 		- 'True', si es un nodo hoja.
	 * 		- 'False', si no es un nodo hoja.
	 * 		- '-1', si ha habido algún fallo.
	 * **************************************************************************/
	private function esNodoHoja() {
		if( $nodoActual = $this->nodoActual() ) {
			$res = (($nodoActual["opcion_1"] == -1) and ($nodoActual["opcion_2"] == -1));
		} else {
			$res = -1;
		}
		return $res;
	}
}

// Create a new SBApp on dev.spotbros.com and copy-paste your SBCode and key
$avIntSBCode = "TO0E5GG";
$avIntKey = "4dc5654198775177bf702f4e467e9b4ea70f6dc462d08b5b95d25839ea933fc2";
$avInt=new AvInt($avIntSBCode,$avIntKey);
$avInt->serveRequest($_GET["params"]);
?>
