<?php

$persons = array (
					 array("name" => "J Esslemont", "id" => "js1335", "age" => "55"),
					 array("name" => "W Sears", "id" => "ws73746", "age" => "65"),
					 array("name" => "Sir T Moore", "id" => "tm9938", "age" => "50"),
					 array("name" => "Farid Attar", "id" => "fa6637", "age" => "70"),
					 array("name" => "Zigmond Freud", "id" => "zi764736", "age" => "30"),
					 array("name" => "Albert Enstien", "id" => "al6363", "age" => "45"),
					 array("name" => "Amadeus Mozart", "id" => "am7374", "age" => "26"),
					 array("name" => "Steven Spilberg", "id" => "st6452", "age" => "16"),
					 array("name" => "Tom Hanks", "id" => "to13245", "age" => "58"),
					 array("name" => "Judie Foster", "id" => "ju5415", "age" => "63"),
					 array("name" => "Kate Winslet", "id" => "ka9475", "age" => "28"),
					 array("name" => "Roger Federer", "id" => "ro10455", "age" => "43"),
					 array("name" => "Andy Murray", "id" => "an74561", "age" => "25"),
					 array("name" => "Bahram Beyzaie ", "id" => "sh2541", "age" => "37"),
					 array("name" => "Abbas Milani", "id" => "ab9999", "age" => "22"),
					 array("name" => "Pouyan Mood", "id" => "po4531", "age" => "35"),
					 array("name" => "Oscar Wilde", "id" => "ow84848" , "age" => "38")
				);

$q=$_GET["q"];



if(!$q)
{
	echo '[]';
}
else{
	
	echo json_encode( array_filter($persons,function($val){ 
						$pos = strpos(strtolower($val["name"]),strtolower($_GET["q"]));	
						return ( $pos !== false);
	}));
}