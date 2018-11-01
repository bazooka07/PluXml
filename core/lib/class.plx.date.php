<?php

/**
 * Classe plxDate rassemblant les fonctions utiles à PluXml
 * concernant la manipulation des dates
 *
 * @package PLX
 * @author	Stephane F., Amauray Graillat
 **/

class plxDate {

	/**
	 * Méthode qui retourne le libellé du mois ou du jour passé en paramètre
	 *
	 * @param	key		constante: 'day', 'month' ou 'short_month'
	 * @param	value	numero du mois ou du jour
	 * @return	string	libellé du mois (long ou court) ou du jour
	 * @author	Stephane F.
	 **/
	public static function getCalendar($key, $value) {

		if(!is_string($key) or (!is_string($value) and !is_integer($value))) { return false; }
		$names = array(
			'month' => array(
				L_JANUARY,
				L_FEBRUARY,
				L_MARCH,
				L_APRIL,
				L_MAY,
				L_JUNE,
				L_JULY,
				L_AUGUST,
				L_SEPTEMBER,
				L_OCTOBER,
				L_NOVEMBER,
				L_DECEMBER
			),
			'short_month' => array(
				L_SHORT_JANUARY,
				L_SHORT_FEBRUARY,
				L_SHORT_MARCH,
				L_SHORT_APRIL,
				L_SHORT_MAY,
				L_SHORT_JUNE,
				L_SHORT_JULY,
				L_SHORT_AUGUST,
				L_SHORT_SEPTEMBER,
				L_SHORT_OCTOBER,
				L_SHORT_NOVEMBER,
				L_SHORT_DECEMBER
			),
			'long_month' => array(
				L_LONG_JANUARY,
				L_LONG_FEBRUARY,
				L_LONG_MARCH,
				L_LONG_APRIL,
				L_LONG_MAY,
				L_LONG_JUNE,
				L_LONG_JULY,
				L_LONG_AUGUST,
				L_LONG_SEPTEMBER,
				L_LONG_OCTOBER,
				L_LONG_NOVEMBER,
				L_LONG_DECEMBER
			),
			'day' => array(
				L_MONDAY,
				L_TUESDAY,
				L_WEDNESDAY,
				L_THURSDAY,
				L_FRIDAY,
				L_SATURDAY,
				L_SUNDAY
			)
		);

		if(array_key_exists($key, $names)) {
			$i = intval($value);
			if($i > 0) {
				$i--;
				if($i < count($names[$key])) { return $names[$key][$i]; }
			}
		}
		return false;
	}

	/**
	 * Méthode qui formate l'affichage d'une date
	 *
	 * @param	date	date/heure au format YYYYMMDDHHMM
	 * @param	format	format d'affichage
	 * @return	string	date/heure formatée
	 * @author	Stephane F.
	 **/
	public static function formatDate($date, $format='#num_day/#num_month/#num_year(4)') {

		# On decoupe notre date
		$year4 = substr($date, 0, 4);
		$year2 = substr($date, 2, 2);
		$month = substr($date, 4, 2);
		$day = substr($date, 6, 2);
		$day_num = date('w',mktime(0,0,0,intval($month),intval($day),intval($year4)));
		$hour = substr($date,8,2);
		$minute = substr($date,10,2);

		# On retourne notre date au format humain
		$format = str_replace('#time', $hour.':'.$minute, $format);
		$format = str_replace('#minute', $minute, $format);
		$format = str_replace('#hour', $hour, $format);
		$format = str_replace('#day', plxDate::getCalendar('day', $day_num), $format);
		$format = str_replace('#short_month', plxDate::getCalendar('short_month', $month), $format);
		$format = str_replace('#month', plxDate::getCalendar('month', $month), $format);
		$format = str_replace('#num_day(1)', intval($day), $format);
		$format = str_replace('#num_day(2)', $day, $format);
		$format = str_replace('#num_day', $day, $format);
		$format = str_replace('#num_month', $month, $format);
		$format = str_replace('#num_year(2)', $year2 , $format);
		$format = str_replace('#num_year(4)', $year4 , $format);
		return $format;
	}

	/**
	 * Méthode qui convertis un timestamp en date/time
	 *
	 * @param	timestamp	timstamp au format unix
	 * @return	string		date au format YYYYMMDDHHMM
	 * @author	Stephane F.
	 **/
	public static function timestamp2Date($timestamp) {

		return date('YmdHi', $timestamp);

	}

	/**
	 * Méthode qui éclate une date au format YYYYMMDDHHMM dans un tableau
	 *
	 * @param	date		date au format YYYYMMDDHHMM
	 * @return	array		tableau contenant le détail de la date
	 * @author	Stephane F.
	 **/
	public static function date2Array($date) {

		preg_match('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9:]{2})([0-9:]{2})/',$date,$capture);
		return array (
			'year' 	=> $capture[1],
			'month' => $capture[2],
			'day' 	=> $capture[3],
			'hour'	=> $capture[4],
			'minute'=> $capture[5],
			'time' 	=> $capture[4].':'.$capture[5]
		);
	}

	/**
	 * Méthode qui vérifie la validité de la date et de l'heure
	 *
	 * @param	int		mois
	 * @param	int		jour
	 * @param	int		année
	 * @param	int		heure:minute
	 * @return	boolean	vrai si la date est valide
	 * @author	Amaury Graillat
	 **/
	public static function checkDate($day, $month, $year, $time) {

		return (preg_match("/^(0[1-9]|[1-2][0-9]|3[0-1])(0[1-9]|1[0-2])[1-2][0-9]{3}([0-1][0-9]|2[0-3])\:[0-5][0-9]$/",$day.$month.$year.$time)
			AND checkdate($month, $day, $year));

	}

	/**
	 * Fonction de conversion de date ISO en format RFC822
	 *
	 * @param	date	date à convertir
	 * @return	string	date au format iso.
	 * @author	Amaury GRAILLAT
	 **/
	public static function dateIso2rfc822($date) {

		$tmpDate = plxDate::date2Array($date);
		return date(DATE_RSS, mktime(substr($tmpDate['time'],0,2), substr($tmpDate['time'],3,2), 0, $tmpDate['month'], $tmpDate['day'], $tmpDate['year']));
	}

}
?>
