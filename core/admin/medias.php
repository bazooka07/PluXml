<?php

/**
 * Gestion des médias
 *
 * @package PLX
 * @author  Stephane F
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
# Nouvel objet de type plxMedias
$plxMediasRoot = PLX_ROOT.$_SESSION['medias'];
if($plxAdmin->aConf['userfolders'] AND $_SESSION['profil']==PROFIL_WRITER)
	$plxMediasRoot .= $_SESSION['user'].'/';
$plxMedias = new plxMedias($plxMediasRoot, $_SESSION['folder']);

#----

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
elseif(!empty($_POST['btn_upload'])) {
	$plxMedias->uploadFiles($_FILES, $_POST);
	header('Location: medias.php');
	exit;
}
elseif(isset($_POST['selection']) AND ((!empty($_POST['btn_ok']) AND $_POST['selection']=='delete')) AND isset($_POST['idFile'])) {
	$plxMedias->deleteFiles($_POST['idFile']);
	header('Location: medias.php');
	exit;
}
elseif(isset($_POST['selection']) AND ((!empty($_POST['btn_ok']) AND $_POST['selection']=='move')) AND isset($_POST['idFile'])) {
	$plxMedias->moveFiles($_POST['idFile'], $_SESSION['currentfolder'], $_POST['folder']);
	header('Location: medias.php');
	exit;
}
elseif(isset($_POST['selection']) AND ((!empty($_POST['btn_ok']) AND $_POST['selection']=='thumbs')) AND isset($_POST['idFile'])) {
	$plxMedias->makeThumbs($_POST['idFile'], $plxAdmin->aConf['miniatures_l'], $plxAdmin->aConf['miniatures_h']);
	header('Location: medias.php');
	exit;
}

# Tri de l'affichage des fichiers
if(isset($_POST['sort']) AND !empty($_POST['sort'])) {
	$sort = $_POST['sort'];
} else {
	$sort = isset($_SESSION['sort_medias']) ? $_SESSION['sort_medias'] : 'title_asc';
}

$sort_title = 'title_desc';
$sort_date = 'date_desc';
switch ($sort) {
	case 'title_asc':
		$sort_title = 'title_desc';
		usort($plxMedias->aFiles, create_function('$b, $a', 'return strcmp($a["name"], $b["name"]);'));
		break;
	case 'title_desc':
		$sort_title = 'title_asc';
		usort($plxMedias->aFiles, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
		break;
	case 'date_asc':
		$sort_date = 'date_desc';
		usort($plxMedias->aFiles, create_function('$b, $a', 'return strcmp($a["date"], $b["date"]);'));
		break;
	case 'date_desc':
		$sort_date = 'date_asc';
		usort($plxMedias->aFiles, create_function('$a, $b', 'return strcmp($a["date"], $b["date"]);'));
		break;
}
$_SESSION['sort_medias']=$sort;

# Contenu des 2 listes déroulantes
$selectionList = array(''=>L_FOR_SELECTION,'move'=>L_PLXMEDIAS_MOVE_FOLDER,'thumbs'=>L_MEDIAS_RECREATE_THUMB,'-'=>'-----','delete' =>L_DELETE_FILE);

# On inclut le header
include __DIR__ .'/top.php';

$curFolder = '/'.plxUtils::strCheck(basename($_SESSION['medias']).'/'.$_SESSION['folder']);
$curFolders = explode('/', $curFolder);

?>

<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasTop')) # Hook Plugins ?>

<input type="checkbox" class="toggler" id="id_toggle_medias" />

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
				<?php echo L_MEDIAS_FOLDER ?>&nbsp;:&nbsp;
				<?php echo $plxMedias->contentFolder() ?>
				<input type="submit" name="btn_changefolder" value="<?php echo L_OK ?>" />&nbsp;&nbsp;&nbsp;&nbsp;
			</div>
			<div>
				<input type="text" id="medias-search" placeholder="<?php echo L_SEARCH ?>..." title="<?php echo L_SEARCH ?>" />
			</div>
		</div>

		<div class="scrollable-table">
			<table id="medias-table" class="full-width">
				<thead>
				<tr>
					<th class="checkbox"><input type="checkbox" onclick="checkAll(this.form, 'idFile[]')" /></th>
					<th>&nbsp;</th>
					<th><a href="javascript:void(0)" class="hcolumn" onclick="document.forms[0].sort.value='<?php echo $sort_title ?>';document.forms[0].submit();return true;"><?php echo L_MEDIAS_FILENAME ?></a></th>
					<th><?php echo L_MEDIAS_EXTENSION ?></th>
					<th><?php echo L_MEDIAS_FILESIZE ?></th>
					<th><?php echo L_MEDIAS_DIMENSIONS ?></th>
					<th><a href="javascript:void(0)" class="hcolumn" onclick="document.forms[0].sort.value='<?php echo $sort_date ?>';document.forms[0].submit();return true;"><?php echo L_MEDIAS_DATE ?></a></th>
				</tr>
				</thead>
				<tbody>
<?php
				# Initialisation de l'ordre
				$num = 0;
				# Si on a des fichiers
				if($plxMedias->aFiles) {
					$offsetRoot = strlen(PLX_ROOT);
					foreach($plxMedias->aFiles as $v) { # Pour chaque fichier
						$isImage = preg_match('@\.(?:jpe?g|png|gif)$@i', $v['extension']);
						$ordre = ++$num;
						echo '<tr>';
						echo '<td><input type="checkbox" name="idFile[]" value="'.$v['name'].'" /></td>';
						echo '<td class="icon">';
							if(is_file($v['path']) AND $isImage) {
								echo '<img src="'.$v['.thumb'].'" title="'.plxUtils::strCheck($v['name']).'" data-src="'.$v['path'].'" class="thumb" />';
							}
						echo '</td>';
						echo '<td>'.
							'<div>'.
								'<a class="imglink" href="'.$v['path'].'" target="_blank">'.$v['name'].'</a>'.
								'<i class="icon-media" title="'.L_MEDIAS_LINK_COPYCLP.'">&#xf0ea;</i>'.
								'<i class="icon-media" title="'.L_RENAME_FILE.'" data-rename>&#xe803;</i>'.
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
						echo '<td>';
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
						echo '<td>'.plxDate::formatDate(plxDate::timestamp2Date($v['date'])).'</td>';
						echo '</tr>';
					}
				} else {
					echo '<tr><td colspan="7" class="center">'.L_MEDIAS_NO_FILE.'</td></tr>';
				}
?>
				</tbody>
			</table>
		</div>
	</div>
</form>

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
			<label for="id_toggle_medias" role="button"><?php echo L_MEDIAS_BACK ?></label>
			<input type="submit" name="btn_upload" id="btn_upload" value="<?php echo L_MEDIAS_SUBMIT_FILE ?>" />
			<?php echo plxToken::getTokenPostMethod() ?>
		</div>

		<p>
			<?php echo L_MEDIAS_MAX_UPLOAD_NBFILE ?> : <?php echo ini_get('max_file_uploads') ?>
 		</p>
		<p>
			<?php echo L_MEDIAS_MAX_UPLOAD_FILE ?> : <?php echo $plxMedias->maxUpload['display'] ?>
			<?php if($plxMedias->maxPost['value'] > 0) echo " / ".L_MEDIAS_MAX_POST_SIZE." : ".$plxMedias->maxPost['display']; ?>
		</p>

		<div>
			<input id="selector_0" type="file" multiple="multiple" name="selector_0[]" />
			<div class="files_list" id="files_list" style="margin: 1rem 0 1rem 0;"></div>
		</div>

		<div class="grid">
			<div class="col sma-12 med-4">
				<ul class="unstyled-list">
					<li><?php echo L_MEDIAS_RESIZE ?>&nbsp;:&nbsp;</li>
					<li><input type="radio" checked="checked" name="resize" value="" />&nbsp;<?php echo L_MEDIAS_RESIZE_NO ?></li>
					<?php
						foreach($img_redim as $redim) {
							echo '<li><input type="radio" name="resize" value="'.$redim.'" />&nbsp;'.$redim.'</li>';
						}
					?>
					<li>
						<input type="radio" name="resize" value="<?php echo intval($plxAdmin->aConf['images_l' ]).'x'.intval($plxAdmin->aConf['images_h' ]) ?>" />&nbsp;<?php echo intval($plxAdmin->aConf['images_l' ]).'x'.intval($plxAdmin->aConf['images_h' ]) ?>
						&nbsp;&nbsp;(<a href="parametres_affichage.php"><?php echo L_MEDIAS_MODIFY ?>)</a>
					</li>
					<li>
						<input type="radio" name="resize" value="user" />&nbsp;
						<input type="text" size="2" maxlength="4" name="user_w" />&nbsp;x&nbsp;
						<input type="text" size="2" maxlength="4" name="user_h" />
					</li>
				</ul>
			</div>
			<div class="col sma-12 med-8">
				<ul class="unstyled-list">
					<li><?php echo L_MEDIAS_THUMBS ?>&nbsp;:&nbsp;</li>
					<li>
						<?php $sel = (!$plxAdmin->aConf['thumbs'] ? ' checked="checked"' : '') ?>
						<input<?php echo $sel ?> type="radio" name="thumb" value="" />&nbsp;<?php echo L_MEDIAS_THUMBS_NONE ?>
					</li>
					<?php
						foreach($img_thumb as $thumb) {
							echo '<li><input type="radio" name="thumb" value="'.$thumb.'" />&nbsp;'.$thumb.'</li>';
						}
					?>
					<li>
						<?php $sel = ($plxAdmin->aConf['thumbs'] ? ' checked="checked"' : '') ?>
						<input<?php echo $sel ?> type="radio" name="thumb" value="<?php echo intval($plxAdmin->aConf['miniatures_l' ]).'x'.intval($plxAdmin->aConf['miniatures_h' ]) ?>" />&nbsp;<?php echo intval($plxAdmin->aConf['miniatures_l' ]).'x'.intval($plxAdmin->aConf['miniatures_h' ]) ?>
						&nbsp;&nbsp;(<a href="parametres_affichage.php"><?php echo L_MEDIAS_MODIFY ?>)</a>
					</li>
					<li>
						<input type="radio" name="thumb" value="user" />&nbsp;
						<input type="text" size="2" maxlength="4" name="thumb_w" />&nbsp;x&nbsp;
						<input type="text" size="2" maxlength="4" name="thumb_h" />
					</li>
				</ul>
			</div>
		</div>
		<?php eval($plxAdmin->plxPlugins->callHook('AdminMediasUpload')) # Hook Plugins ?>
	</div>

</form>

<?php /* ---------- les éléments suivants sont des boites modales --------------- */ ?>
<!-- New Folder Dialog -->
<div id="dlgNewFolder" class="dialog">
	<div class="dialog-content">
		<?php echo L_MEDIAS_NEW_FOLDER ?>
		<input id="id_newfolder" type="text" name="newfolder" value="" maxlength="50" size="15" />
		<input type="submit" name="btn_newfolder" value="<?php echo L_MEDIAS_CREATE_FOLDER ?>" />
		<span class="dialog-close">🞭</span>
	</div>
</div>

<!-- Rename File Dialog -->
<div id="dlgRenameFile" class="dialog">
	<div class="dialog-content">
		<?php echo L_MEDIAS_NEW_NAME ?>
		<input id="id_newname" type="text" name="newname" value="" maxlength="50" size="15" />
		<input id="id_oldname" type="hidden" name="oldname" />
		<input type="submit" name="btn_renamefile" value="<?php echo L_MEDIAS_RENAME ?>" />
		<span class="dialog-close">🞭</span>
	</div>
</div>

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

<script>
(function() { // overlay
	'use strict';

	function dialogBox(id) {
		const dlg = document.getElementById(id);
		if(dlg == null) {
			console.log('#' + id + ' element not found in "medias.php" file.');
			return;
		}
		const closeBtn = dlg.querySelector('.dialog-close');
		closeBtn.onclick = function(event) { dlg.classList.remove('active'); };
		dlg.classList.add('active');
	}

	const imgs = Array.prototype.slice.call(document.querySelectorAll('img[data-src]'));
	if(imgs.length > 0) {
		const modalBox = document.querySelector('.modal');
		const closeBtn = document.querySelector('.modal button.closeBtn');
		const prevBtn = document.querySelector('.modal button.prevBtn');
		const nextBtn = document.querySelector('.modal button.nextBtn');
		const size = document.querySelector('.modal div.size');
		const filename = document.querySelector('.modal div.filename');
		const offset = (filename.hasAttribute('data-root')) ? filename.getAttribute('data-root').length : 0;
		const counter = document.querySelector('.modal div.counter');
		const img = document.querySelector('.modal .modal-box img');
		img.onload = function(event) {
			size.textContent = img.width + ' x ' + img.height;
		};
		var pos = null;
		var imgSrcList = [];

		function copyToClipboard(value) {
			const entry = document.getElementById('clipboard-entry');
			entry.value = value;
			entry.select();
			document.execCommand('copy');
			alert('<?php echo L_MEDIAS_LINK_COPYCLP_DONE; ?> : \n' + value);
		}

		function setImgSrc(index) {
			if(index < 0 || index > imgSrcList.length - 1) { return; }
			pos = index;
			var src = imgSrcList[pos];
			img.src = src;
			prevBtn.disabled = (pos <= 0);
			nextBtn.disabled = (pos >= imgSrcList.length -1);
			filename.textContent = (offset > 0) ? src.substring(offset) : src;
			counter.textContent = (pos + 1) + ' / ' + imgSrcList.length;
		}

		imgs.forEach(function(item) {
			imgSrcList.push(item.getAttribute('data-src'));
			item.addEventListener('click', function(event) {
				setImgSrc(imgSrcList.indexOf(event.target.getAttribute('data-src')));
				modalBox.classList.add('active');
				event.preventDefault();
			})
		});

		closeBtn.addEventListener('click', function(event) {
			modalBox.classList.remove('active');
			event.preventDefault();
		});
		prevBtn.addEventListener('click', function(event) {
			setImgSrc(pos - 1);
			event.preventDefault();
		});
		nextBtn.addEventListener('click', function(event) {
			setImgSrc(pos + 1);
			event.preventDefault();
		});
		filename.addEventListener('click', function(event) {
			copyToClipboard(this.textContent);
			event.preventDefault();
		});

		document.addEventListener('keydown', function(event) {
			if(modalBox.classList.contains('active')) {
				if(!event.altKey && !event.ctrlKey && !event.shiftKey) {
					switch(event.key) {
						case 'ArrowLeft':
							setImgSrc(pos - 1);
							break;
						case ' ':
						case 'ArrowRight':
							setImgSrc(pos + 1);
							break;
						case 'Home':
							setImgSrc(0);
							break;
						case 'End':
							setImgSrc(imgSrcList.length - 1);
							break;
						case 'Escape' :
							modalBox.classList.remove('active');
							break;
						default:
							return;
							// console.log(event.key);
					}
					event.preventDefault();
				} else if(event.ctrlKey && !event.shiftKey && event.key == 'c') {
					var value = imgSrcList[pos].replace(/^(\.+\/)+/, '');
					if(event.altKey) {
						// On calcule l'url de la miniature
						value = value.replace(/(\.(?:jpe?g|png|gif))$/, '.tb$1');
					}
					copyToClipboard(value);
				}
			}
		});

		// gestion des icones et de la recherche dans le tableau de medias
		const mediasTable = document.getElementById('medias-table');
		if(mediasTable != null) {
			mediasTable.addEventListener('click', function(event) {
				const el = event.target;
				if(el.tagName == 'I' && el.classList.contains('icon-media')) {
					const a = el.parentElement.querySelector('a[href]');
					if(a != null) {
						event.preventDefault();
						const value = a.href;
						if(!el.hasAttribute('data-rename')) {
							// copy link into the clipboard
							copyToClipboard(value);
						} else {
							// rename the file
							document.getElementById('id_oldname').value = value;
							dialogBox('dlgRenameFile');
						}
					}
				}
			});

			// Look for a media
			const STORAGE_KEY = 'medias_search';
			var mediaRows = mediasTable.tBodies[0].rows;

			function lookForMedias(value) {
				for(var i=0, iMax=mediaRows.length; i<iMax; i++) {
					if(!mediaRows[i].dataset.hasOwnProperty('media')) {
						mediaRows[i].dataset.media = mediaRows[i].querySelector('a.imglink').textContent.toLowerCase();
					}
					if(mediaRows[i].dataset.media.indexOf(value) >= 0) {
						mediaRows[i].classList.remove('hidden');
					} else {
						mediaRows[i].classList.add('hidden');
					}
				}
			}

			const searchInput = document.getElementById('medias-search');
			if(searchInput != null) {
				searchInput.onkeyup = function(event) {
					event.preventDefault();
					const value = event.target.value.toLowerCase();
					lookForMedias(value);
					if(typeof(localStorage) == 'object') {
						if(value.length > 0) {
							localStorage.setItem(STORAGE_KEY, value);
						} else {
							localStorage.removeItem(STORAGE_KEY);
						}
					}
				};

				if(typeof(localStorage) == 'object') {
					const value = localStorage.getItem(STORAGE_KEY);
					if(value != null) {
						searchInput.value = value;
						lookForMedias(value);
					}
				}
			}
		}
	}

	// New directory
	document.getElementById('btnNewFolder').addEventListener('click', function(event) {
		event.preventDefault();
		dialogBox('dlgNewFolder');
	});

	// Fil d'Ariane
	const steps = document.querySelectorAll('#fil-ariane-medias a[data]');
	for(var i=0, iMax=steps.length; i<iMax; i++) {
		steps[i].addEventListener('click', function(event) {
			event.preventDefault();
			const frm = document.getElementById('form_medias');
			if(frm != null) {
				frm.elements.folder.value = event.target.getAttribute('data');
				frm.submit();
			}
		});
	}

})();
</script>

<?php
# Hook Plugins
eval($plxAdmin->plxPlugins->callHook('AdminMediasFoot'));
# On inclut le footer
include __DIR__ .'/foot.php';
?>
