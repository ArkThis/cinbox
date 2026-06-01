<?php

// This file is used to add custom character in/out mappings for Task:
// "CleanFilenames"

// You can assign as many arrays to keys within it as you like to add custom
// mappings. The key used to assign your mapping to $charMappingsCustom will
// then be available as selection in 'CLEAN_SOURCE' config option.

// The variable name here MUST be 'charMappingsCustom'.
$charMappingsCustom['custom1'] = array(
    "µ" => "mu",
    "@" => "(at)",
    "&" => "+",
);

/* Add more definitions, with different 'customX' for more mappings:
$charMappingsCustom['customX'] = array(
    "a" => "b",
    "c" => "d",
    "e" => "f",
);
 */

?>
