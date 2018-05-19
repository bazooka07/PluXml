<?php

# http://www.rssboard.org/rss-specification

/**
 * Classe plxFeed responsable du traitement global des flux de syndication
 *
 * @package PLX
 * @author	Florent MONTHEL, Stephane F, Amaury Graillat, J.P. Pourrez
 **/
class plxFeed extends plxMotor {

	private static $instance = null;

	/**
	 * Méthode qui se charger de créer le Singleton plxFeed
	 *
	 * @return	objet			retourne une instance de la classe plxFeed
	 * @author	Stephane F
	 **/
	public static function getInstance(){
		if (!isset(self::$instance))
			self::$instance = new plxFeed(path('XMLFILE_PARAMETERS'));
		return self::$instance;
	}

	/**
	 * Constructeur qui initialise certaines variables de classe
	 * et qui lance le traitement initial
	 *
	 * @param	filename	emplacement du fichier XML de configuration
	 * @return	null
	 * @author	Florent MONTHEL, Stéphane F
	 **/
	protected function __construct($filename) {

		# On parse le fichier de configuration
		$this->getConfiguration($filename);
		# récupération des paramètres dans l'url
		$this->get = plxUtils::getGets();
		# gestion du timezone
		date_default_timezone_set($this->aConf['timezone']);
		# chargement des variables
		$this->racine = $this->aConf['racine'];
		$this->bypage = $this->aConf['bypage_feed'];
		$this->tri = 'desc';
		$this->clef = (!empty($this->aConf['clef']))?$this->aConf['clef']:'';
		# Traitement des plugins
		$this->plxPlugins = new plxPlugins($this->aConf['default_lang']);
		$this->plxPlugins->loadPlugins();
		# Hook plugins
		eval($this->plxPlugins->callHook('plxFeedConstructLoadPlugins'));
		# Traitement sur les répertoires des articles et des commentaires
		$this->plxGlob_arts = plxGlob::getInstance(PLX_ROOT.$this->aConf['racine_articles'],false,true,'arts');
		$this->plxGlob_coms = plxGlob::getInstance(PLX_ROOT.$this->aConf['racine_commentaires']);
		# Récupération des données dans les autres fichiers xml
		$this->getCategories(path('XMLFILE_CATEGORIES'));
		$this->getUsers(path('XMLFILE_USERS'));
		$this->getTags(path('XMLFILE_TAGS'));
		# Récupération des articles appartenant aux catégories actives
		$this->getActiveArts();
		# Hook plugins
		eval($this->plxPlugins->callHook('plxFeedConstruct'));
	}

	/**
	 * Méthode qui effectue une analyse de la situation et détermine
	 * le mode à appliquer. Cette méthode alimente ensuite les variables
	 * de classe adéquates
	 *
	 * @return	null
	 * @author	Florent MONTHEL, Stéphane F, J.P. Pourrez
	 **/
	public function fprechauffage() {

		# Hook plugins
		if(eval($this->plxPlugins->callHook('plxFeedPreChauffageBegin'))) return;

		$allCats = "(?:home,|pin,|\d{3},)*(?:home|pin|{$this->activeCats})(?:,pin|\d{3})*";
		if($this->get AND preg_match('#^(?:atom/|rss/)?categorie(\d+)/?#',$this->get,$capture)) {
			# Flux pour une catégorie
			$this->mode = 'article'; # Mode du flux
			# On récupère la catégorie cible
			$this->cible = str_pad($capture[1],3,'0',STR_PAD_LEFT); # On complète sur 3 caractères
			# On modifie le motif de recherche
			$cats = "((?:home,|pin,|\d{3},)*(?:{$this->cible})(?:\d{3})*)";
			$this->motif = "@^\d{4}\.{$cats}\.\d{3}\.\d{12}\.[\w-]+\.xml$@";
		} elseif($this->get AND preg_match('@^(?:atom/|rss/)?commentaires/?$@',$this->get)) {
			# Flux de tous les commentaires
			$this->mode = 'commentaire'; # Mode du flux
		} elseif($this->get AND preg_match('@^(?:atom/|rss/)?tag/([\w-]+)/?$@',$this->get,$capture)) {
			# Flux pour un mot-clé
			$this->mode = 'tag';
			$this->cible = $capture[1];
			$ids = array();
			$datetime = date('YmdHi');
			foreach($this->aTags as $idart => $tag) {
				if($tag['date'] <= $datetime) {
					$tags = array_map('trim', explode(',', $tag['tags']));
					$tagUrls = array_map(array('plxUtils', 'title2url'), $tags);
					if(in_array($this->cible, $tagUrls)) {
						if(!isset($ids[$idart])) $ids[$idart] = $idart;
						if(!isset($cibleName)) {
							$key = array_search($this->cible, $tagUrls);
							$cibleName=$tags[$key];
						}
					}
				}
			}
			if(sizeof($ids)>0) {
				$allIds = '('.implode('|', $ids).')';
				$this->motif = "@{$allIds}\.$allCats\.\d{3}\.\d{12}\.[\w-]+\.xml$@";
			} else
				$this->motif = '';
		} elseif($this->get AND preg_match('@^(?:atom/|rss/)?commentaires/article(\d+)/?$@',$this->get,$capture)) {
			# Flux des commentaires d'un article
			$this->mode = 'commentaire'; # Mode du flux
			# On récupère l'article cible
			$this->cible = str_pad($capture[1],4,'0',STR_PAD_LEFT); # On complète sur 4 caractères
			# On modifie le motif de recherche
			$this->motif = "@^{$this->cible}\.{$allCats}\.\d{3}\.\d{12}\.[\w-]+\.xml$@";
		}
		elseif($this->get AND preg_match('#^admin([\w-]+)/commentaires/(hors|en)-ligne/?$#',$this->get,$capture)) {
			$this->mode = 'admin'; # Mode du flux
			$this->cible = '-';	# /!\: il ne faut pas initialiser à blanc sinon ça prend par défaut les commentaires en ligne (faille sécurité)
			if ($capture[1] == $this->clef) {
				if($capture[2] == 'hors')
					$this->cible = '_';
				elseif($capture[2] == 'en')
					$this->cible = '';
			}
		} else {
			$this->mode = 'article'; # Mode du flux
			# On modifie le motif de recherche
			$this->motif = "@^\d{4}\.{$allCats}\.\d{3}\.\d{12}\.[\w-]+\.xml$@";
		}
		# Hook plugins
		eval($this->plxPlugins->callHook('plxFeedPreChauffageEnd'));

	}

	/**
	 * Méthode qui effectue le traitement selon le mode du moteur
	 *
	 * @return	null ou redirection si une erreur est détectée
	 * @author	Florent MONTHEL, Stéphane F
	 **/
	public function fdemarrage() {

		# Hook plugins
		if(eval($this->plxPlugins->callHook('plxFeedDemarrageBegin'))) return;

		# Flux de commentaires d'un article précis
		if($this->mode == 'commentaire' AND $this->cible) {
			if(!$this->getArticles()) { # Aucun article, on redirige
				$this->cible = $this->cible + 0;
				header('Location: '.$this->urlRewrite('?article'.$this->cible.'/'));
				exit;
			} else { # On récupère les commentaires
				$regex = '/^'.$this->cible.'.[0-9]{10}-[0-9]+.xml$/';
				$this->getCommentaires($regex,'rsort',0,$this->bypage);
			}
		} elseif($this->mode == 'commentaire') {
			# Flux de commentaires global
			$regex = '@^\d{4}\.\d{10}-\d+\.xml$@';
			$this->getCommentaires($regex, 'rsort', 0, $this->bypage);
		} elseif($this->mode == 'admin') {
			# Flux admin
			if(empty($this->clef)) { # Clef non initialisée
				header('Content-Type: text/plain; charset='.PLX_CHARSET);
				echo L_FEED_NO_PRIVATE_URL;
				exit;
			}
			# On récupère les commentaires
			$this->getCommentaires("@^{$this->cible}\.\d{4}\.\d{10}-\d+\.xml$@", 'rsort', 0, $this->bypage, 'all');
		} elseif($this->mode == 'tag') {
			# Flux d'articles pour un tag
			if(empty($this->motif)) {
				header('Location: '.$this->urlRewrite('?tag/'.$this->cible.'/'));
				exit;
			} else {
				$this->getArticles(); # Récupération des articles (on les parse)
			}
		} else {
			# Flux des articles d'une catégorie précise
			if($this->cible) {
				# On va tester la catégorie
				if(empty($this->aCats[$this->cible]) OR !$this->aCats[$this->cible]['active']) {
					# Pas de catégorie, on redirige
					$this->cible = $this->cible + 0;
					header('Location: '.$this->urlRewrite('?categorie'.$this->cible.'/'));
					exit;
				}
			}
			$this->getArticles(); # Récupération des articles (on les parse)
		}

		# Selon le mode, on appelle la méthode adéquate
		switch($this->mode) {
			case 'tag':
			case 'article' : $this->getRssArticles(); break;
			case 'commentaire' : $this->getRssComments(); break;
			case 'admin' : $this->getAdminComments(); break;
			default : break;
		}
		# Hook plugins
		eval($this->plxPlugins->callHook('plxFeedDemarrageEnd'));

	}

	/**
	 * Méthode qui affiche le flux rss des articles du site
	 *
	 * @return	flux sur stdout
	 * @author	Florent MONTHEL, Stephane F, Amaury GRAILLAT, J.P. Pourrez
	 **/
	public function getRssArticles() {

		# Initialisation
		if($this->mode == 'tag') {
			$title = $this->aConf['title'].' - '.L_PAGETITLE_TAG.' '.$this->cible;
			$link = $this->urlRewrite('?tag/'.$this->cible);
		}
		elseif($this->cible) { # Articles d'une catégorie
			$catId = $this->cible + 0;
			$title = $this->aConf['title'].' - '.$this->aCats[ $this->cible ]['name'];
			$link = $this->urlRewrite('?categorie'.$catId.'/'.$this->aCats[ $this->cible ]['url']);
		} else { # Articles globaux
			$title = $this->aConf['title'];
			$link = $this->urlRewrite();
		}
		$title = htmlspecialchars($title);
		$lastBuildDate = $this->plxRecord_coms->lastUpdate('date_update');

		$href = $this->urlRewrite('feed.php?'.$this->get);
		$cname = 'constant';

		header('Content-Type: application/rss+xml; charset='.PLX_CHARSET);
		echo <<< RSS_ARTICLES_STARTS
<?xml version="1.0" encoding="{$cname('PLX_CHARSET')}" ?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>$title</title>
		<link>$link</link>
		<language>{$this->aConf['default_lang']}</language>
		<description><![CDATA[{$this->aConf['description']}]]></description>
		<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="self" type="application/rss+xml" href="$href" />
		<lastBuildDate>$lastBuildDate</lastBuildDate>
		<generator>PluXml</generator>\n
RSS_ARTICLES_STARTS;
		# On va boucler sur les articles (s'il y en a)
		if($this->plxRecord_arts) {
			while($this->plxRecord_arts->loop()) {
				# Traitement initial
				if($this->aConf['feed_chapo']) {
					$content = $this->plxRecord_arts->f('chapo');
					if(trim($content) == '') $content = $this->plxRecord_arts->f('content');
				} else {
					$content = $this->plxRecord_arts->f('chapo').$this->plxRecord_arts->f('content');
				}
				$content .= $this->aConf['feed_footer'];
				$artId = intval($this->plxRecord_arts->f('numero'));
				$author = htmlspecialchars($this->aUsers[$this->plxRecord_arts->f('author')]['name']);
				$title = htmlspecialchars($this->plxRecord_arts->f('title'));
				$link = $this->urlRewrite('?article'.$artId.'/'.$this->plxRecord_arts->f('url'));
				$guid = md5($this->plxRecord_arts->f('numero').$this->plxRecord_arts->f('date_update'));
				$description = plxUtils::rel2abs($this->racine,$content);
				$pubDate = plxDate::dateIso2rfc822($this->plxRecord_arts->f('date'));
				$enclosure = $this->plxRecord_arts->f('thumbnail');
				if(!empty($enclosure)) {
					# Pas de <enclosure />
					$enclosure = $this->urlRewrite($enclosure);
					$enclosure = <<< ENCLOSURE

				<enclosure>$enclosure</enclosure>
ENCLOSURE;
				}
				# On affiche le flux dans un buffer
				$item = <<< ENTRY
			<item>
				<title>$title</title>
				<link>$link</link>
				<guid>$guid</guid>$enclosure
				<description><![CDATA[{$description}]]></description>
				<pubDate>$pubDate</pubDate>
				<dc:creator>$author</dc:creator>
			</item>\n
ENTRY;
				# Hook plugins
				eval($this->plxPlugins->callHook('plxFeedRssArticlesXml'));
				echo $item;
			}
		}

		echo <<< RSS_ARTICLES_ENDS
	</channel>
</rss>\n
RSS_ARTICLES_ENDS;
	}

	private function __getRssComments($header, $admin=false) {
		$last_updated = $this->plxRecord_coms->lastUpdate('date');
		$lastBuildDate = plxDate::dateIso2rfc822($last_updated);
		$cname = 'constant';

		header('Content-Type: application/rss+xml; charset='.PLX_CHARSET);
		echo <<< RSS_COMMENTS_STARTS
<?xml version="1.0" encoding="{$cname('PLX_CHARSET')}" ?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title>{$header['title']}</title>
		<link>{$header['link']}</link>
		<language>{$this->aConf['default_lang']}</language>
		<description><![CDATA[{$this->aConf['description']}]]></description>
		<atom:link xmlns:atom="http://www.w3.org/2005/Atom" rel="self" type="application/rss+xml" href="{$header['href']}" />
		<lastBuildDate>$lastBuildDate</lastBuildDate>
		<generator>PluXml</generator>\n
RSS_COMMENTS_STARTS;

		# On va boucler sur les commentaires (s'il y en a)
		if($this->plxRecord_coms) {
			while($this->plxRecord_coms->loop()) {
				# Traitement initial
				if(isset($this->activeArts[$this->plxRecord_coms->f('article')])) {
					$artId = $this->plxRecord_coms->f('article') + 0;
					if($this->cible) { # Commentaires d'un article
						$title_com = $this->plxRecord_arts->f('title').' - ';
						$title_com .= L_FEED_WRITTEN_BY.' '.$this->plxRecord_coms->f('author').' @ ';
						$title_com .= plxDate::formatDate($this->plxRecord_coms->f('date'),'#day #num_day #month #num_year(4), #hour:#minute');
						$comId = 'c'.$this->plxRecord_coms->f('article').'-'.$this->plxRecord_coms->f('index');
						$link_com = $this->urlRewrite('?article'.$artId.'/'.$this->plxRecord_arts->f('url').'#'.$comId);
					} else { # Commentaires globaux
						$title_com = $this->plxRecord_coms->f('author').' @ ';
						$title_com .= plxDate::formatDate($this->plxRecord_coms->f('date'),'#day #num_day #month #num_year(4), #hour:#minute');
						$artInfo = $this->artInfoFromFilename($this->plxGlob_arts->aFiles[$this->plxRecord_coms->f('article')]);
						$comId = 'c'.$this->plxRecord_coms->f('article').'-'.$this->plxRecord_coms->f('index');
						$link_com = $this->urlRewrite('?article'.$artId.'/'.$artInfo['artUrl'].'#'.$comId);
					}
					$title = htmlspecialchars($title);
					# On vérifie la date de publication
					if($this->plxRecord_coms->f('date') > $last_updated)
						$last_updated = $this->plxRecord_coms->f('date');
					$pubDate = plxDate::dateIso2rfc822($this->plxRecord_coms->f('date'));
					$creator = htmlspecialchars(plxUtils::strCheck($this->plxRecord_coms->f('author')));

					# On affiche le flux dans un buffer
					$entry = <<< ENTRY_STARTS
<item>
	<title>$title</title>
	<link>$link_com</link>
	<guid>$link_com</guid>
	<description><![CDATA[{$this->plxRecord_coms->f('content')}]]</description>
	<pubDate>$pubDate</pubDate>
	<dc:creator>$creator</dc:creator>\n
ENTRY_STARTS;
					# Hook plugins
					eval($this->plxPlugins->callHook($header['hook']));
					$entry = <<< ENTRY_ENDS
</item>\n
ENTRY_ENDS;
				}
			}
		}

		echo <<< RSS_COMMENTS_ENDS
	</channel>
</rss>\n
RSS_COMMENTS_ENDS;
	}


	/**
	 * Méthode qui affiche le flux rss des commentaires du site
	 *
	 * @return	flux sur stdout
	 * @author	Florent MONTHEL, Amaury GRAILLAT, J.P. Pourrez
	 **/
	public function getRssComments() {
		# Commentaires globaux
		$title = $this->aConf['title'];
		$link = '';

		# Commentaires pour un article
		if($this->cible) {
			$title .= ' - '.$this->plxRecord_arts->f('title');
			$link = "?article{$artId}{$this->plxRecord_arts->f('numero')}0/{$this->plxRecord_arts->f('url')}";
		}

		self::__getRssComments(array(
			'title'	=> htmlspecialchars($title.' - '.L_FEED_COMMENTS),
			'link'	=> $this->urlRewrite($link),
			'href'	=> $this->urlRewrite('feed.php?rss/commentaires/'),
			'hook'	=> 'plxFeedRssCommentsXml'
		));
	}

	/**
	 * Méthode qui affiche le flux RSS des commentaires du site pour l'administration
	 *
	 * @return	flux sur stdout
	 * @author	Florent MONTHEL, Amaury GRAILLAT, J.P. Pourrez
	 **/
	public function getAdminComments() {

		# # Commentaires off/on line
		$sel = ($this->cible == '_') ? 'off' : 'on';
		$suffix = ($this->cible == '_') ? L_FEED_OFFLINE_COMMENTS : L_FEED_ONLINE_COMMENTS;
		$ref = ($this->cible == '_') ? 'hors' : 'en';
		self::__getRssComments(
			array(
				'title'	=> htmlspecialchars("{$this->aConf['title']} - $suffix"),
				'link'	=> $this->urlRewrite("{$this->racine}core/admin/comments.php?sel={$sel}line&page=1"),
				'href'	=> $this->urlRewrite("{$this->racine}feed.php?admin{$this->clef}/commentaires/{ref}-ligne"),
				'hook'	=> 'plxFeedAdminCommentsXml'
			),
			true
		);
	}
}
?>
