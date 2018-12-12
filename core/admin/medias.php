<?php

/**
 * Gestion des médias
 *
 * @package PLX
 * @author  Stephane F, J.P. Pourrez (2018-11)
 **/

include __DIR__ .'/prepend.php';

# Control du token du formulaire
plxToken::validateFormToken($_POST);

# Sécurisation du chemin du dossier
if(isset($_POST['folder']) AND $_POST['folder']!='.' AND !plxUtils::checkSource($_POST['folder'])) {
	$_POST['folder']='.';
}

# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminMediasPrepend'));

# Recherche du type de medias à afficher via la session
if(empty($_SESSION['medias'])) {
	$_SESSION['medias'] = $plxAdmin->aConf['medias'];
	$_SESSION['folder'] = '';
}
elseif(!empty($_POST['folder'])) {
	$_SESSION['currentfolder']= (isset($_SESSION['folder'])?$_SESSION['folder']:'');
	$_SESSION['folder'] = ($_POST['folder']=='.'?'':$_POST['folder']);
}

# Tri de l'affichage des fichiers
if(!empty($_POST['sort']) AND preg_match('@^(?:title|date|filesize)_(?:a|de)sc$@', $_POST['sort'])) {
	$sort = $_POST['sort'];
} else {
	$sort = (!empty($_SESSION['sort_medias'])) ? $_SESSION['sort_medias'] : 'title_asc';
}

# on précise l'ordre de tri des medias
$sort_title = 'title_desc';
$sort_date = 'date_desc';
$sort_filesize = 'filesize_desc';
switch ($sort) {
	case 'title_asc'		: $sort_title = 'title_desc'; break;
	case 'date_asc'			: $sort_date = 'date_desc'; break;
	case 'date_desc'		: $sort_date = 'date_asc'; break;
	case 'filesize_asc'		: $sort_filesize = 'filesize_desc'; break;
	case 'filesize_desc'	: $sort_filesize = 'filesize_asc'; break;
	default					: $sort_title = 'title_asc';
}
$_SESSION['sort_medias'] = $sort;

# Nouvel objet de type plxMedias
$plxMediasRoot = PLX_ROOT.$_SESSION['medias'];
if($plxAdmin->aConf['userfolders'] AND $_SESSION['profil'] >= PROFIL_WRITER) {
	$plxMediasRoot .= $_SESSION['user'].'/';
}
$plxMedias = new plxMedias($plxMediasRoot, $_SESSION['folder'], $sort);

/* ----------- Début de traitement du $_POST[...] ------------- */
if(!empty($_POST['btn_newfolder']) AND !empty($_POST['newfolder'])) {
	$newdir = plxUtils::title2filename(trim($_POST['newfolder']));
	if($plxMedias->newDir($newdir)) {
		$_SESSION['folder'] = $_SESSION['folder'].$newdir.'/';
	}
	header('Location: medias.php');
	exit;
}
if(!empty($_POST['btn_renamefile']) AND !empty($_POST['newname'])) {
	$plxMedias->renameFile($_POST['oldname'], $_POST['newname']);
	header('Location: medias.php');
	exit;
}
elseif(!empty($_POST['folder']) AND $_POST['folder']!='.' AND !empty($_POST['btn_delete'])) {
	if($plxMedias->deleteDir($_POST['folder'])) {
		$_SESSION['folder'] = '';
	}
	header('Location: medias.php');
	exit;
}
elseif(!empty($_FILES)) {
	$plxMedias->uploadMultiFiles('selector');
	$_SESSION['sort_medias'] = 'date_desc';
	foreach(array('img', 'thumb') as $col) {
		$name = $col.'_new';
		$_SESSION[$name] = $_POST[$name];
		foreach(array('_w', '_h') as $r) {
			$name_r = $name.$r;
			$_SESSION[$name_r] = $_POST[$name_r];
		}
	}
	header('Location: medias.php');
	exit;
}
elseif(!empty($_POST['selection']) AND !empty($_POST['idFile']) AND !empty($_POST['btn_ok'])) {
	switch($_POST['selection']) {
		case 'delete':
			$plxMedias->deleteFiles($_POST['idFile']);
			header('Location: medias.php');
			exit;
		case 'move':
			$plxMedias->moveFiles($_POST['idFile'], $_SESSION['currentfolder'], $_POST['folder']);
			header('Location: medias.php');
			exit;
		case 'thumbs':
			$plxMedias->makeThumbs($_POST['idFile'], $plxAdmin->aConf['miniatures_l'], $plxAdmin->aConf['miniatures_h']);
			header('Location: medias.php');
			exit;
	}
}
/* ----------- Fin de traitement du $_POST[...] ------------- */

# Contenu des 2 listes déroulantes
$selectionList = array(''=>L_FOR_SELECTION,'move'=>L_PLXMEDIAS_MOVE_FOLDER,'thumbs'=>L_MEDIAS_RECREATE_THUMB,'-'=>'-----','delete' =>L_DELETE_FILE);

# Traitement de la barre de progression de l'envoi du lot de fichiers
const SESSION_UPLOAD_PREFIX = 'session.upload_progress.';
define('SESSION_UPLOAD_ENABLED', (ini_get(SESSION_UPLOAD_PREFIX .'enabled') == 1));

# On inclut le header
include __DIR__ .'/top.php';

$curFolder = '/'.plxUtils::strCheck(basename($_SESSION['medias']).'/'.$_SESSION['folder']);
$curFolders = explode('/', $curFolder);

?>

<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasTop')) # Hook Plugins ?>

<input type="checkbox" class="toggler" id="id_toggle_medias" />

<?php /* --------------- Tableau des medias ------------ */ ?>
<form method="post" id="form_medias">

	<div class="inline-form" id="files_manager">

		<div class="inline-form action-bar">
			<h2><?php echo L_MEDIAS_TITLE ?></h2>
			<p id="fil-ariane-medias">
				<?php
				// fil d'Ariane
				echo L_MEDIAS_DIRECTORY.' : <a href="#" data=".">('.L_PLXMEDIAS_ROOT.')</a> / ';
				if($curFolders) {
					$path='';
					foreach($curFolders as $id => $folder) {
						if(!empty($folder) AND $id>1) {
							$path .= $folder.'/';
							echo '<a href="#" data="'.$path.'">'.$folder.'</a> / ';
						}
					}
				}
				?>
			</p>
			<?php plxUtils::printSelect('selection', $selectionList, '', false, 'no-margin', 'id_selection') ?>
			<input type="submit" name="btn_ok" value="<?php echo L_OK ?>" onclick="return confirmAction(this.form, 'id_selection', 'delete', 'idFile[]', '<?php echo L_CONFIRM_DELETE ?>')" />
			<label for="id_toggle_medias" role="button"><?php echo L_MEDIAS_ADD_FILE ?></label>
			<button id="btnNewFolder"><?php echo L_MEDIAS_NEW_FOLDER ?></button>
<?php if(!empty($_SESSION['folder'])) { ?>
				&nbsp;&nbsp;&nbsp;<input type="submit" name="btn_delete" class="red" value="<?php echo L_DELETE_FOLDER ?>" onclick="return confirm('<?php printf(L_MEDIAS_DELETE_FOLDER_CONFIRM, $curFolder) ?>')" />
<?php } ?>
			<input type="hidden" name="sort" value="" />
			<?php echo plxToken::getTokenPostMethod() ?>
		</div>

		<div class="header">
			<div>
				<?php echo L_MEDIAS_FOLDER ?>
				<?php echo $plxMedias->contentFolder() ?>
				<input type="submit" name="btn_changefolder" value="<?php echo L_OK ?>" />
			</div>
			<div>
				<input type="text" id="medias-search" placeholder="<?php echo L_SEARCH ?>..." title="<?php echo L_SEARCH ?>" />
			</div>
		</div>

		<div class="scrollable-table">
			<table id="medias-table" class="full-width" data-plxroot-offset="<?php echo strlen(PLX_ROOT); ?>" data-i18n='{"copyClp" : "<?php echo L_MEDIAS_LINK_COPYCLP_DONE; ?>"}' data-medias-sort="<?php echo $sort; ?>">
				<thead>
					<tr>
						<th class="checkbox" data-sort-method='none'><input type="checkbox" onclick="return checkAll(this.form, 'idFile[]');" /></th>
						<th data-sort-method='none'>&nbsp;</th>
						<th data-medias-sort="<?php echo $sort_title; ?>"><span><?php echo L_MEDIAS_FILENAME ?></span></th>
						<th><span><?php echo L_MEDIAS_EXTENSION ?></span></th>
						<th data-sort-method='integer' data-medias-sort="<?php echo $sort_filesize; ?>"><span><?php echo L_MEDIAS_FILESIZE ?></span></th>
						<th data-sort-method='none'><?php echo L_MEDIAS_DIMENSIONS ?></th>
						<th data-sort-method='integer' data-medias-sort="<?php echo $sort_date ?>"><span><?php echo L_MEDIAS_DATE ?></span></th>
					</tr>
				</thead>
				<tbody>
<?php
				# Si on a des fichiers
				if($plxMedias->aFiles) {
					$offsetRoot = strlen(PLX_ROOT);
					foreach($plxMedias->aFiles as $name=>$v) { /* ------------------ Boucle sur chaque media ---------------------- */
						$isImage = preg_match('@\.(?:jpe?g|png|gif)$@i', $v['extension']);
						echo '<tr>';
						echo '<td><input type="checkbox" name="idFile[]" value="'.$name.'" /></td>';
						echo '<td class="icon">';
							if(is_file($v['.thumb'])) {
								$extra = ($isImage) ? ' data-img' : '';
								echo '<img src="'.$v['.thumb'].'" title="'.$name.'"'.$extra.' class="thumb" width="48" height="48" />';
							}
						echo '</td>';
						echo '<td data-sort="'. $name .'">'.
							'<div>'.
								'<a class="imglink" href="'.$v['path'].'" target="_blank">'.$name.'</a>'.
								'<i class="icon-media" title="'.L_RENAME_FILE.'" data-rename>&#xe803;</i>'.
								'<i class="icon-media" title="'.L_MEDIAS_LINK_COPYCLP.'">&#xf0ea;</i>'.
								'</div>';
							if($v['thumb'] !== false) {
								$href = plxUtils::thumbName($v['path']);
								echo '<div>'.
									L_MEDIAS_THUMB.' : '.
									'<a href="'.$href.'" target="_blank">'.basename($href).'</a>'.
									'<i class="icon-media" title="'.L_MEDIAS_LINK_COPYCLP.'">&#xf0ea;</i>'.
									'</div>';
							}
						echo '</td>';
						echo '<td>'.substr($v['extension'],1).'</td>';
						echo '<td data-sort="'. $v['filesize'] .'">';
						echo plxUtils::formatFilesize($v['filesize']);
						if($v['thumb'] !== false) {
							echo '<br />'.plxUtils::formatFilesize($v['thumb']['filesize']);
						}
						echo '</td>';
						$dimensions = '&nbsp;';
						if($isImage) {
							if(isset($v['infos']) AND isset($v['infos'][0]) AND isset($v['infos'][1])) {
								$dimensions = $v['infos'][0].' x '.$v['infos'][1];
							}
							if($v['thumb'] !== false) {
								$dimensions .= '<br />'.$v['thumb']['infos'][0].' x '.$v['thumb']['infos'][1];
							}
						}
						echo '<td>'.$dimensions.'</td>';
						echo '<td data-sort="'. $v['date'] .'">'.plxDate::formatDate(plxDate::timestamp2Date($v['date'])).'</td>';
						echo "</tr>\n";
					}
				} else {
					echo '<tr><td colspan="7" class="center">'.L_MEDIAS_NO_FILE.'</td></tr>'."\n";
				}
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

<?php  ?>
<?php
/* ----------- Téléversement d'un lot de fichiers -------- *\
 * for debugging, set at the root of the site, the script variables.php which contains the following line :
 * <?php phpinfo(INFO_VARIABLE); ?>
 * and add in the <form> tag : action="<?php echo PLX_ROOT; ?>variables.php"
 * */
?>
<form method="post" id="form_uploader" class="form_uploader" enctype="multipart/form-data">

	<div id="files_uploader">

		<div class="inline-form action-bar">
			<h2 class="h4"><?php echo L_MEDIAS_TITLE ?></h2>
			<p>
<?php
				echo L_MEDIAS_DIRECTORY.' : ('.L_PLXMEDIAS_ROOT.') / ';
				if($curFolders) {
					$path='';
					foreach($curFolders as $id => $folder) {
						if(!empty($folder) AND $id>1) {
							$path .= $folder.'/';
							echo $folder.' / ';
						}
					}
				}
?>
			</p>
			<label for="id_toggle_medias" role="button">← <?php echo L_MEDIAS_BACK ?></label>
			<input type="submit" name="btn_upload" id="btn_upload" value="<?php echo L_MEDIAS_SUBMIT_FILE ?>" disabled />
			<?php echo plxToken::getTokenPostMethod() ?>
		</div>

		<div id="upload-limits">
<?php
	$uploadLimits = array(
		'max_file_uploads'		=> L_MEDIAS_MAX_UPLOAD_NBFILE,
		'upload_max_filesize'	=> L_MEDIAS_MAX_UPLOAD_FILE,
		'post_max_size'			=> L_MEDIAS_MAX_POST_SIZE
	);
	foreach($uploadLimits as $key=>$caption) {
		$value = ini_get($key);
		$valueStr = strtr($value, array(
			'K'	=> 'Ko',
			'M' => 'Mo',
			'G'	=> 'Go'
		));
		if(preg_match('@\s*(\d+)\s*(K|M|G)$@', $value, $matches)) {
			$units = array(
				'K' => '1024',
				'M'	=> '1048576', // 1024*1024
				'G'	=> '1073741824' // 1024*1024*1024
			);
			$valueInt = intval($matches[1]) * $units[$matches[2]];
		} else {
			$valueInt = intval($value);
		}
		echo <<< ITEM
			<p><span>$caption</span> : <span id="$key" data-value="$valueInt" title="$valueInt bytes">$valueStr</span></p>\n
ITEM;
	}
?>
			<progress id="post-load" value="0" min="0" max="100"></progress>
		</div>

		<div id="upload-area">
<?php
// gestion de la barre de progression de l'envoi du lot de fichiers
if(SESSION_UPLOAD_ENABLED) {
	$session_upload_name = ini_get(SESSION_UPLOAD_PREFIX .'name');
	$session_upload_value = plxUtils::charAleatoire(16);
	$data_session = ' data-session="'.$session_upload_name.'"';
?>
			<input type="hidden" name="<?php echo $session_upload_name; ?>" value="<?php echo $session_upload_value; ?>" />
<?php
}
?>
			<input type="file" id="selector" name="selector[]" multiple accept="image/*, audio/*, application/pdf, text/*, */*"<?php if(!empty($data_session)) { echo $data_session; } ?>/>
			<p><?php echo L_MEDIAS_DROP_CLICK; ?></p>
			<div class="files_list" id="files_list" data-icons-path="<?php echo plxMedias::ICONS_PATH; ?>" data-icon-exts="<?php echo $plxMedias->iconExts('|'); ?>"></div>
<?php
// gestion de la barre de progression de l'envoi du lot de fichiers
if(SESSION_UPLOAD_ENABLED) {
?>
			<progress id="upload-progress" value="0" min="0" max="200"></progress>
<?php
}
?>
		</div>

		<div class="grid">
<?php
// Redimensionnement des images et des thumbnails
$captions = array('img' => L_MEDIAS_RESIZE, 'thumb' => L_MEDIAS_THUMBS);
$no_resizes = array('img' => L_MEDIAS_RESIZE_NO, 'thumb' => L_MEDIAS_THUMBS_NONE);
foreach(array('img', 'thumb') as $col) {
	$default = $plxAdmin->aConf['thumbs'];
	switch($col) {
		case 'img':
			$choices = array_merge(
				array('no' => L_MEDIAS_RESIZE_NO),
				$img_redim,
				array('conf' => $plxAdmin->aConf['images_l' ].'x'.$plxAdmin->aConf['images_h' ]),
				array('user' => 'user')
			);
			break;
		case 'thumb':
			$choices = array_merge(
				array('no' => L_MEDIAS_THUMBS_NONE),
				$img_redim,
				array('conf' => $plxAdmin->aConf['miniatures_l' ].'x'.$plxAdmin->aConf['miniatures_h' ]),
				array('user' => 'user')
			);
			break;
	}
?>
			<div class="col sma-12 med-4">
				<p><?php echo $captions[$col]; ?>&nbsp;:</p>
				<ul class="unstyled-list">
					<?php
						// echo "\n<!--\n\$choices = "; print_r($choices); echo "-->\n"; // for debugging
						$name = $col.'_new';
						$lastValue = (isset($_SESSION[$name])) ? $_SESSION[$name] : $choices['conf'];
						foreach($choices as $i=>$redim) {
							$value = ($i === 'no') ? '' : $redim;
							$checked = ($value == $lastValue) ? ' checked' : '';
							$id = 'id_'.$col.'_new_'.$i;
							$extra = '<label for="'.$id.'">'.$redim.'</label>';
							if($i === 'conf' and $_SESSION['profil'] < PROFIL_MANAGER) {
								$extra .= '&nbsp;&nbsp;(<a href="parametres_affichage.php">'.L_MEDIAS_MODIFY.'</a>)';
							} elseif($i === 'user') {
								$name_w = $name.'_w';
								$name_h = $name.'_h';
								$w = (!empty($_SESSION[$name_w])) ? $_SESSION[$name_w] : '';
								$h = (!empty($_SESSION[$name_h])) ? $_SESSION[$name_h] : '';
								$extra = <<< EXTRA_USER

						<input type="text" size="2" maxlength="4" name="${name_w}" value="$w" />&nbsp;x&nbsp;
						<input type="text" size="2" maxlength="4" name="${name_h}" value="$h" />\n
EXTRA_USER;
							}
							echo <<< CHOICE
					<li><input type="radio" id="${id}" name="${name}" value="$value"$checked />&nbsp;$extra</li>\n
CHOICE;
						}
					?>
				</ul>
			</div>
<?php
}
?>
		</div>
		<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasUpload')) # Hook Plugins ?>
	</div>

</form>

<?php /* ==== les éléments suivants sont des boites modales ==== */ ?>

<?php /* -------------- New Folder Dialog ------------------ */ ?>
<div id="dlgNewFolder" class="dialog">
	<div class="dialog-content">
		<form method="post">
			<?php echo L_MEDIAS_NEW_FOLDER ?>
			<input id="id_newfolder" type="text" name="newfolder" value="" maxlength="50" size="15" />
			<input type="submit" name="btn_newfolder" value="<?php echo L_MEDIAS_CREATE_FOLDER ?>" />
			<?php echo plxToken::getTokenPostMethod(); ?>
			<span class="dialog-close">🞭</span>
		</form>
	</div>
</div>

<?php /* --------------- Rename File Dialog ---------------- */ ?>
<div id="dlgRenameFile" class="dialog">
	<div class="dialog-content">
		<form method="post">
			<?php echo L_MEDIAS_NEW_NAME ?>
			<input id="id_newname" type="text" name="newname" value="" maxlength="50" size="15" />
			<input id="id_oldname" type="hidden" name="oldname" />
			<input type="submit" name="btn_renamefile" value="<?php echo L_MEDIAS_RENAME ?>" />
			<?php echo plxToken::getTokenPostMethod(); ?>
			<span class="dialog-close">🞭</span>
		</form>
	</div>
</div>

<?php /* ---------------------- Diaporama ------------------ */ ?>
<div class="modal">
	<div class="modal-overlay">
		<div class="modal-box">
			<img src="" />
		</div>
		<button type="button" class="closeBtn">x</button>
		<button type="button" class="prevBtn">◀</button>
		<button type="button" class="nextBtn">▶</button>
		<div class="footer">
			<div class="size" title="<?php echo L_MEDIAS_FILESIZE ?>">&nbsp;</div>
			<div class="filename" title="<?php echo L_MEDIAS_LINK_COPYCLP; ?>" data-root="<?php echo PLX_ROOT;?>">&nbsp;</div>
			<div class="counter">🞭</div>
		</div>
	</div>
</div>

<input id="clipboard-entry" type="text" />

<script type="text/javascript" src="<?php echo PLX_CORE ?>lib/medias.js"></script>

<?php
# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminMediasFoot'));
# On inclut le footer
include __DIR__ .'/foot.php';
?>
