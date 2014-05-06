immo_energy_passport
====================

Im Contao Immomanager http://www.contao-immomanager.de (Version 2.2.0) stehen keine Felder für den Energiebedarf laut Energieausweis zur Verfügung.

immo_energy_passport ergänzt diese Felder in der Datenbank. Über Modifikationen in der Datei system/modules/immo/ImmoImportOpenImmo.php können die neuen Felder auch über die OpenImmo-Schnittstelle importiert werden.

In system/modules/immo/Import/ImmoImportOpenImmo.php ca. Zeile 685:
```php
// Parse zustand_angaben
$xmlZustandAngabenNodes = $xmlXpath->query('i:zustand_angaben', $xmlImmobilie);
if ($xmlZustandAngabenNodes->length) {
	$xmlZustandAngaben = $xmlZustandAngabenNodes->item(0);
 
	if ($xmlXpath->evaluate('boolean(i:baujahr)', $xmlZustandAngaben)) {
		$arrImmobilie['yoc'] = floatval($xmlXpath->evaluate('string(i:baujahr)', $xmlZustandAngaben));
	}
 
	// START MOD RSM --------------------------------------
	// Parse Energiepass
	$xmlEnergiepassNodes = $xmlXpath->query('i:energiepass', $xmlZustandAngaben);
	if ($xmlEnergiepassNodes->length) {
 
		$xmlEnergiepass = $xmlEnergiepassNodes->item(0);
		if ($xmlXpath->evaluate('boolean(i:energieverbrauchkennwert)', $xmlEnergiepass)) {
			$arrImmobilie['energy_value'] = floatval($xmlXpath->evaluate('string(i:energieverbrauchkennwert)', $xmlEnergiepass));
		}
 
		if ($xmlXpath->evaluate('string(i:mitwarmwasser)', $xmlEnergiepass) == 'true') {
			$arrImmobilie['energy_with_hot_water'] = true;
		}
 
		unset($xmlEnergiepass);
	}
	unset($xmlEnergiepassNodes);
	// END MOD RSM --------------------------------------
 
	unset($xmlZustandAngaben);
}
unset($xmlZustandAngabenNodes);
```