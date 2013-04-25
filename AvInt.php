<?php
require_once('../SBClientSDK/SBApp.php');

class AvInt extends SBApp
{
	protected $sbUserSBCode;

    // M�todos protegidos
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
			$texto = "�Hola ".$userName."! �Bienvenid@ a AvInt!\n".
					 "El juego de aventuras donde t� eres el protagonista.\n\n".
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
		$comando = strtolower($comando_);
		switch($comando) {
			case 'menu':
				// Muestra el men� principal	
				$res = $this->muestraMenu();
				break;

			case 'ayuda':
				// Muestra la ayuda
				$res = $comando;
				break;

			case 'iniciar 1':
				// Compruebo que el usuario no tiene alguna partida a medio...
				if($res = $this->partidaActual($this->sbUserSBCode)) {
					if($res != -1) {
						$res = "Ya tienes una partida a medio en la aventura '".$this->tituloAventura($res)."'.\n".
					 		   "S�lo se puede jugar una partida simult�neamente.\n\n".
					 	   	   "Para continuar con la partida, escribe 'continuar'.\n".
					 	       "Para terminar la partida, escribe 'fin'.";
					} else {
						$aventura = substr($comando,8,1);
						if( $res = $this->iniciaPartida($aventura) ) {
							// Muestro el nodo 1
							// $res = $this->continuaPartida();
						}
					}
				}
				break;

			case 'partida':
				// Compruebo si el usuario tiene alguna partida a medio...
				if($res = $this->partidaActual($this->sbUserSBCode)) {
					if($res != -1) {
						$res = "Actualmente est�s jugando en la aventura: '".$this->tituloAventura($res)."'.";
					} else {
						$res = "No tienes ninguna partida en curso.";
					}
				}
				break;

			case 'continuar':
				// Compruebo si el usuario tiene alguna partida a medio...
				if($res = $this->partidaActual($this->sbUserSBCode)) {
					if($res == -1) {
						$res = "No tienes ninguna partida en curso. No hay nada que continuar.";
					} else {
						// Muestro el nodo actual
						//$res = $this->continuaPartida();
						$res = $comando;
					}
				}
				break;

			case '1':
				// Salta a la opci�n 1 del cap�tulo actual
				$res = $comando;
				break;
				
			case '2':
				// Salta a la opci�n 2 del cap�tulo actual
				$res = $comando;
				break;

			case 'fin':
				// Compruebo si el usuario tiene alguna partida a medio...
				if($res = $this->partidaActual($this->sbUserSBCode)) {
					if($res == -1) {
						$res = "No tienes ninguna partida en curso por finalizar.";
					} else {
						if( $res = $this->terminaPartida() ) {
							$res = "Partida finalizada correctamente.";	
						}
					}
				}
				break;

			default :
				$res = "Comando incorrecto, escriba 'ayuda' para ver los comandos disponibles...";
		}
		if($res) {
			$this->replyOrFalse(utf8_encode($res));
		} else {
			$this->replyOrFalse(utf8_encode("Ha ocurrido alg�n error. Int�ntelo m�s adelante."));
		}
	}

	/*****************************************************************************
	 * muestraMenu
	 * 	@Def.: Esta funci�n devuelve el texto del men� principal.
	 * 	@Param: N/A
	 * 	@Return: 
	 * 		- Cadena con el texto del men� principal, si todo ha dido bien.
	 * 		- 'False', si ha habido alg�n error.
	 * **************************************************************************/
	private function muestraMenu()
	{
		$texto = False;

		// Hago la conexi�n a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}
			
		// Hago la consulta...
		$query = "SELECT * FROM Aventuras";

		if($result = $mysqli->query($query)) {

			if(($num_rows = $result->num_rows) == 0) {
				$texto = "En estos momentos no hay aventuras disponibles.";
			} else {	
				$texto = "Aventuras disponibles: \n\n";

				$contador = 0;
				while($row = $result->fetch_assoc()) {
					$contador++;
					$texto .= "1.- ".$row["titulo"].".\n";
					if($contador == $num_rows) {
						$texto .= "\n";
					}
				}
				$texto .= "Escriba 'iniciar' seguido del n�mero correspondiente para comenzar la aventura...\n".
				  		  "(Ejemplo: 'iniciar 1')";
			}
			$result->close();
		}
		$mysqli->close();

		return $texto;
	}

	/*****************************************************************************
	 * iniciaPartida
	 * 	@Def.: Esta funci�n comienza una partida nueva.
	 * 	@Param: 
	 * 		- $aventura_: N�mero de la aventura a iniciar.
	 * 	@Return: 
	 * 		- 'True', si se ha iniciado la partida correctamente.
	 * 		- 'False', si ha habido alg�n error.
	 * **************************************************************************/
	private function iniciaPartida($aventura_)
	{
		// Hago la conexi�n a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Inicializo los datos de la nueva partida...
		$query = "INSERT INTO Partidas VALUES ('{$this->sbUserSBCode}','{$aventura_}',1,NOW(),NOW())";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$mysqli->close();
		return True;
	}

	/*****************************************************************************
	 * partidaActual
	 * 	@Def.: Esta funci�n comprueba cu�l es la partida actual de un jugador.
	 * 	@Param: 
	 * 		- $sbUserSBCode_: Id del jugador.
	 * 	@Return: 
	 * 		- '[id_aventura]', si hay alguna partida en curso.
	 * 		- '-1' si no hay ninguna partida en curso.
	 * 		- 'False', si ha habido alg�n error.
	 * **************************************************************************/
	private function partidaActual($sbUserSBCode_)
	{
		// Hago la conexi�n a la base de datos
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
		} else {
			$res = -1;
		}
		$result->close(); 	
		$mysqli->close();

		return $res;
	}

	/*****************************************************************************
	 * tituloAventura
	 * 	@Def.: Esta funci�n devuelve el t�tulo de una aventura dada.
	 * 	@Param: 
	 * 		- $id_aventura_: Id de la aventura.
	 * 	@Return: 
	 * 		- '[nombre]', si existe la aventura con id dada.
	 * 		-' "" ' si no exista la partida con id dada.
	 * 		-'False' si ha habido alg�n error.
	 * **************************************************************************/
	private function tituloAventura($id_aventura_)
	{
		// Hago la conexi�n a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Hago la consulta...
		$query = "SELECT * FROM Aventuras WHERE id_aventura = '{$id_aventura_}'";

		if(!($result = $mysqli->query($query))) {
			$mysqli->close();
			return False;
		}

		if($result->num_rows == 0) {
			$res = "";
		} else {
			$row = $result->fetch_assoc();
			$res = $row["titulo"];
		}
		$result->close();
		$mysqli->close();
		
		return $res;
	}

	/*****************************************************************************
	 * terminaPartida
	 * 	@Def.: Esta funci�n finaliza la partida en curso de un jugador.
	 * 	@Param: N/A.
	 * 	@Return: 
	 * 		-'True', si se ha finalizado la partida correctamente.
	 * 		-'False', si ha habido alg�n error.
	 * **************************************************************************/
	private function terminaPartida()
	{
		// Hago la conexi�n a la base de datos
		$mysqli = new mysqli('mysql.hostinger.es','u414170863_avent','agarrido83','u414170863_aventura');
		if($mysqli->connect_error) {
			return False;
		}

		// Inicializo los datos de la nueva partida...
		$query = "DELETE FROM Partidas WHERE id_jugador = '{$this->sbUserSBCode}'";

		if(!($result = $mysqli->query($query))) {

			$mysqli->close();
			return False;

		}
		$mysqli->close();

		return True;
	}
}

// Create a new SBApp on dev.spotbros.com and copy-paste your SBCode and key
$avIntSBCode = "TO0E5GG";
$avIntKey = "4dc5654198775177bf702f4e467e9b4ea70f6dc462d08b5b95d25839ea933fc2";
$avInt=new AvInt($avIntSBCode,$avIntKey);
$avInt->serveRequest($_GET["params"]);
?>
