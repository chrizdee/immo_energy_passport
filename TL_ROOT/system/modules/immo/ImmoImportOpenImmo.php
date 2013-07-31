<?php

/**
 * @copyright fruitMedia 2010,2011 <www.fruitmedia.de>
 * @author    Tristan Lins <tristan.lins@infinitysoft.de>
 * @package   ImmoManager
 * @license   EULA
 */


class ImmoImportOpenImmo extends ImmoImport
{
	/**
	 * Immo Service Name
	 *
	 * @var string
	 */
	protected $strServiceName = 'OpenImmo Import';

	/**
	 * Immo Service Key
	 *
	 * @var string
	 */
	protected $strServiceKey = 'openimmo';

	/**
	 * Import objects from a file.
	 *
	 * @param ImmoImportSource $source
	 *
	 * @return array
	 * Returns an array of actions and object data, or null if it cannot be handeled.
	 */
	public function importObjects(ImmoImportSource $source)
	{
		$strXML = $source->getInputFileContent();

		// remove namespace prefixes
		$strXML = preg_replace(
			'#(</?)(\w+):#',
			'$1',
			$strXML
		);

		// create the dom document
		$xmlDoc = new DOMDocument();

		// define own error handler, to catch XML errors
		set_error_handler('exception_error_handler');

		// load the XML
		try {
			$xmlDoc->loadXML($strXML);
		} catch (Exception $e) {
			// restore contao error handler
			restore_error_handler();

			throw $e;
		}

		// restore contao error handler
		restore_error_handler();

		$xmlRoot = $xmlDoc->documentElement;
		if ($xmlRoot->tagName != 'openimmo') {
			throw new Exception('Document is not an openimmo file: ' . $source->getInputFile());
		}

		if (!$xmlRoot->hasAttribute('xmlns')) {
			$xmlRoot->setAttribute('xmlns', 'http://www.openimmo.de');

			$strXML = $xmlDoc->saveXML();
			$xmlDoc->loadXML($strXML);
			$xmlRoot = $xmlDoc->documentElement;
		}

		$xmlXpath = new DOMXPath($xmlDoc);
		$xmlXpath->registerNamespace('i', $xmlRoot->getAttribute('xmlns'));

		$disabled = explode(',', ini_get('disable_functions'));
		$blnShowMemory = function_exists('memory_get_usage') && !in_array('memory_get_usage', $disabled);
		$blnShowMemoryPeak = function_exists('memory_get_peak_usage') && !in_array('memory_get_peak_usage', $disabled);

		/*
		$strArt = $xmlXpath->evaluate('string(i:uebertragung/@art)', $xmlRoot);
		$strUmfang = $xmlXpath->evaluate('string(i:uebertragung/@umfang)', $xmlRoot);
		$strModus = $xmlXpath->evaluate('string(i:uebertragung/@modus)', $xmlRoot);
		*/

		$arrObjects = array();

		$xmlImmobilien = $xmlXpath->query('i:anbieter/i:immobilie', $xmlRoot);

		$this->log(sprintf('Found %d immo nodes in %s', $xmlImmobilien->length, $source->getInputFile()),
			'ImmoImportOpenImmo::importObjects',
			'TL_INFO');

		if (!is_array($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'])) {
			$GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'] = deserialize($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'], true);
		}

		if (!is_array($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'])) {
			$GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'] = deserialize($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'], true);
		}

		for ($i = 0; $i < $xmlImmobilien->length; $i++) {
			$arrImmobilie = array();

			$xmlImmobilie = $xmlImmobilien->item($i);

			// Parse objektkategorie
			$xmlObjektkategorieNodes = $xmlXpath->query('i:objektkategorie', $xmlImmobilie);
			if ($xmlObjektkategorieNodes->length) {
				$xmlObjektkategorie = $xmlObjektkategorieNodes->item(0);

				/*
				// Parse nutzungsart
				$xmlNutzungsart = $xmlXpath->query('i:nutzungsart', $xmlObjektkategorie)->item(0);
				{
					$blnWohnen = $xmlXpath->evaluate('boolean(@WOHNEN="true")', $xmlNutzungsart);
					$blnGewerbe = $xmlXpath->evaluate('boolean(@GEWERBE="true")', $xmlNutzungsart);
				}
				*/

				$strTypeKey = 'rent';

				// Parse vermarktungsart
				$xmlVermarktungsartNodes = $xmlXpath->query('i:vermarktungsart', $xmlObjektkategorie);
				if ($xmlVermarktungsartNodes->length) {
					$xmlVermarktungsart = $xmlVermarktungsartNodes->item(0);

					$blnKauf       = $xmlXpath->evaluate('boolean(@KAUF="true")', $xmlVermarktungsart);
					$blnMietePacht = $xmlXpath->evaluate('boolean(@MIETE_PACHT="true")', $xmlVermarktungsart);
					$blnErbpacht   = $xmlXpath->evaluate('boolean(@ERBPACHT="true")', $xmlVermarktungsart);
					$blnLeasing    = $xmlXpath->evaluate('boolean(@LEASING="true")', $xmlVermarktungsart);

					$strTypeKey = $blnKauf ? 'sell' : 'rent';

					unset($xmlVermarktungsart);
				}
				unset($xmlVermarktungsartNodes);

				// init the pid field
				$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping']['*'][$strTypeKey];

				// init the immotype field
				$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping']['*'][$strTypeKey];

				// Parse objektart
				$xmlObjektart = $xmlXpath->query('i:objektart', $xmlObjektkategorie)->item(0);
				{
					// Parse zimmer
					$xmlZimmerNodes = $xmlXpath->query('i:zimmer', $xmlObjektart);
					if ($xmlZimmerNodes->length) {
						$xmlZimmer = $xmlZimmerNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@zimmertyp)', $xmlZimmer);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'zimmer:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'zimmer:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'zimmer:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'zimmer:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlZimmer);
					}
					unset($xmlZimmerNodes);

					// Parse wohnung
					$xmlWohnungNodes = $xmlXpath->query('i:wohnung', $xmlObjektart);
					if ($xmlWohnungNodes->length) {
						$xmlWohnung = $xmlWohnungNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@wohnungtyp)', $xmlWohnung);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'wohnung:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'wohnung:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'wohnung:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'wohnung:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlWohnung);
					}
					unset($xmlWohnungNodes);

					// Parse haus
					$xmlHausNodes = $xmlXpath->query('i:haus', $xmlObjektart);
					if ($xmlHausNodes->length) {
						$xmlHaus = $xmlHausNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@haustyp)', $xmlHaus);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'haus:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'haus:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'haus:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'haus:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlHaus);
					}
					unset($xmlHausNodes);

					// Parse grundstueck
					$xmlGrundstueckNodes = $xmlXpath->query('i:grundstueck', $xmlObjektart);
					if ($xmlGrundstueckNodes->length) {
						$xmlGrundstueck = $xmlGrundstueckNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@grundst_typ)', $xmlGrundstueck);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'grundstueck:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'grundstueck:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'grundstueck:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'grundstueck:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlGrundstueck);
					}
					unset($xmlGrundstueckNodes);

					// Parse buero_praxen
					$xmlBueroPraxenNodes = $xmlXpath->query('i:buero_praxen', $xmlObjektart);
					if ($xmlBueroPraxenNodes->length) {
						$xmlBueroPraxen = $xmlBueroPraxenNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@buero_typ)', $xmlBueroPraxen);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'buero_praxen:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'buero_praxen:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'buero_praxen:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'buero_praxen:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlBueroPraxen);
					}
					unset($xmlBueroPraxenNodes);

					// Parse einzelhandel
					$xmlEinzelhandelNodes = $xmlXpath->query('i:einzelhandel', $xmlObjektart);
					if ($xmlEinzelhandelNodes->length) {
						$xmlEinzelhandel = $xmlEinzelhandelNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@handel_typ)', $xmlEinzelhandel);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'einzelhandel:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'einzelhandel:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'einzelhandel:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'einzelhandel:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlEinzelhandel);
					}
					unset($xmlEinzelhandelNodes);

					// Parse gastgewerbe
					$xmlGastgewerbeNodes = $xmlXpath->query('i:gastgewerbe', $xmlObjektart);
					if ($xmlGastgewerbeNodes->length) {
						$xmlGastgewerbe = $xmlGastgewerbeNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@gastgew_typ)', $xmlGastgewerbe);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'gastgewerbe:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'gastgewerbe:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'gastgewerbe:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'gastgewerbe:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlGastgewerbe);
					}
					unset($xmlGastgewerbeNodes);

					// Parse hallen_lager_prod
					$xmlHallenLagerProdNodes = $xmlXpath->query('i:hallen_lager_prod', $xmlObjektart);
					if ($xmlHallenLagerProdNodes->length) {
						$xmlHallenLagerProd = $xmlHallenLagerProdNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@hallen_typ)', $xmlHallenLagerProd);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'hallen_lager_prod:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'hallen_lager_prod:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'hallen_lager_prod:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'hallen_lager_prod:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlHallenLagerProd);
					}
					unset($xmlHallenLagerProdNodes);

					// Parse land_und_forstwirtschaft
					$xmlLandUndForstwirtschaftNodes = $xmlXpath->query('i:land_und_forstwirtschaft', $xmlObjektart);
					if ($xmlLandUndForstwirtschaftNodes->length) {
						$xmlLandUndForstwirtschaft = $xmlLandUndForstwirtschaftNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@land_typ)', $xmlLandUndForstwirtschaft);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'land_und_forstwirtschaft:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'land_und_forstwirtschaft:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'land_und_forstwirtschaft:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'land_und_forstwirtschaft:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlLandUndForstwirtschaft);
					}
					unset($xmlLandUndForstwirtschaftNodes);

					// Parse parken
					$xmlParkenNodes = $xmlXpath->query('i:parken', $xmlObjektart);
					if ($xmlParkenNodes->length) {
						$xmlParken = $xmlParkenNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@parken_typ)', $xmlParken);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'parken:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'parken:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'parken:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'parken:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlParken);
					}
					unset($xmlParkenNodes);

					// Parse sonstige
					$xmlSonstigeNodes = $xmlXpath->query('i:sonstige', $xmlObjektart);
					if ($xmlSonstigeNodes->length) {
						$xmlSonstige = $xmlSonstigeNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@sonstige_typ)', $xmlSonstige);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'sonstige:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'sonstige:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'sonstige:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'sonstige:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlSonstige);
					}
					unset($xmlSonstigeNodes);

					// Parse freizeitimmobilie_gewerblich
					$xmlFreizeitimmobilieGewerblichNodes = $xmlXpath->query('i:freizeitimmobilie_gewerblich', $xmlObjektart);
					if ($xmlFreizeitimmobilieGewerblichNodes->length) {
						$xmlFreizeitimmobilieGewerblich = $xmlFreizeitimmobilieGewerblichNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@freizeit_typ)', $xmlFreizeitimmobilieGewerblich);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'freizeitimmobilie_gewerblich:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'freizeitimmobilie_gewerblich:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'freizeitimmobilie_gewerblich:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'freizeitimmobilie_gewerblich:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlFreizeitimmobilieGewerblich);
					}
					unset($xmlFreizeitimmobilieGewerblichNodes);

					// Parse zinshaus_renditeobjekt
					$xmlZinshausRenditeobjektNodes = $xmlXpath->query('i:zinshaus_renditeobjekt', $xmlObjektart);
					if ($xmlZinshausRenditeobjektNodes->length) {
						$xmlZinshausRenditeobjekt = $xmlZinshausRenditeobjektNodes->item(0);

						$strTyp = $xmlXpath->evaluate('string(@zins_typ)', $xmlZinshausRenditeobjekt);

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'zinshaus_renditeobjekt:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey = 'zinshaus_renditeobjekt:*'][$strTypeKey])) {
							$arrImmobilie['pid'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_category_mapping'][$strKey][$strTypeKey];
						}

						if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'zinshaus_renditeobjekt:' . $strTyp][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}
						else if (!empty($GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey = 'zinshaus_renditeobjekt:*'][$strTypeKey])) {
							$arrImmobilie['immotype'] = $GLOBALS['TL_CONFIG']['immo_import_openimmo_type_mapping'][$strKey][$strTypeKey];
						}

						unset($xmlZinshausRenditeobjekt);
					}
					unset($xmlZinshausRenditeobjektNodes);
				}

				list($arrImmobilie['immotype'], $arrImmobilie['objecttype']) = explode(':', $arrImmobilie['immotype'], 2);

				unset($xmlObjektkategorie);
			}
			unset($xmlObjektkategorieNodes);

			// Parse geo
			$xmlGEONodes = $xmlXpath->query('i:geo', $xmlImmobilie);
			if ($xmlGEONodes->length) {
				$xmlGEO = $xmlGEONodes->item(0);

				$arrImmobilie['street']       = $xmlXpath->evaluate('string(i:strasse/text())', $xmlGEO);
				$arrImmobilie['house_number'] = $xmlXpath->evaluate('string(i:hausnummer/text())', $xmlGEO);
				$arrImmobilie['postal']       = $xmlXpath->evaluate('string(i:plz/text())', $xmlGEO);
				$arrImmobilie['region']       = $xmlXpath->evaluate('string(i:ort/text())', $xmlGEO);
				$arrImmobilie['country']      = $xmlXpath->evaluate('string(i:land/@iso_land)', $xmlGEO);
				$arrImmobilie['map']          = $xmlXpath->evaluate('string(i:geokoordinaten/@breitengrad)', $xmlGEO) . ',' . $xmlXpath->evaluate('string(geokoordinaten/@laengengrad)', $xmlGEO);

				$arrImmobilie['stories'] = $xmlXpath->evaluate('string(i:anzahl_etagen/text())', $xmlGEO);
				$arrImmobilie['floor']   = $xmlXpath->evaluate('string(i:etage/text())', $xmlGEO);

				unset($xmlGEO);
			}
			unset($xmlGEONodes);

			// Parse kontaktperson
			$xmlKontaktpersonNodes = $xmlXpath->query('i:kontaktperson', $xmlImmobilie);
			if ($xmlKontaktpersonNodes->length) {
				$xmlKontaktperson = $xmlKontaktpersonNodes->item(0);

				$arrContact              = array();
				// not supported -> $arrContact['title']     = $xmlXpath->evaluate('string(titel/text())', $xmlKontaktperson);
				$arrContact['firstname'] = $xmlXpath->evaluate('string(i:vorname/text())', $xmlKontaktperson);
				$arrContact['lastname']  = $xmlXpath->evaluate('string(i:name/text())', $xmlKontaktperson);
				$arrContact['gender']    = $xmlXpath->evaluate('string(i:anrede/text())', $xmlKontaktperson);
				switch (strtolower($arrContact['gender'])) {
					case 'herr':
						$arrContact['gender'] = 'male';
						break;
					case 'frau':
						$arrContact['gender'] = 'female';
						break;
					default:
						$arrContact['gender'] = '';
				}
				$arrContact['company'] = $xmlXpath->evaluate('string(i:firma/text())', $xmlKontaktperson);

				if ($xmlXpath->evaluate('boolean(i:tel_durchw)', $xmlKontaktperson)) {
					$arrContact['phone']    = $xmlXpath->evaluate('string(i:tel_durchw/text())', $xmlKontaktperson);
				}
				else {
					$arrContact['phone']    = $xmlXpath->evaluate('string(i:tel_zentrale/text())', $xmlKontaktperson);
				}
				$arrContact['mobile']   = $xmlXpath->evaluate('string(i:tel_handy/text())', $xmlKontaktperson);
				$arrContact['fax']      = $xmlXpath->evaluate('string(i:tel_fax/text())', $xmlKontaktperson);
				if ($xmlXpath->evaluate('boolean(i:strasse)', $xmlKontaktperson)) {
					$arrContact['email']    = $xmlXpath->evaluate('string(i:email_direkt/text())', $xmlKontaktperson);
				}
				else {
					$arrContact['email']    = $xmlXpath->evaluate('string(i:email_zentrale/text())', $xmlKontaktperson);
				}
				$arrContact['homepage'] = $xmlXpath->evaluate('string(i:url/text())', $xmlKontaktperson);

				$arrContact['street']       = $xmlXpath->evaluate('string(i:strasse/text())', $xmlKontaktperson);
				$arrContact['house_number'] = $xmlXpath->evaluate('string(i:hausnummer/text())', $xmlKontaktperson);
				$arrContact['postal']       = $xmlXpath->evaluate('string(i:plz/text())', $xmlKontaktperson);
				$arrContact['city']         = $xmlXpath->evaluate('string(i:ort/text())', $xmlKontaktperson);
				$arrContact['country']      = $xmlXpath->evaluate('string(i:land/text())', $xmlKontaktperson);

				$arrImmobilie['contact'] = $arrContact;

				unset($xmlKontaktperson);
			}
			unset($xmlKontaktpersonNodes);

			// --> weitere_adresse

			// Parse preise
			$xmlPreiseNodes = $xmlXpath->query('i:preise', $xmlImmobilie);
			if ($xmlPreiseNodes->length) {
				$xmlPreise = $xmlPreiseNodes->item(0);

				if ($xmlXpath->evaluate('boolean(i:kaufpreis)', $xmlPreise)) {
					$arrImmobilie['price'] = floatval($xmlXpath->evaluate('string(i:kaufpreis)', $xmlPreise));
				}
				if ($xmlXpath->evaluate('boolean(i:nettokaltmiete)', $xmlPreise)) {
					$arrImmobilie['lease'] = floatval($xmlXpath->evaluate('string(i:nettokaltmiete)', $xmlPreise));
				}
				else if ($xmlXpath->evaluate('boolean(i:kaltmiete)', $xmlPreise)) {
					$arrImmobilie['lease'] = floatval($xmlXpath->evaluate('string(i:kaltmiete)', $xmlPreise));
				}
				else if ($xmlXpath->evaluate('boolean(i:warmmiete)', $xmlPreise)) {
					$arrImmobilie['lease']   = floatval($xmlXpath->evaluate('string(i:warmmiete)', $xmlPreise));
					$arrImmobilie['heating'] = '0';
				}
				else if ($xmlXpath->evaluate('boolean(i:mietpreis_pro_qm)', $xmlPreise)) {
					$arrImmobilie['lease']    = floatval($xmlXpath->evaluate('string(i:mietpreis_pro_qm)', $xmlPreise));
					$arrImmobilie['leasePer'] = 'perSurface';
				}
				if ($xmlXpath->evaluate('boolean(i:nebenkosten)', $xmlPreise)) {
					$arrImmobilie['incidentals'] = floatval($xmlXpath->evaluate('string(i:nebenkosten)', $xmlPreise));
                    $arrImmobilie['incidentalsPer'] = 'perMonth';
				}
				if ($xmlXpath->evaluate('boolean(i:heizkosten_enthalten="true")', $xmlPreise)) {
					$arrImmobilie['heating'] = '0';
				}
				if ($xmlXpath->evaluate('boolean(i:heizkosten)', $xmlPreise)) {
					$arrImmobilie['heating'] = floatval($xmlXpath->evaluate('string(i:heizkosten)', $xmlPreise));
				}
				if ($xmlXpath->evaluate('boolean(i:pacht)', $xmlPreise)) {
					$arrImmobilie['leasehold'] = floatval($xmlXpath->evaluate('string(i:pacht)', $xmlPreise));
				}
				if ($xmlXpath->evaluate('boolean(i:hausgeld)', $xmlPreise)) {
					$arrImmobilie['allowanceToInmates'] = floatval($xmlXpath->evaluate('string(i:hausgeld)', $xmlPreise));
				}

				if ($xmlXpath->evaluate('boolean(i:aussen_courtage)', $xmlPreise)) {
					$arrImmobilie['provisionValue'] = $xmlXpath->evaluate('string(i:aussen_courtage)', $xmlPreise);
                    $arrImmobilie['provisionValue'] = preg_replace('#(\d+)\.(\d+)#', '$1,$2', $arrImmobilie['provisionValue']);

					if ($xmlXpath->evaluate('boolean(i:aussen_courtage/@mit_mwst)', $xmlPreise)) {
						$arrImmobilie['provisionMwSt'] = false;
					}
                    else {
                        $arrImmobilie['provisionMwSt'] = true;
                    }
				}
				if ($xmlXpath->evaluate('boolean(i:courtage_hinweis)', $xmlPreise)) {
					$arrImmobilie['provision'] = $xmlXpath->evaluate('string(i:courtage_hinweis)', $xmlPreise);
				}

				if ($xmlXpath->evaluate('boolean(i:kaution)', $xmlPreise)) {
					$arrImmobilie['deposit'] = array(
                        'value' => $xmlXpath->evaluate('string(i:kaution)', $xmlPreise),
                        'unit'  => $GLOBALS['TL_CONFIG']['immo_import_depositPer']
                    );
                    $arrImmobilie['deposit']['value'] = preg_replace('#(\d+)\.(\d+)#', '$1,$2', $arrImmobilie['deposit']['value']);
                    $arrImmobilie['deposit'] = serialize($arrImmobilie['deposit']);
				}

				unset($xmlPreise);
			}
			unset($xmlPreiseNodes);

			// --> bieterverfahren

			// --> versteigerung

			// Parse flaechen
			$xmlFlaechenNodes = $xmlXpath->query('i:flaechen', $xmlImmobilie);
			if ($xmlFlaechenNodes->length) {
				$xmlFlaechen = $xmlFlaechenNodes->item(0);

				if ($xmlXpath->evaluate('boolean(i:wohnflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface'] = floatval($xmlXpath->evaluate('string(i:wohnflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:gesamtflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface2'] = floatval($xmlXpath->evaluate('string(i:gesamtflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:ladenflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface_salesarea'] = floatval($xmlXpath->evaluate('string(i:ladenflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:lagerflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface_production'] = floatval($xmlXpath->evaluate('string(i:lagerflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:verkaufsflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface_salesarea'] = floatval($xmlXpath->evaluate('string(i:verkaufsflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:bueroflaeche)', $xmlFlaechen)) {
					$arrImmobilie['surface_office'] = floatval($xmlXpath->evaluate('string(i:bueroflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:grundstuecksflaeche)', $xmlFlaechen)) {
					$arrImmobilie['land_area'] = floatval($xmlXpath->evaluate('string(i:grundstuecksflaeche)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:anzahl_zimmer)', $xmlFlaechen)) {
					$arrImmobilie['rooms'] = floatval($xmlXpath->evaluate('string(i:anzahl_zimmer)', $xmlFlaechen));
				}
				if ($xmlXpath->evaluate('boolean(i:teilbar_ab)', $xmlFlaechen)) {
					$arrImmobilie['surface_office_split']     = floatval($xmlXpath->evaluate('string(i:teilbar_ab)', $xmlFlaechen));
					$arrImmobilie['surface_production_split'] = floatval($xmlXpath->evaluate('string(i:teilbar_ab)', $xmlFlaechen));
					$arrImmobilie['surface_salesarea_split']  = floatval($xmlXpath->evaluate('string(i:teilbar_ab)', $xmlFlaechen));
				}

				unset($xmlFlaechen);
			}
			unset($xmlFlaechenNodes);

			// --> ausstattung

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

			// --> bewertung

			// --> infrastruktur

			// Parse freitexte
			$xmlFreitexteNodes = $xmlXpath->query('i:freitexte', $xmlImmobilie);
			if ($xmlFreitexteNodes->length) {
				$xmlFreitexte = $xmlFreitexteNodes->item(0);

				if ($xmlXpath->evaluate('boolean(i:objekttitel)', $xmlFreitexte)) {
					$arrImmobilie['title'] = $xmlXpath->evaluate('string(i:objekttitel)', $xmlFreitexte);
				}
				if ($xmlXpath->evaluate('boolean(i:lage)', $xmlFreitexte)) {
					$arrImmobilie['placedetails'] = ImmoImport::plain2html($xmlXpath->evaluate('string(i:lage)', $xmlFreitexte));
				}
				if ($xmlXpath->evaluate('boolean(i:ausstatt_beschr)', $xmlFreitexte)) {
					$arrImmobilie['equipment'] = ImmoImport::plain2html($xmlXpath->evaluate('string(i:ausstatt_beschr)', $xmlFreitexte));
				}
				if ($xmlXpath->evaluate('boolean(i:objektbeschreibung)', $xmlFreitexte)) {
					$arrImmobilie['description'] = ImmoImport::plain2html($xmlXpath->evaluate('string(i:objektbeschreibung)', $xmlFreitexte));
				}
				if ($xmlXpath->evaluate('boolean(i:sonstige_angaben)', $xmlFreitexte)) {
					$arrImmobilie['miscellaneous'] = ImmoImport::plain2html($xmlXpath->evaluate('string(i:sonstige_angaben)', $xmlFreitexte));
				}

				unset($xmlFreitexte);
			}
			unset($xmlFreitexteNodes);

			// Parse anhaenge
			$arrImmobilie['media'] = array();
			$xmlAnhaengeNodes      = $xmlXpath->query('i:anhaenge/i:anhang', $xmlImmobilie);
			for ($j = 0; $j < $xmlAnhaengeNodes->length; $j++) {
				$xmlAnhang   = $xmlAnhaengeNodes->item($j);
				$xmlDaten    = $xmlXpath->query('i:daten', $xmlAnhang)->item(0);
				$strFormat   = strtolower($xmlXpath->evaluate('string(i:format/text())', $xmlAnhang));
				$strLocation = $xmlXpath->evaluate('string(@location)', $xmlAnhang);

				if ($xmlXpath->evaluate('boolean(i:anhanginhalt)', $xmlDaten)) {
					$varBin = base64_decode($xmlXpath->evaluate('string(i:anhanginhalt/text())', $xmlDaten));
				} else if ($strLocation == 'EXTERN') {
					$varBin = $source->getFile($xmlXpath->evaluate('string(i:pfad/text())', $xmlDaten));
				} else if ($strLocation == 'INTERN') {
					$varBin = base64_decode($xmlXpath->evaluate('string(i:anhanginhalt/text())', $xmlDaten));
				} else {
					$varBin = false;
				}

				if (!$varBin) {
					unset($xmlAnhang, $xmlDaten, $strFormat, $strLocation);
					continue;
				}

				$strTempFile = tempnam(sys_get_temp_dir(), 'immo_import_');
				file_put_contents($strTempFile, $varBin);

				// attached file is PDF
				if (preg_match('#^application/(x-)?pdf#', $strFormat) || $strFormat == 'PDF') {
					$arrImmobilie['fileSRC'] = $strTempFile;
				}

				// attached file is image
				else if (
					preg_match('#^image/#', $strFormat) ||
					$strFormat == 'jpg' ||
					$strFormat == 'jpeg' ||
					$strFormat == 'png' ||
					$strFormat == 'gif' ||
					$strFormat == 'bmp'
				) {
					$arrMedia              = array();
					$arrMedia['extension'] = $strFormat;
					$arrMedia['title']     = $xmlXpath->evaluate('string(i:anhangtitel/text())', $xmlAnhang);
					$arrMedia['singleSRC'] = $strTempFile;

					switch ($xmlXpath->evaluate('string(@gruppe)', $xmlAnhang)) {
						case 'GRUNDRISS':
							$arrMedia['mtype'] = 'layout';
							break;
						case 'TITELBILD':
							$arrMedia['mtype']      = 'main';
							$arrMedia['titleImage'] = true;
							break;
						default:
							$arrMedia['mtype'] = 'gallery';
							break;
					}
					$arrMedia['published'] = 1;

					$arrImmobilie['media'][] = $arrMedia;
				}

				// attached file is unknown
				else {
					$this->log('Do not know, how to handle attachment type ' . $strFormat, 'ImmoImport', 'TL_ERROR');
				}

				unset($xmlAnhang, $xmlDaten, $strFormat, $strLocation, $varBin);
			}
			unset($xmlAnhaengeNodes);

			// --> verwaltung_objekt

			// Parse verwaltung_techn
			$xmlVerwaltungTechnNodes = $xmlXpath->query('i:verwaltung_techn', $xmlImmobilie);
			if ($xmlVerwaltungTechnNodes->length) {
				$xmlVerwaltungTechn = $xmlVerwaltungTechnNodes->item(0);

				$arrImmobilie['objectid']  = $xmlXpath->evaluate('string(i:objektnr_extern/text())', $xmlVerwaltungTechn);
				$arrImmobilie['__do__']    = strtoupper($xmlXpath->evaluate('string(i:aktion/@aktionart)', $xmlVerwaltungTechn));
				$arrImmobilie['import_id'] = $xmlXpath->evaluate('string(i:openimmo_obid/text())', $xmlVerwaltungTechn);

				unset($xmlVerwaltungTechn);
			}
			unset($xmlVerwaltungTechnNodes);

			$this->log(sprintf('parse Immobilie %s (%s), memory usage %s (%d), peak %s (%d)',
					$arrImmobilie['objectid'],
					$arrImmobilie['__do__'],
					$blnShowMemory ? $this->getReadableSize(memory_get_usage()) : '-',
					$blnShowMemory ? memory_get_usage() : '-',
					$blnShowMemoryPeak ? $this->getReadableSize(memory_get_peak_usage()) : '-',
					$blnShowMemoryPeak ? memory_get_peak_usage() : '-'),
				'ImmoImportOpenImmo::importObjects',
				'TL_INFO');

			$arrObjects[] = $arrImmobilie;

			unset($xmlImmobilie, $arrImmobilie);
		}

		unset($xmlXpath, $xmlDoc);

		return $arrObjects;
	}
}
