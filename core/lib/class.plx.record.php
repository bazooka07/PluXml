<?php

/**
 * Classe plxRecord responsable du parcourt des enregistrements
 *
 * @package PLX
 * @author	Anthony GUÉRIN et Florent MONTHEL
 **/
class plxRecord {

	public $size = false; # Nombre d'elements dans le tableau $result
	public $i = -1; # Position dans le tableau $result
	public $result = array(); # Tableau multidimensionnel associatif

	/**
	 * Constructeur qui initialise les variables de classe
	 *
	 * @param	array	tableau associatif des résultats à traiter
	 * @return	null
	 * @author	Anthony GUÉRIN et Florent MONTHEL
	 **/
	public function __construct(&$array) {

		# On initialise les variables de classe
		$this->result = &$array;
		$this->i = -1; # initialisation de la boucle
		$this->size = sizeof($this->result);
	}

	/**
	 * Méthode qui incrémente judicieusement la variable $i
	 *
	 * @return	booléen
	 * @author	Anthony GUÉRIN
	 **/
	public function loop() {

		if($this->i < $this->size-1) { # Tant que l'on est pas en fin de tableau
			$this->i++;
			return true;
		}
		# On sort par une valeur negative
		$this->i = -1;
		return false;
	}

	/**
	 * Méthode qui récupère la valeur du champ $field
	 * correspondant à la position courante
	 *
	 * @param	field	clef du tableau à retourner
	 * @return	string ou false
	 * @author	Anthony GUÉRIN et Florent MONTHEL
	 **/
	public function f($field) {

		if($this->i < 0) # Compteur négatif
			$this->i = 0;
 		# On controle que le champ demande existe bien
		return (array_key_exists($field, $this->result[ $this->i ])) ? $this->result[ $this->i ][ $field ] : false;
	}

	/**
	 * Méthode qui retourne la date du dernier article ou commentaire.
	 * @param	champ contenant la date à scanner
	 * @return	date au format string
	 * @author	J.P. Pourrez
	 * */
	public function lastUpdate($field) {
		if(empty($this->result) or !array_key_exists($field, $this->result[0])) {
			return date('YmdHis');
		}

		$lastUpdate = '19700101000000';
		$iMax=count($this->result);
		for($i=0; $i<$iMax; $i++) {
			$value = $this->result[$i][$field];
			if(strcmp($lastUpdate, $value) < 0) {
				$lastUpdate = $value;
			}
		}
		return $lastUpdate;
	}

}
?>
