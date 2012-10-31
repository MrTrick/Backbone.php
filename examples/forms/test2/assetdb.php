<?php
$assets = array (
					 array("AssetName" => "Printer", "serial" => "hp2tr1gf7", "make" => "hp4520"),
					 array("AssetName" => "Printer", "serial" => "hpg94yg7h", "make" => "hp6336"),
					 array("AssetName" => "scanner", "serial" => "esp98757", "make" => "epsonTT"),
					 array("AssetName" => "scanner", "serial" => "epdsj", "make" => "epsonXX"),
					 array("AssetName" => "Printer", "serial" => "canprt0067", "make" => "canon6657"),
					 array("AssetName" => "laptop", "serial" => "l14r14r", "make" => "dell-inspiron"),
					 array("AssetName" => "laptop", "serial" => "l14521", "make" => "hp-vp736"),
					 array("AssetName" => "laptop", "serial" => "len74658", "make" => "lenovo-thinkpad"),
					 array("AssetName" => "laptop", "serial" => "leng7634", "make" => "lenovo-G560"),
					 array("AssetName" => "eReader", "serial" => "son982392", "make" => "Sony-Ereader"),
					 array("AssetName" => "camera", "serial" => "nik874912", "make" => "Nikon-D300"),
					 array("AssetName" => "camera", "serial" => "nik8trwww", "make" => "Nikon-DX1"),
					 array("AssetName" => "camera", "serial" => "can7jhwqhgf", "make" => "canon 7D"),
					 array("AssetName" => "camera", "serial" => "can5aifq", "make" => "canon 5D"),
					 array("AssetName" => "phone", "serial" => "apjdhgw", "make" => "Apple-iPad"),
					 array("AssetName" => "phone", "serial" => "ap4sy24tg", "make" => "Apple-iPhone 4s")
				);

$q=$_GET["q"];



if(!$q)
{
	echo '[]';
}
else{
	
	echo json_encode( array_filter($assets,function($val){ 
						$pos = strpos(strtolower($val["make"]),strtolower($_GET["q"]));	
						$pos2 = strpos(strtolower($val["AssetName"]),strtolower($_GET["q"]));	
						return (( $pos !== false)||($pos2 !== false));
	}));
}