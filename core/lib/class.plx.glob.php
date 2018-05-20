<?php

/**
 * Classe plxGlob responsable de la récupération des fichiers à traiter
 *
 * @package PLX
 * @author	Anthony GUÉRIN, Florent MONTHEL, Amaury Graillat et Stéphane F.
 **/
class plxGlob {

	const DATE_ART_PATTERN = '@^\d{4}\.[^.]*\.\d{3}\.(\d{12}})@';
	public $count = 0; # Le nombre de resultats
	public $aFiles = array(); # Tableau des fichiers

	private $dir = false; # Repertoire a checker
	private $onlyfilename = false; # Booleen indiquant si notre resultat sera relatif ou absolu
	private $rep = false; # Boolean pour ne lister que les dossiers

	private static $instance = array();

	/**
	 * Constructeur qui initialise les variables de classe
	 *
	 * @param	dir				repertoire à lire
	 * @param	rep				boolean pour ne prendre que les répertoires sans les fichiers
	 * @param	onlyfilename	boolean pour ne récupérer que le nom des fichiers sans le chemin
	 * @param	type			type de fichier lus (arts ou '')
	 * @return	null
	 * @author	Anthony GUÉRIN, Florent MONTHEL, Amaury Graillat et Stephane F
	 **/
	private function __construct($dir,$rep=false,$onlyfilename=true,$type='') {

		# On initialise les variables de classe
		$this->dir = $dir;
		$this->rep = $rep;
		$this->onlyfilename = $onlyfilename;
		$this->initCache($type);
	}

	/**
	 * Méthode qui se charger de créer le Singleton plxGlob
	 *
	 * @param	dir				répertoire à lire
	 * @param	rep				boolean pour ne prendre que les répertoires sans les fichiers
	 * @param	onlyfilename	boolean pour ne récupérer que le nom des fichiers sans le chemin
	 * @param	type			type de fichier lus (arts ou '')
	 * @return	objet			return une instance de la classe plxGlob
	 * @author	Stephane F
	 **/
	public static function getInstance($dir,$rep=false,$onlyfilename=true,$type=''){
		$basename = str_replace(PLX_ROOT, '', $dir);
		if (!isset(self::$instance[$basename]))
			self::$instance[$basename] = new plxGlob($dir,$rep,$onlyfilename,$type);
		return self::$instance[$basename];
	}

	/**
	 * Méthode qui se charger de mémoriser le contenu d'un dossier
	 *
	 * @param	type			type de fichier lus (arts ou '')
	 * @return	null
	 * @author	Amaury Graillat et Stephane F
	 **/
	private function initCache($type='') {

		if(is_dir($this->dir)) {
			# On ouvre le repertoire
			if($dh = opendir($this->dir)) {
				# Récupération du dirname
				if($this->onlyfilename) # On recupere uniquement le nom du fichier
					$dirname = '';
				else # On concatene egalement le nom du repertoire
					$dirname = $this->dir;
				# Pour chaque entree du repertoire
				while(false !== ($file = readdir($dh))) {
					if($file[0]!='.') {
						$dir = is_dir($this->dir.'/'.$file);
						if($this->rep AND $dir) {
							$this->aFiles[] = $dirname.$file;
						}
						elseif(!$this->rep AND !$dir) {
							if($type=='arts') {
								$index = str_replace('_','',substr($file, 0,strpos($file,'.')));
								if(is_numeric($index)) {
									$this->aFiles[$index] = $file;
								}
							} else {
								$this->aFiles[] = $file;
							}
						}
					}
				}
				# On ferme la ressource sur le repertoire
				closedir($dh);
			}
		}
	}

	/**
	 * Méthode qui cherche les fichiers correspondants au motif $motif
	 *
	 * @param	motif			motif de recherche des fichiers sous forme d'expression réguliere
	 * @param	type			type de recherche: article ('art'), commentaire ('com') ou autre (''))
	 * @param	tri				type de tri (sort, rsort, alpha, ralpha)
	 * @param	publi			recherche des fichiers avant ou après la date du jour
	 * @return	array ou false
	 * @author	Anthony GUÉRIN, Florent MONTHEL et Stephane F
	 **/
	private function search($motif,$type,$tri,$publi) {

		$array=array();
		$this->count = 0;
		$allPubs = ($publi === 'all');

		if($this->aFiles) {

			# Pour chaque entree du repertoire
			$nowEpoc = time();
			$nowStr = date('YmdHi');
			foreach ($this->aFiles as $file) {

				if(preg_match($motif,$file)) {

					switch($type) {
						case 'art':
							# On decoupe le nom du fichier
							list($artId, $cats, $author, $datePub, $title, $ext) = explode('.', $file, 6);
							# On cree un tableau associatif en choisissant bien nos cles et en verifiant la date de publication
							$key = ($tri === 'alpha' OR $tri === 'ralpha') ? $title.'~'.$artId : $datePub.$artId;
							if(
								$allPubs OR
								($publi === 'before' AND $datePub <= $nowStr) OR
								($publi === 'after'  AND $datePub >= $nowStr)
							) {
	 							$array[$key] = $file;
								$this->count++; # On incremente le compteur
							}
							break;
						case 'com':
							# On decoupe le nom du fichier
							$index = explode('.',$file);
							# On cree un tableau associatif en choisissant bien nos cles et en verifiant la date de publication
							if(
								$allPubs OR
								($publi === 'before' AND $index[1] <= $nowEpoc) OR
								($publi === 'after' AND $index[1] >= $nowEpoc)
							) {
								$array[ $index[1].$index[0] ] = $file;
								$this->count++; # On incremente le compteur
							}
							break;
						default: # Aucun tri
							$array[] = $file;
							# On incremente le compteur
							$this->count++;
					}
				}
			}
		}

		# On retourne le tableau si celui-ci existe
		if($this->count > 0)
			return $array;
		else
			return false;
	}

	/**
	 * Méthode qui retourne un tableau trié, des fichiers correspondants
	 * au motif $motif, respectant les différentes limites
	 *
	 * @param	motif			motif de recherche des fichiers sous forme d'expression régulière
	 * @param	type			type de recherche: article ('art'), commentaire ('com') ou autre (''))
	 * @param	tri				type de tri (sort, rsort, alpha, random)
	 * @param	depart			indice de départ de la sélection
	 * @param	limite			nombre d'éléments à sélectionner
	 * @param	publi			recherche des fichiers avant ou après la date du jour
	 * @param	pinCount		Nombre d'articles épinglés à afficher
	 * @return	array ou false
	 * @author	Anthony GUÉRIN, Florent MONTHEL et Jean-Pierre Pourrez (aka bazooka07)
	 **/
	public function query($motif,$type='',$tri='',$depart='0',$limite=false,$publi='all',$pinCount=0) {

		# Si on a des résultats
		if($rs = $this->search($motif,$type,$tri,$publi)) {

			if(!empty($tri) and $type === 'art' and $pinCount != 0) {
				# On sépare tous les articles épinglés
				$arts = array_filter(
					$rs,
					function($key) {
						return preg_match('@^\d{4}\.(?:home,|draft,|\d{3},)*pin(?:,\d{3})*\.@', $key);
					}
				);
				if(!empty($arts)) {
					foreach(array_keys($arts) as $key) {
						unset($rs[$key]);
					}
					if(count($arts) > 1) {
						# Tri des articles épinglés
						uasort($arts, function($key1, $key2) {
							if(
								preg_match(self::DATE_ART_PATTERN, $key1, $matches1) and
								preg_match(self::DATE_ART_PATTERN, $key2, $matches2)
							) {
								return strcmp($matches1[1], $matches2[1]);
							} else {
								return 0;
							}
						});
						# On garde la quantité demandée
						if($pinCount > 0) {
							$arts = array_slice($arts, 0, $pinCount);
						}
					}
				}
			}

			# Ordre de tri du tableau
			if ($type != '') {
				switch ($tri) {
					case 'random':
						shuffle($rs);
						break;
					case 'alpha':
					case 'asc':
					case 'sort':
						ksort($rs);
						break;
					case 'ralpha':
					case 'desc':
					case 'rsort':
						krsort($rs);
						break;
					default:
				}
			} else {
				switch ($tri) {
					case 'random':
						shuffle($rs);
						break;
					case 'alpha':
					case 'sort':
						sort($rs);
						break;
					case 'ralpha':
					case 'rsort':
						rsort($rs);
						break;
					default:
				}
			}

			# On enlève les clés du tableau
			if(!empty($arts)) {
				$rs = array_merge(array_values($arts), array_values($rs));
			} else {
				$rs = array_values($rs);
			}
			# On a une limite, on coupe le tableau
			if($limite)
				$rs = array_slice($rs,$depart,$limite);
			# On retourne le tableau
			return $rs;
		}
		# On retourne une valeur négative
		return false;
	}

}
?>
