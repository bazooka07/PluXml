/*
 * For using with medias.php.
 *
 * @author: J.P. Pourrez (bazooka07)
 * @date 2018-11-30
 * */

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
		const input = dlg.querySelector('input[type="text"]');
		if(input != null) {
			input.focus();
		}
	}

	const mediasTable = document.getElementById('medias-table');
	var imgs = mediasTable.querySelectorAll('tr td:nth-of-type(2) img[data-img]');
	if(imgs.length > 0) {

		// console.log('tableau imgs  créé');

		function updateImgs() {
			imgs = mediasTable.querySelectorAll('tr td:nth-of-type(2) img[data-img]');
			for(var i=0, iMax=imgs.length; i<iMax; i++) { imgs[i].index = i; }
			// console.log('Tableau trié');
		}

		updateImgs();

		const i18n = JSON.parse(mediasTable.dataset.i18n);
		const modalBox = document.querySelector('.modal');
		const closeBtn = document.querySelector('.modal button.closeBtn');
		const prevBtn = document.querySelector('.modal button.prevBtn');
		const nextBtn = document.querySelector('.modal button.nextBtn');
		const size = document.querySelector('.modal div.size');
		const filename = document.querySelector('.modal div.filename');
		const offset = (filename.hasAttribute('data-root')) ? filename.getAttribute('data-root').length : 0;
		const counter = document.querySelector('.modal div.counter');
		const img = document.querySelector('.modal .modal-box img');
		const preloadImg = new Image();
		preloadImg.onload = function(event) {
			// Only for getting the real size of img
			size.textContent = preloadImg.width + ' x ' + preloadImg.height;
			img.src = preloadImg.src;
		};
		var pos = null;

		function copyToClipboard(value) {
			const entry = document.getElementById('clipboard-entry');
			entry.value = value;
			entry.select();
			document.execCommand('copy');
			alert(i18n.copyClp + ' :\n' + value);
		}

		function setImgSrc(index) {
			if(index < 0 || index > imgs.length - 1) { return; }
			pos = index;
			const src = imgs[pos].getAttribute('src').replace(/\/\.thumbs\//, '/');
			preloadImg.src = src;
			prevBtn.disabled = (pos <= 0);
			nextBtn.disabled = (pos >= imgs.length -1);
			filename.textContent = (offset > 0) ? src.substring(offset) : src;
			counter.textContent = (pos + 1) + ' / ' + imgs.length;
		}

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

		// gestion du mode modal, des icones et de la recherche dans le tableau de medias
		if(mediasTable != null) {
			mediasTable.addEventListener('click', function(event) {
				const el = event.target;
				if(el.tagName == 'I' && el.classList.contains('icon-media')) {
					const a = el.parentElement.querySelector('a[href]');
					if(a != null) {
						event.preventDefault();
						const value = a.getAttribute('href'); // différent de a.href;
						if(!el.hasAttribute('data-rename')) {
							// copy link into the clipboard
							const offset = (mediasTable.hasAttribute('data-plxroot-offset')) ? mediasTable.getAttribute('data-plxroot-offset') : 0;
							copyToClipboard(value.substr(offset));
						} else {
							// rename the file
							document.getElementById('id_oldname').value = value;
							dialogBox('dlgRenameFile');
						}
					}
				} else if(el.tagName == 'IMG' && el.hasAttribute('data-img')) {
					// gestion du modal
					setImgSrc(el.index);
					modalBox.classList.add('active');
					event.preventDefault();
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

		// Tablesort
		// for sorting  a table : http://tristen.ca/tablesort/demo/
		const myScript = document.createElement('SCRIPT');

		// https://developer.mozilla.org/en-US/docs/Web/Events/load
		myScript.addEventListener('load', function(event) {
			// console.log(this.src + ' loaded');
			Tablesort.extend(
				'integer',
				function(item) { return /^\d+$/.test(item); },
				function(a, b) {
					const intA = parseInt(a);
					const intB = parseInt(b);
					return (intA < intB) ? 1 : (intA == intB) ? 0 : -1;
				}
			);

			// Icon after loading the HTML page
			var descending = 'ascending';
			const mediasSort = mediasTable.getAttribute('data-medias-sort');
			if(mediasSort != null) {
				const parts = mediasSort.split('_');
				const col = mediasTable.querySelector('thead th[data-medias-sort^="' + parts[0] + '"]');
				if(col != null) {
					col.setAttribute('data-sort-default', '');
					if(parts[1] == 'desc') { descending = 'descending'; }
				}
			}

			const sort = new Tablesort(mediasTable, { descending: descending });
			this.table.addEventListener('afterSort', function(event) {
				updateImgs();
				const form = document.getElementById('form_medias');
				if(form != null) {
					const col = sort.current;
					if(col.hasAttribute('data-medias-sort')) {
						const parts = col.getAttribute('data-medias-sort').split('_');
						const order = col.getAttribute('aria-sort').replace(/ending$/, '');
						const value = parts[0] + '_' + order;
						form.elements.sort.value = value;
						// console.log('Medias order by ' + value);
					}
				} else {
					console.log('#form_medias element not found');
				}
			});
		});
		// var src = 'https://raw.github.com/tristen/tablesort/gh-pages/dist/tablesort.min.js';
		var src = document.scripts[document.scripts.length-1].src.replace(/[^/]*\.js$/, 'tablesort.min.js');
		myScript.src = src;
		myScript.table = mediasTable;
		// console.log('Downloading ' + src);
		document.head.appendChild(myScript);

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

// uploading files
(function() {

	// https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file
	function returnFilesize(value) {
		if(value < 1024) { return value + 'B'; }
		if(value < 1048576) { return (value / 1024).toFixed(1) + 'KB'; }
		return (value / 1048576).toFixed(1) + 'MB';
	}

	const inputFiles = document.getElementById('selector');
	const filesList = document.getElementById('files_list');
	const iconsPath = filesList.getAttribute('data-icons-path');
	const iconExts =  filesList.getAttribute('data-icon-exts');
	const progressBar = document.getElementById('upload-progress');
	const progressLoad = document.getElementById('post-load');
	var limits = {};
	'max_file_uploads, upload_max_filesize, post_max_size'.split(/\s*,\s*/).forEach(function(key) {
		const el = document.getElementById(key);
		limits[key] = (el != null && el.hasAttribute('data-value')) ? parseInt(el.getAttribute('data-value')) : -1;
	});

	if(inputFiles != null) {
		const exts = (iconExts != null) ? iconExts.split('|') : null;
		inputFiles.addEventListener('change', function(event) {
			filesList.innerHTML = '';
			var filesCount = 0;
			var filesSum = 0;
			var badFilesSize = 0;
			var disabledForm = false;
			for(var i=0, iMax=inputFiles.files.length; i<iMax; i++) {
				const curFile = inputFiles.files[i];
				const fig = document.createElement('FIGURE');

				// teste la taille des fichiers
				if(limits.upload_max_filesize > 0 && curFile.size > limits.upload_max_filesize) {
					fig.classList.add('big-size');
					disabledForm = true;
				}

				// tester si mimetype = image
				const ext = curFile.name.replace(/.*\.(\w+)$/, '$1');
				const img = document.createElement('IMG');
				if(/^image\/(?:jpe?g|png|svg\+xml|gif)/.test(curFile.type)) {
					img.src = window.URL.createObjectURL(curFile);
				} else if(exts != null && exts.indexOf(ext) >= 0) {
					img.src = iconsPath + ext + '.png';
				} else {
					img.src = iconsPath + '_blank.png';
				}
				fig.appendChild(img);

				const figCaption = document.createElement('FIGCAPTION');

				const filename = document.createElement('p');
				filename.textContent = curFile.name;
				filename.title = curFile.name;
				filename.className = 'divtitle';
				figCaption.appendChild(filename);

				const sizeElt = document.createElement('p');
				sizeElt.textContent = returnFilesize(curFile.size);
				figCaption.appendChild(sizeElt);
				fig.appendChild(figCaption);

				filesList.appendChild(fig);

				filesCount++;
				filesSum += curFile.size;
			}
			if(limits.max_file_uploads > 0 && filesCount > limits.max_file_uploads) {
				document.getElementById('max_file_uploads').classList.add('blink');
				disabledForm = true;
			}
			var load = 100;
			if(limits.post_max_size > 0) {
				if(filesSum > limits.post_max_size) {
					document.getElementById('post_max_size').classList.add('blink');
					disabledForm = true;
				} else {
					load = parseInt(100.0 * filesSum / limits.post_max_size);
				}
				// Progressbar for the size of post datas
				progressLoad.value = load;
			}

			document.getElementById('btn_upload').disabled = disabledForm;
		});

		inputFiles.ondragenter = function(event) { this.classList.add('drag'); }
		inputFiles.ondragexit = function(event)  { this.classList.remove('drag'); }
		inputFiles.ondrop = function(event)      { this.classList.remove('drag'); }

		// Gestion de la progresssion de l'envoi du lot de fichiers
		const progressBar = document.getElementById('upload-progress');
		const form1 = document.getElementById('form_uploader');
		const keyName = (inputFiles.hasAttribute('data-session')) ? inputFiles.dataset.session : null;

		function parseResponse(value) {
			const datas = JSON.parse(value);
			if(datas != null) {
				progressBar.value = progressBar.max * datas.bytes_processed / datas.content_length;
			}
		}

		if(progressBar != null && form1 != null && keyName != null) {
			const key = form1.elements[keyName].value;
			const url = form1.action.replace(/\.php$/, '-tracker.php') + '?key=' + key;
			var timer1 = null;
			const xhr = new XMLHttpRequest();
			xhr.onreadystatechange = function() {
				if(xhr.readyState == XMLHttpRequest.DONE && xhr.status == 200) {
					parseResponse(xhr.responseText);

					if(timer1 != null) { clearTimeout(timer1); }
					timer1 = setTimeout(function() {
						xhr.open('GET', url);
						xhr.send();
					}, 1500);
				}
			};

			form1.addEventListener('submit', function(event) {
				timer1 = setTimeout(function() {
					xhr.open('GET', url);
					xhr.send();
				}, 500);
			});
		}
	}
})();
