<?php

/**
 *  Joomla 2 Contao
 *  am Beispiel (Musterdaten) von einem News-Portal mit Vereinen
 *
 *  Datum: 29.07.2011
 *
 *  (c) Oliver Lohoff
 *
 *  Sections => tl_news.pid
 *  Categories => tl_news.vereine
 *
 *  Weitere Features:
 *  - Links werden ersetzt
 *  - Author wird angepasst
 *
 */

class joomla2contao {

    private $datei;
    private $arrConfig;

    protected function config() {
        $this->arrConfig = array(
	    // Quelle => Ziel
	       'section' => array(
               1 => 2,  // Basketball
			   2 => 16,  // Tennis
			   3 => 22,  // Fitness-Studios
			   4 => 3,  // Boxen
			   5 => 4,  // Eishockey
			   6 => 1, // American Sports
			   7 => 6,  // Fußball
			   8 => 23,  // Judo
			   10 => 13, // Radsport/Triathlon
			   // ...
				),
		   'authors' => array(
			   62 => 4, // Max Mustermann
			   63 => 5, // Max Mustermann
				),
            // Vereine
           'categories' => array(
                12 => 26,  // 1. FC Pelkum 1924 e.V. Fuﬂball => 1. FC Pelkum 1924 e.V.
                160 => 79,  // ASV Hamm 04/69 Tischtennis e.V. Tischtennis => ASV Hamm 04/69 Tischtennis e.V.
                4 => 43,  //   ASV Hamm 04/69 Handball e.V. Handball => ASV Hamm 04/69 Handball e.V.
                145 => 68,  //   ASV Hamm Tennis e.V. Tennis => ASV Hamm Tennis e.V.
                243 => 17,  //   ASV Hamm-Westfalen Handball => ASV Hamm-Westfalen
                // ...
          )
	    );
    }

    protected function dbconnect() {
        $sqlhost = "localhost";
        $sqluser = "";
        $sqlpass = "";
        $sqldatabase = "";
        mysql_connect($sqlhost, $sqluser, $sqlpass) or die("Datenbankserver nicht gefunden!");
        mysql_select_db($sqldatabase) or die("Datenbank nicht vorhanden!");
        // damit die Daten als UTF8 kommen, sonst nur Kauderwelsch
        mysql_query('SET NAMES utf8');
    }

    protected function dbclose() {
        mysql_close();
    }

    protected function standardize($strString)
    {
        $arrSearch = array('/[^a-zA-Z0-9 _-]+/i', '/ +/', '/\-+/');
        $arrReplace = array('', '-', '-');
        $strString = preg_replace('/\{\{[^\}]+\}\}/U', '', $strString);
        return strtolower($strString);
    }

    protected function generateAlias($strAlias, $intId)
    {
        $strAlias = substr( $strAlias, 0, strrpos( substr( $strAlias, 0, 100 ), ' ' ) ?: 100 );
        $strAlias = $this->standardize($strAlias);
        $this->dbconnect();
        $sql = "SELECT `id` from `tl_news` WHERE `alias` = $strAlias";
        $result = mysql_query($sql);
        if (mysql_num_rows($result) > 1)
        {
            // Alias vorhanden, ID wird angehängt
            $strAlias .= '-' . $intId;
        }
        return $strAlias;
    }

    protected function liesFeld ($row, $feld, $linksErsetzen = false) {
        $return = $row[$feld];
        // Links ersetzen
        $return = $this->ersetzeLinks($return);
        // alle Zeilenumbrüche entfernen und Sonderzeichen maskieren
        $return = mysql_real_escape_string($return);
        return $return;
    }

    protected function ersetzeLinks($strText) {
        // Bildlinks
    	$strSearch = '#src="images#';
    	$strReplace= 'src="tl_files/j';
    	$strText = preg_replace($strSearch, $strReplace, $strText);

        // Galerielinks
        $strSearch = '#index.php\?option=com_joomgallery.*"#';
    	$strReplace= 'fotogalerie.html"';
    	$strText = preg_replace($strSearch, $strReplace, $strText);
        
        return $strText;
    }

    function convert_datetime($str) {
        list($date, $time) = explode(' ', $str);
        list($year, $month, $day) = explode('-', $date);
        list($hour, $minute, $second) = explode(':', $time);
        $timestamp = mktime($hour, $minute, $second, $month, $day, $year);
        return $timestamp;
    }

    protected function konvertiereNews() {

        echo "konvertiereNews<br>";
        $this->dbconnect();
        $sql = "SELECT `title`, `alias`, `introtext`, `fulltext`, `sectionid`, `catid`, `created`, `created_by`, `modified`  FROM jos_content WHERE `sectionid` != ''";
        $result = mysql_query($sql);

        // Kontrollausgabe
        echo "Anzahl Zeilen: " . mysql_num_rows($result);

        $str = "INSERT INTO tl_news (`pid`, `tstamp`, `headline`, `alias`, `date`, `time`, `author`, `teaser`, `text`, `published`, `verein`) VALUES\n";
        $ersterDurchlauf = true; // für das Komma hinter den Value-Werten

        // ID simulieren für Alias-Generierung
        $id = 0;
        while ($row = mysql_fetch_array($result)) {

                // ID der News simulieren
                $id++;

                // Alias generieren gem. Contao-Konventionen
                //$alias = $this->generateAlias($row['alias'], $id);
                $alias = $row['alias'];

                // Section-ID von Joomla = News-Archiv (Sportart) in Contao (pid)
                $sectionid = $this->liesFeld($row, 'sectionid');
                $pid = $this->arrConfig['section'][$sectionid];

                // Titel
                $title = $this->liesFeld($row, 'title');
            
                // Teaser
                $teaser = $this->liesFeld($row, 'introtext', true);
                // Fix: 21 Teaser werden durch zuviel HTML - Tags komplett gelöscht
                // Wenn also kein Teaser übrig bleibt, wird eben HTML zugelassen
                if (strlen($teaser) == 0) $teaser = $this->liesFeld($row, 'introtext');

                // Konvertiere die Links
                //$teaser = $this->ersetzeLinks($teaser);

                // Text der News
                $text = $this->liesFeld($row, 'fulltext', true);
                // wenn kein Fulltext, dann nimm den Teaser
                if (strlen($text) == 0) $text = $teaser;

                // Konvertiere die Links
                //$text = $this->ersetzeLinks($text);

                // Datum
                $datum = $this->convert_datetime($row['created']);  // in einen Timestamp umwandeln

                // Author
                $createdby = $row['created_by'];
                $author = $this->arrConfig['authors'][$createdby];

                // Datum
                if ($row['modified'] != '0000-00-00 00:00:00') $datum = $this->convert_datetime($row['modified']);

                // Verein
                $catid = $this->liesFeld($row, 'catid');
                $verein = ($this->arrConfig['categories'][$catid]) ? $this->arrConfig['categories'][$catid] : 0;

                if ($ersterDurchlauf) $ersterDurchlauf = false; else $str.=",\n";
		    	$str.= "($pid, $datum, '$title', '$alias', $datum, $datum, '$author', '$teaser', '$text', '1', $verein)";

        }

        fwrite($this->datei, $str);
        $this->dbclose();
    }




    protected function tabellenLeeren() {
        // TL_News löschen
        $str = "DELETE FROM tl_news;\n";
        // Auto-Increment auf 1 zurücksetzen
        $str.= "ALTER TABLE `tl_news` AUTO_INCREMENT=1;\n\n";
        fwrite ($this->datei, $str);
    }

    public function start() {
        $this->config();
        $this->datei = fopen('j2contao.sql', 'w');
        $this->tabellenLeeren();
        $this->konvertiereNews();
        fclose($this->datei);

    }

}

$hs = new joomla2contao();
$hs->start();

?>