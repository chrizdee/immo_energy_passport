<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

// Add energy_pass fields

$GLOBALS['TL_DCA']['tl_immo_objects']['metapalettes']['_immo_base_']['details'] = str_replace('floor', 'floor, energy_value, energy_with_hot_water', $GLOBALS['TL_DCA']['tl_immo_objects']['metapalettes']['_immo_base_']['details']);

$GLOBALS['TL_DCA']['tl_immo_objects']['fields']['energy_value'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_immo_objects']['energy_value'],
	'inputType' => 'text',
	'exclude'   => true,
	'eval'      => array('rgxp'           => 'digit',
	                     'tl_class'       => 'w50',
	                     'feViewable'     => 'details',
	                     'exposeViewable' => 'details',
						 'feedViewable' => 'details')
);

$GLOBALS['TL_DCA']['tl_immo_objects']['fields']['energy_with_hot_water'] = array(
	'label'     => &$GLOBALS['TL_LANG']['tl_immo_objects']['energy_with_hot_water'],
	'inputType' => 'checkbox',
	'exclude'   => true,
	'eval'      => array('tl_class' => 'w50 m12',
						 'feViewable'     => 'details',
	                     'exposeViewable' => 'details',
						 'feedViewable' => 'details')
);

?>