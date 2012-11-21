<?php

$xml_file_dir = '../../../../data';
if(file_exists($xml_file_dir)) { 
	echo "Directory exists";
} else {
	throw new Exception("Directory could not be found");
}

?>