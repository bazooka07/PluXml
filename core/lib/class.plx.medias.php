<?php

/**
 * Classe plxMedias regroupant les fonctions pour gérer la librairie des medias
 *
 * @package PLX
 * @author	Stephane F
 **/
class plxMedias {

	 // http://www.iana.org/assignments/media-types/media-types.xhtml
	const IMG_MIMETYPES = '@^image/(?:jpe?g|png|gif)$@i';
	const COMMON_MIMETYPES = '@^(?:image|audio|video|text)/@i';
	const APP_MIMETYPES = '@^application/(?:'.
		'pdf|'.
		'vnd.openxmlformats|'.
		'vnd.oasis.opendocument|'.
		'vnd.ms-|'.
		'zip|'.
		'gzip|'.
		'rar|'.
		'x-gtar|'.
		'x-tar|'.
		'x-xcf|'.
		'epub+zip|'.
		'vnd.sun.xml|'.
		'x-shockwave-flash|'.
		'x-7z-compressed'.
		')@i';

	const ICONS_PATH = PLX_CORE.'admin/theme/exts/48/';
	public $path = null; # chemin vers les médias
	public $dir = null;
	public $aDirs = array(); # liste des dossiers et sous dossiers
	public $aFiles = array(); # liste des fichiers d'un dossier
	private $new_sizes = array( # dimensions pour les nouvelles images et vignettes
		'img_new'	=> array('w'=> false, 'h'=>false),
		'thumb_new'	=> array('w'=> false, 'h'=>false)
	);
	/*
	public $maxUpload = array(); # valeur upload_max_filesize
	public $maxPost = array(); # valeur post_max_size
	* */

	/*
	public $thumbQuality = 60; # qualité image
	public $thumbWidth = 60; # largeur des miniatures
	public $thumbHeight = 60; # hauteur des miniatures

	public $img_exts = '/\.(jpe?g|png|gif|bmp)$/i';
	public $doc_exts = '/\.(7z|aiff|asf|avi|csv|docx?|epub|fla|flv|gz|gzip|m4a|m4v|mid|mov|mp3|mp4|mpc|mpe?g|ods|odt|odp|ogg|pdf|pptx?|ppt|pxd|qt|ram|rar|rm|rmi|rmvb|rtf|svg|swf|sxc|sxw|tar|tgz|txt|vtt|wav|webm|wma|wmv|xcf|xlsx?|zip)$/i';
	* */

	/**
	 * Constructeur qui initialise la variable de classe
	 *
	 * @param	path	répertoire racine des médias
	 * @param	dir		dossier de recherche
	 * @return	null
	 * @author	Stephane F
	 **/
	public function __construct($path, $dir, $sort='title_asc') {

		# Initialisation
		$this->path = $path;
		$this->dir = $dir;
		$this->sort = $sort;

		# Création du dossier réservé à l'utilisateur connecté s'il n'existe pas
		if(!is_dir($this->path)) {
			if(!mkdir($this->path,0755))
				return plxMsg::Error(L_PLXMEDIAS_MEDIAS_FOLDER_ERR);
		}
		# Création du dossier réservé aux miniatures
		if(!is_dir($this->path.'.thumbs/'.$this->dir)) {
			mkdir($this->path.'.thumbs/'.$this->dir,0755,true);
		}

		$this->aDirs = $this->_getAllDirs();
		$this->aFiles = $this->_getDirFiles($this->dir);

		/*
		# Taille maxi des fichiers
		$maxUpload = strtoupper(ini_get("upload_max_filesize"));
		$this->maxUpload['display'] = str_replace('M', ' Mo', $maxUpload);
		$this->maxUpload['display'] = str_replace('K', ' Ko', $this->maxUpload['display']);
		if(substr_count($maxUpload, 'K')) $this->maxUpload['value'] = str_replace('K', '', $maxUpload) * 1024;
		elseif(substr_count($maxUpload, 'M')) $this->maxUpload['value'] = str_replace('M', '', $maxUpload) * 1024 * 1024;
		elseif(substr_count($maxUpload, 'G')) $this->maxUpload['value'] = str_replace('G', '', $maxUpload) * 1024 * 1024 * 1024;
		else $this->maxUpload['value'] = 0;

		# Taille maxi des données
		$maxPost = strtoupper(ini_get("post_max_size"));
		$this->maxPost['display'] = str_replace('M', ' Mo', $maxPost);
		$this->maxPost['display'] = str_replace('K', ' Ko', $this->maxPost['display']);
		if(substr_count($maxPost, 'K')) $this->maxPost['value'] = str_replace('K', '', $maxPost) * 1024;
		elseif(substr_count($maxPost, 'M')) $this->maxPost['value'] = str_replace('M', '', $maxPost) * 1024 * 1024;
		elseif(substr_count($maxPost, 'G')) $this->maxPost['value'] = str_replace('G', '', $maxPost) * 1024 * 1024 * 1024;
		else $this->maxPost['value'] = 0;
		*/
	}

	/**
	 * Méthode qui retourne un tableau de tous les dossiers et sous-dossiers d'un répertoire.
	 *
	 * @author	J.P. Pourrez (bazooka07)
	 **/
	private function _getAllDirs() {
		$result = array();
		$pattern = '*/';
		$offset = strlen($this->path);
		for($i=1; $i<10; $i++) {
			$dirs = glob($this->path . str_repeat($pattern, $i), GLOB_ONLYDIR);
			if(empty($dirs)) { break; }
			foreach($dirs as $d) {
				$path = substr($d, $offset);
				$result[] = array(
					'level' => $i,
					/* 'name'	=> '/'.$path, // totalement inutile et jamais utilisé ! */
					'path'	=> $path
				);
			}
		}
		usort($result, function($a, $b) { return strcasecmp($a['path'], $b['path']); });
		return $result;
	}

	/**
	 * Méthode qui retourne la liste des fichiers d'un répertoire
	 *
	 * @param	dir		répertoire de lecture
	 * @return	files	tableau contenant la liste de tous les fichiers d'un dossier
	 * @author	Stephane F
	 **/
	private function _getDirFiles($dir) {

		$src = $this->path.$dir;
		if(!is_dir($src)) return array();

		$offset = strlen($this->path);
		$files = array();
		foreach(array_filter(
			glob($src.'*'),
			function($item) { return !preg_match('@\.tb\.\w+$@', $item); } # On rejette les miniatures
		) as $filename) {
			if(is_dir($filename) or preg_match(('@(?:html?|php)@i'), $filename)) { continue; }

			$thumbInfos = false;
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			$icon = self::ICONS_PATH.'_blank.png';
			if(preg_match('@\.(jpe?g|png|gif)$@i', $filename, $matches)) {
				# Youpi! We catch a picture
				$thumbName = plxUtils::thumbName($filename);
				if(file_exists($thumbName)) {
					$thumbInfos = array(
						'infos' 	=> getimagesize($thumbName),
						'filesize'	=> filesize($thumbName)
					);
				}
				$sample = $this->path. '.thumbs/' .substr($filename, $offset);
				if(
					file_exists($sample) or
					plxUtils::makeThumb(
						$filename,
						$sample
					)
				) {
					$icon = $sample;
				}
				$imgSize = getimagesize($filename);
			} else {
				# No picture
				$imgSize = false;
				$iconFilename = self::ICONS_PATH.rtrim($ext, 'x').'.png'; // rtrim() is a hack against Microsoft
				if(file_exists($iconFilename)) {
					$icon = $iconFilename;
				}
			}
			$stats = stat($filename);
			$name = basename($filename);
			$files[$name] = array(
				'.thumb'	=> $icon,
				'name' 		=> $name,
				'path' 		=> $filename,
				'date' 		=> $stats['mtime'], // integer
				'filesize' 	=> $stats['size'], // integer
				'extension'	=> '.' . $ext,
				'infos' 	=> $imgSize,
				'thumb' 	=> $thumbInfos
			);
		}

		switch($this->sort) {
			case 'title_desc'	: krsort($files); break;
			case 'date_asc'		: uasort($files, function($a, $b) { return ($a['date'] - $b['date']); }); break;
			case 'date_desc'	: uasort($files, function($b, $a) { return ($a['date'] - $b['date']); }); break;
			case 'filesize_asc'	: uasort($files, function($a, $b) { return ($a['filesize'] - $b['filesize']); }); break;
			case 'filesize_desc': uasort($files, function($b, $a) { return ($a['filesize'] - $b['filesize']); }); break;
			default: ksort($files);
		}

		return $files;
	}

	/**
	 * Méthode qui formate l'affichage de la liste déroulante des dossiers
	 *
	 * @return	string	balise select à afficher
	 * @author	J.P. Pourrez (bazooka07)
	 **/
	public function contentFolder() {

		$currentFolder = $this->dir;
		if(!empty($this->aDirs)) {
			$options = array_map(
				function($item) use($currentFolder) {
					$selected = ($item['path'] == $currentFolder) ? ' selected' : '';
					return <<< OPTION
			<option class="level_{$item['level']}" value="${item['path']}"$selected>/${item['path']}</option>
OPTION;
				},
				$this->aDirs
			);
		}

		$selectedRoot = (empty($this->dir)) ? ' selected' : '';
		$caption = L_PLXMEDIAS_ROOT;
		$start = <<< START
		<select class="folder" id="folder" name="folder">
			<option value="."$selectedRoot>($caption)</option>\n
START;
		$stop = <<< STOP
		</select>\n
STOP;
		return $start . ((!empty($options)) ? implode("\n", $options) : '') . $stop;
	}

	/**
	 * Méthode qui supprime un fichier (et sa vignette si elle existe dans le cas d'une image)
	 *
	 * @param	files	liste des fichier à supprimer
	 * @return  boolean	faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function deleteFiles($files) {

		$count = 0;
		foreach($files as $file) {
			# protection pour ne pas supprimer un fichier en dehors de $this->path.$this->dir
			$file=basename($file);
			if(!unlink($this->path.$this->dir.$file)) {
				$count++;
			} else {
				# Suppression de la vignette
				if(is_file($this->path.'.thumbs/'.$this->dir.$file))
					unlink($this->path.'.thumbs/'.$this->dir.$file);
				# Suppression de la miniature
				$thumName = plxUtils::thumbName($file);
				if(is_file($this->path.$this->dir.$thumName))
					unlink($this->path.$this->dir.$thumName);
			}
		}

		if(sizeof($files)==1) {
			if($count==0)
				return plxMsg::Info(L_PLXMEDIAS_DELETE_FILE_SUCCESSFUL);
			else
				return plxMsg::Error(L_PLXMEDIAS_DELETE_FILE_ERR);
		}
		else {
			if($count==0)
				return plxMsg::Info(L_PLXMEDIAS_DELETE_FILES_SUCCESSFUL);
			else
				return plxMsg::Error(L_PLXMEDIAS_DELETE_FILES_ERR);
		}
	}


	/**
	 * Méthode récursive qui supprimes tous les dossiers et les fichiers d'un répertoire
	 *
	 * @param	deldir	répertoire de suppression
	 * @return	boolean	résultat de la suppression
	 * @author	Stephane F
	 **/
	private function _deleteDir($deldir) { #fonction récursive

		if(is_dir($deldir) AND !is_link($deldir)) {
			if($dh = opendir($deldir)) {
				while(FALSE !== ($file = readdir($dh))) {
					if($file != '.' AND $file != '..') {
						$this->_deleteDir($deldir.'/'.$file);
					}
				}
				closedir($dh);
			}
			return rmdir($deldir);
		}
		return unlink($deldir);
	}

	/**
	 * Méthode qui supprime un dossier et son contenu
	 *
	 * @param	deleteDir	répertoire à supprimer
	 * @return  boolean	faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function deleteDir($deldir) {

		# suppression du dossier des miniatures et de son contenu
		$this->_deleteDir($this->path.'.thumbs/'.$deldir);

		# suppression du dossier des images et de son contenu
		if($this->_deleteDir($this->path.$deldir))
			return plxMsg::Info(L_PLXMEDIAS_DEL_FOLDER_SUCCESSFUL);
		else
			return plxMsg::Error(L_PLXMEDIAS_DEL_FOLDER_ERR);
	}

	/**
	 * Méthode qui crée un nouveau dossier
	 *
	 * @param	newdir	nom du répertoire à créer
	 * @return  boolean	faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function newDir($newdir) {

		$newdir = $this->path.$this->dir.$newdir;

		if(!is_dir($newdir)) { # Si le dossier n'existe pas on le créer
			if(!mkdir($newdir,0755))
				return plxMsg::Error(L_PLXMEDIAS_NEW_FOLDER_ERR);
			else
				return plxMsg::Info(L_PLXMEDIAS_NEW_FOLDER_SUCCESSFUL);
		} else {
			return plxMsg::Error(L_PLXMEDIAS_NEW_FOLDER_EXISTS);
		}
	}

	/**
	 * Méthode qui déplace, redimensionne et miniaturise une nouvelle image.
	 * @param	tmp_location	Emplacement temporaire
	 * @param	new_name		nom du nouveau fichier
	 * @author	J.P. Pourrez (bazooka07) 2018-12-05
	 * */
	private function __move_uploaded_image($tmp_location, $new_name) {
		$success = plxUtils::makeThumb($tmp_location, $this->path.'.thumbs/'.$this->dir.$new_name, 48, 48, 75);
		$path1 = $this->path.$this->dir;
		foreach(array('thumb', 'img') as $k) { // travailler d'abord sur la vignette avant de déplacer l'image
			if(!empty($this->new_sizes[$k.'_new']['w']) or !empty($this->new_sizes[$k.'_new']['h'])) {
				$target = ($k == 'img') ? $new_name : plxUtils::thumbName($new_name);
				if(!plxUtils::makeThumb($tmp_location, $path1.$target, $this->new_sizes[$k.'_new']['w'], $this->new_sizes[$k.'_new']['h'])) {
					// impossible de redimensionner une image
					$success = false;
				}
				if($k == 'img') {
					unlink($tmp_location);
				}
			} elseif($k == 'img') {
				// Déplace seulement l'image
				if(!move_uploaded_file($tmp_location, $path1.$new_name)) {
					// Unable to move the new file. We have an error
					$success = false;
				}
			}
		}
		return $success;
	}

	/**
	 * Méthode qui installe un lot de fichiers sur le serveur.
	 *
	 * @param	name 		nom de l'élément du formulaire pour : <input type="file" name="$name" multiple />
	 * @return  msg			résultat de l'installation
	 * @author	J.P. Pourrez (bazooka07) 2018-12-05
	 **/
	public function uploadMultiFiles($name) {
		if(!empty($name) and !empty($_FILES[$name])) {

			// for resizing and thumbnail
			$pattern = '@^[1-9]\d*x[1-9]\d*$@';
			$this->new_sizes = array();
			foreach(array('img_new', 'thumb_new') as $field) {
				$this->new_sizes[$field] = array('w' => false, 'h' => false);
				if(!empty($_POST[$field])) {
					if($_POST[$field] == 'user') {
						foreach(array('w', 'h') as $direction) {
							$k = $field.'_'.$direction;
							if(!empty($_POST[$k])) {
								$this->new_sizes[$field][$direction] = intval($_POST[$k]);
							}
						}
					} elseif(preg_match($pattern, $_POST[$field])) {
						list($w, $h) = explode('x', $_POST[$field]);
						$this->new_sizes[$field]['w'] = intval($w);
						$this->new_sizes[$field]['h'] = intval($h);
					}
				}
			}

			$success = true;
			for($i=0, $iMax=count($_FILES[$name]['name']); $i<$iMax; $i++) {
				if($_FILES[$name]['error'][$i] == 0) {
					if($_FILES[$name]['size'][$i] > 0) {
						$tmp_location = $_FILES[$name]['tmp_name'][$i];
						// basename() may prevent filesystem traversal attacks;
						$tmp_name = basename($_FILES[$name]['name'][$i]); // nom actuel du fichier

						// vérifie si un fichier a déjà le même nom
						if(!file_exists($this->dir.$tmp_name)) {
							$new_name = $tmp_name;
						} else {
							$name = substr($tmp_name, 0, strrpos($tmp_name, '.'));
							$ext = strrchr($tmp_name, '.');
							$i = 1;
							$new_name = "$name.$i$ext";
							while(file_exists($this->dir.$new_name)) {
								$i++;
								$new_name = "$name.$i$ext";
							}
						}

						// check the mimetype
						$mimetype = $_FILES[$name]['type'][$i];
						if(preg_match(self::IMG_MIMETYPES, $mimetype)) {
							// we have an image
							if(!$this->__move_uploaded_image($tmp_location, $new_name)) {
								// Unable to move the new file. We have an error
							}
						} elseif(preg_match(self::COMMON_MIMETYPES, $mimetype) or preg_match(self::APP_MIMETYPES, $mimetype)) {
							if(!move_uploaded_file($tmp_location, $this->path.$this->dir.$new_name)) {
								// Unable to move the new file. We have an error
								plxMsg::Error(L_PLXMEDIAS_UPLOAD_ERR);
								$success = false;
							}
						} else {
							// bad format
							plxMsg::Error(L_PLXMEDIAS_WRONG_FILEFORMAT);
							$success = false;
						}
					} else {
						plxMsg::Error(L_PLXMEDIAS_WRONG_FILESIZE);
						$success = false;
					}
				} else {
					plxMsg::Error(L_PLXMEDIAS_UPLOAD_ERR);
					$success = false;
				}
			}
			if($success) {
				plxMsg::Info((count($_FILES[$name]['name']) > 1) ? L_PLXMEDIAS_UPLOADS_SUCCESSFUL : L_PLXMEDIAS_UPLOAD_SUCCESSFUL);
			}
		}
	}

	/**
	 * Méthode qui déplace une ou plusieurs fichiers
	 *
	 * @param   files		liste des fichier à déplacer
	 * @param	src_dir		répertoire source
	 * @param	dst_dir		répertoire destination
	 * @return  boolean		faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function moveFiles($files, $src_dir, $dst_dir) {

		if($dst_dir=='.') $dst_dir='';

		$count = 0;
		foreach($files as $file) {
			# protection pour ne pas déplacer un fichier en dehors de $this->path.$this->dir
			$file=basename($file);

			# Déplacement du fichier
			if(is_readable($this->path.$src_dir.$file)) {
				$result = rename($this->path.$src_dir.$file, $this->path.$dst_dir.$file);
				$count++;
			}
			# Déplacement de la miniature
			$thumbName = plxUtils::thumbName($file);
			if($result AND is_readable($this->path.$src_dir.$thumbName)) {
				$result = rename($this->path.$src_dir.$thumbName, $this->path.$dst_dir.$thumbName);
			}
			# Déplacement de la vignette
			if($result AND is_readable($this->path.'.thumbs/'.$src_dir.$file)) {
				$result = rename($this->path.'.thumbs/'.$src_dir.$file, $this->path.'.thumbs/'.$dst_dir.$file);
			}
		}

		if(sizeof($files)==1) {
			if($count==0)
				return plxMsg::Error(L_PLXMEDIAS_MOVE_FILE_ERR);
			else
				return plxMsg::Info(L_PLXMEDIAS_MOVE_FILE_SUCCESSFUL);
		}
		else {
			if($count==0)
				return plxMsg::Error(L_PLXMEDIAS_MOVE_FILES_ERR);
			else
				return plxMsg::Info(L_PLXMEDIAS_MOVE_FILES_SUCCESSFUL);
		}

	}

	/**
	 * Méthode qui recréer les miniatures
	 *
	 * @param   files		liste des fichier à déplacer
	 * @param	width		largeur des miniatures
	 * @param	height		hauteur des miniatures
	 * @return  boolean		faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function makeThumbs($files, $width, $height) {

		$errors = 0;
		$success = -1; // for one created thumb, equals 0 !
		foreach($files as $file) {
			if(is_file($this->path.$this->dir.$file) and
				preg_match('@\.(?:jpe?g|png|gif)@i', $file)
			) {
				$file=basename($file);
				$thumName = plxUtils::thumbName($file);
				if(!plxUtils::makeThumb($this->path.$this->dir.$file, $this->path.$this->dir.$thumName, $width, $height, 80)) {
					$errors++;
				} else {
					$success++;
				}
			}
		}
		switch($errors) {
			case 0	:
				if($success < 0) { return true; }
				return plxMsg::Info(($success > 0) ? sprintf(L_PLXMEDIAS_RECREATE_THUMBS_SUCCESSFUL, ($success + 1)) : L_PLXMEDIAS_RECREATE_THUMB_SUCCESSFUL);
				break;
			case 1	: return plxMsg::Error(L_PLXMEDIAS_RECREATE_THUMB_ERR); break;
			default	: return plxMsg::Error(sprintf(L_PLXMEDIAS_RECREATE_THUMBS_ERR, $errors));
		}

	}

	/**
	 * Méthode qui renomme un fichier
	 *
	 * @param   oldname		ancien nom
	 * @param	newname		nouveau nom
	 * @return  boolean		faux si erreur sinon vrai
	 * @author	Stephane F
	 **/
	public function renameFile($oldname, $newname) {

		$result = false;

		$dirname = dirname($oldname)."/";
		$filename = basename($oldname);
		$ext = pathinfo($filename, PATHINFO_EXTENSION);

		$newname = trim(basename($newname, pathinfo($newname, PATHINFO_EXTENSION)), '.');
		$newname = $ext!="" ? $newname.'.'.$ext : $newname;

		# Déplacement du fichier
		if(is_readable($oldname) AND is_file($oldname)) {

			# On teste l'existence du nouveau fichier et on formate le nom pour éviter les doublons
			$i = 1;
			$file = $dirname.plxUtils::title2filename($newname);
			$name = substr($newname, 0, strrpos($newname,'.'));
			while(file_exists($file)) {
				$file = $dirname.$name.'.'.$i++.'.'.$ext;
			}

			# changement du nom du fichier
			$result = rename($oldname, $file);

			# changement du nom de la miniature
			$old_thumbName = plxUtils::thumbName($oldname);
			if($result AND is_readable($old_thumbName)) {
				$new_thumbName = plxUtils::thumbName($file);
				$result = rename($old_thumbName, $new_thumbName);
			}

			# changement du nom de la vignette
			$path = str_replace($this->path, $this->path.'.thumbs/', $dirname);
			$old_thumbName = $path.$filename;
			if($result AND is_readable($old_thumbName)) {
				$new_thumbName = $path.basename($file);
				$result = rename($old_thumbName, $new_thumbName);
			}

		}

		if($result)
			return plxMsg::Info(L_RENAME_FILE_SUCCESSFUL);
		else
			return plxMsg::Error(L_RENAME_FILE_ERR);

	}

	public function iconExts($implode=false) {
		$files = array_map(function($item) {
				return basename($item, '.png');
			},
			glob(self::ICONS_PATH.'*.png')
		);
		return (!empty($implode)) ? implode('|', $files) : $files;
	}
}
?>
