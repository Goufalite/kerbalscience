<?php
include "config.inc.php";

require_once 'vendor/autoload.php';
use \YaLinqo\Enumerable;

class ScienceElement {
	
	function __construct($body, $where, $what, $how, $asteroid)
	{
		$this->where = $where;
		$this->what = $what;
		$this->how = $how;
		$this->body = $body;
		$this->many = 0;
		$this->asteroid = $asteroid;
		$this->retrieved = 0;		
	}
	public $body;
	public $where;
	public $what;
	public $many;
	public $how;
	public $asteroid;
}

$bodies = json_decode(file_get_contents("bodies.json"));
if (!defined("SAVEFILE"))
{
	echo "
<form enctype='multipart/form-data' action='' method='post'>
  <input type='hidden' name='MAX_FILE_SIZE' value='12000000' />
  Upload your save file : <input name='savefile' type='file' />
  <input type='submit' value='Submit' />
</form>";
if (count($_FILES)==0) exit();
	try {
		$f = fopen($_FILES['savefile']['tmp_name'],"r");
	}
	catch (Exception $e)
	{
		die($e);
	}
} else {
	
	try{
		$f = fopen(SAVEFILE,"r");
	}
	catch (Exception $e)
	{
		die($e);
	}
}
if (!$f)
{
	die("Bad opening file...");
}

$inScience = false;
$foundScience = false;

// table for all sciences found
$science = array();
$situationsList = array();
foreach ($bodies->situations as $v)
{
	$situationsList[] = $v->name;
}
$situations = join(array_merge($situationsList,$bodies->recoveries),"|");
if (LOCALE!="")
{
	$bodiestranslated = from($bodies->bodies)->selectMany('$v ==> $v->translation')->select('$v ==> $v->{constant("LOCALE")}')->toList();
	$myTranslationSeparator = from($bodies->translationSeparator)->where('($tr,$k) ==> $k == constant("LOCALE")')->selectMany('($tr,$k) ==> $tr')->firstOrDefault();
}

$what = "";
$body = "";
$how = "";
$where = "";

while ($l = fgets($f))
{
	if (preg_match("@^\\t\\tScience@",$l))
	{
		$inScience = true;
		$foundScience = true;
	}
	$matches = array();
	// atmosphereAnalysis@KerbinSrfLandedKSC
	if (preg_match("/id = ([A-Za-z0-9]+)\@([A-Z][a-z0-9]+)(".$situations.")([A-Za-z0-9&]*)(_[A-Za-z0-9&]*)?/",$l,$matches) 
		&& $inScience)
	{
		if (!isset($matches[4]) || $matches[4]=="")
		{
			$matches[4]="Global";
		}
		if (!isset($matches[5])) {
			$matches[5] = "";
		}
		$what = $matches[1];
		$body = $matches[2];
		$how = $matches[3];
		$where = $matches[4];
		$asteroid = $matches[5];
		$newScience = new ScienceElement($body, $where, $what, $how, $asteroid);
		$science[] = $newScience;
		foreach ($bodies->bodies as $i => $b)
		{
			if ($b->name == $body)
			{
				if (!in_array($where,$b->biomes))
				{
					$b->biomes[] = $where;
					sort($b->biomes);
				}
				if (!in_array($what,$bodies->foundScienceType) && $what != "recovery")
				{
					$bodies->foundScienceType[] = $what;
				}
				if (!in_array($body,$bodies->foundBodies))
				{
					$bodies->foundBodies[] = $body;
				}
			}
		}
		
	}
	
	// maximum science
	if (preg_match("/cap = ([0-9.]+)/",$l,$matches) && $inScience)
	{
		$end = end($science);
		$end->many = round($matches[1],2);
		$inScience = false;
		
		$what = "";
		$body = "";
		$how = "";
		$where = "";
	}
	
	// retrieved science
	if (preg_match("/sci = ([0-9.]+)/",$l,$matches) && $inScience)
	{
		$end = end($science);
		$end->retrieved = round($matches[1],2);
	}
	
	// translate biome
	if (LOCALE != "" && preg_match("/title = .*".$myTranslationSeparator."(.*?)".$myTranslationSeparator."(.*?)\\r\\n$/",$l,$matches) && $inScience)
	{
		$end = end($science);
		if ($end->where != "Global" && $end->asteroid =="")
		{
			$matches[2] == trim($matches[2]);
			if (in_array($matches[2],$bodiestranslated))
			{
				$bodies->translateBiomes[$end->where.$end->body] = $matches[1];
			}
			else if ($end->body == "Kerbin")
			{
				// exotic ksc biome TODO check for future biomes
				$bodies->translateBiomes[$end->where.$end->body] = $matches[2];
			}
		}
	}
	
	// end
	if (preg_match("@\\tSCENARIO@",$l) && $foundScience)
	{
		break;
	}
		
}
fclose($f);

?>
<!DOCTYPE html>
<html>
<head>
<title>Kerbal science</title>
<style type='text/css'>
button
{
	font-size:25pt;
	background-color: white;
}
.selectedBody {
	font-weight: bold;
	background-color: grey;
}
.found {
	background-color:green;
	text-align: center;
	height: 20px;
}

.global {
	background-color:yellow;
}

table {
    border-collapse: collapse;
}

table, th, td {
    border: 1px solid black;
}
.impossibleExp {
	background-color: orangered;
	text-align: center;
	height: 20px;
}
</style>
<script type='text/javascript'>
var currBody = "<?php echo @$_GET["body"]; ?>";
function loaded() {
	var checkboxes = document.querySelectorAll('input[type=checkbox]');
	if (checkboxes.length != 0)
	{
		checkboxes[0].addEventListener("change",toggleksc);
	}
	if (currBody == "")
	{
			currBody = "Kerbin";	
	}
	showBody(currBody);
	
}

function toggleksc() {
	var showtype = document.getElementById("showksc").checked ? "block":"none";
	console.log(showtype);
	var elts = document.getElementsByClassName("kscbiome");
	for(i=0; i< elts.length; i++)
	{
		elts[i].style.display = showtype;
	}
}

function showBody(body) {
	var bodies = document.getElementsByClassName("body");
	for (i=0; i< bodies.length; i++)
	{
		bodies[i].style.display = "none";
	}
	var btns = document.getElementsByTagName('button');
	for (i=0; i< btns.length; i++)
	{
		btns[i].classList.remove("selectedBody");
		if (btns[i].id == "btn"+body)
		{
			btns[i].classList.add("selectedBody");
		}
	}
	var myBody = document.getElementById("body"+body)
	{
		if (myBody) {
			myBody.style.display = "block";
			currBody = body;
		}
	}
}

function toggleJson() {
	var txtjson = document.getElementById("txtjson");
	txtjson.style.display = txtjson.style.display === 'none' ? '' : 'none';
}

</script>
</head>
<body onload='loaded()'>
<form method='get' action=''>
<?php

if (!isset($_GET["body"]))
{
	$_GET["body"] = "Mun";
}
echo "<input type='button' value='Toggle JSON' onclick='toggleJson()'/><br/><textarea style='display:none;' id='txtjson' rows=50 cols=200>".json_encode($science)."</textarea><br/>";
foreach($bodies->bodies as $b)
{
	if (in_array($b->name, $bodies->foundBodies))
	{
		$displayName = $b->name.(isset($b->filterName)?" (".$b->filterName.")":"");
		echo "<button id='btn".$displayName."' type='button' onclick='showBody(\"".$displayName."\")' name='body'>".$displayName."</button>";
	}
}
echo "</form>";

foreach($bodies->bodies as $currBody)
{
	$displayName = $currBody->name.(isset($currBody->filterName)?" (".$currBody->filterName.")":"");
	echo "<div class='body' id='body".$displayName."'>";

	if (isset($currBody->showBiomes))
	{
		$currScience = from($science)->where('$v->body == $GLOBALS["currBody"]->name && in_array($v->where,$GLOBALS["currBody"]->showBiomes) && $v->where != "Global" && $v->asteroid == ""')->toList();
	}
	else
	{
		$currScience = from($science)->where('$v->body == $GLOBALS["currBody"]->name && $v->asteroid == ""')->toList();
	}
	// recoveries
	$recovline = "";
	echo "<h3>Recovery</h3>";
	echo "<table border=1 ><tr>";
	foreach($bodies->recoveries as $r)
	{
		echo "<th>".$r."</th>";
		$recovline .= "<td>";
		
		$recovery = from($currScience)
			->where('$v->how == $GLOBALS["r"] && $v->body == $GLOBALS["currBody"]->name')
			->firstOrDefault();
		if ($recovery != null)
		{
			$recovline .= "<div class='found'>";
			if ($recovery->retrieved < $recovery->many)
			{
				$recovline .= $recovery->retrieved." / ";
			}
			$recovline .= $recovery->many."</div>";
		}
		
		$recovline .= "</td>";
	}
	echo "</tr>\n<tr>";
	echo $recovline."</tr></table>\n";
	
	// experiences
	if (isset($currBody->showBiomes))
	{
		$biomes = $currBody->showBiomes;
	}
	else
	{
		$biomes = $currBody->biomes;
	}
	foreach ($biomes as $currBiome)
	{
		if (isset($currBody->hideBiomes) && in_array($currBiome,$currBody->hideBiomes)) continue;
		$allSituations = array();
		
		if ($currBiome == "Global" && count($biomes)>1) continue;
		echo "<div><h3>".$currBiome.(LOCALE!=""?" (".@$bodies->translateBiomes[$currBiome.$currBody->name].")":"")."</h3>\n";
		echo "<table border=1 ><tr><th></th>";
		
		foreach ($bodies->foundScienceType as $i=>$foundScienceType)
		{
			if ($foundScienceType == "recovery") continue;
			echo "<th>".ucFirst(preg_replace("@([A-Z])@","<br/>\\1",$foundScienceType))."</th>";

			foreach($situationsList as $currSituation)
			{
				if (!isset($allSituations[$currSituation])) $allSituations[$currSituation] = "";
				$allSituations[$currSituation] .= "<td>";
				
				// forbidden experiences
				if (!$currBody->atmosphere &&  $foundScienceType == "atmosphereAnalysis")
				{
					$allSituations[$currSituation] .= "<div class='impossibleExp'></div></td>";
					continue;
				}
				
				if (from($bodies->situations)->where('$v->name == $GLOBALS["currSituation"]')->any('in_array($GLOBALS["foundScienceType"],$v->notSciences)'))
				{
					$allSituations[$currSituation] .= "<div class='impossibleExp'></div></td>";
					continue;
				}
				
				// find science
				if (!isset($currBody->showBiomes))
				{
					$foundScience = from($currScience)
						->where('$v->how == $GLOBALS["currSituation"] && $v->what == $GLOBALS["foundScienceType"] && ($v->where == $GLOBALS["currBiome"] || $v->where == "Global")')
						->firstOrDefault();
				}
				else
				{
					$foundScience = from($currScience)
						->where('$v->how == $GLOBALS["currSituation"] && $v->what == $GLOBALS["foundScienceType"] && $v->where == $GLOBALS["currBiome"]')
						->firstOrDefault();
				}
				
				if ($foundScience != null)
				{
					$allSituations[$currSituation] .= "<div class='found ".($foundScience->where=="Global"?"global":"")."'>";
						
					if ($foundScience->retrieved != $foundScience->many) {
						$allSituations[$currSituation] .= $foundScience->retrieved." / ";
					} 
					$allSituations[$currSituation] .= $foundScience->many;
					if ($foundScience->retrieved == 0)
					{
						$allSituations[$currSituation] .= "!!";
					}
					$allSituations[$currSituation] .= "</div>";
				}
				$allSituations[$currSituation] .= "</td>";
			}
		}
		
		echo "</tr>\n";

		// display table
		foreach($bodies->situations as $currSituation)
		{
			$sitName = $currSituation->name;
			
			if ($sitName=="SrfSplashed" && !$currBody->water || preg_match("@^Flying@",$sitName) && !$currBody->atmosphere || preg_match("@^Srf@",$sitName) && !$currBody->landable) continue;
			
			echo "<tr><td>".$sitName;
			switch($sitName) 
			{
				case "SrfLanded":
				case "SrfSplashed": 
					echo " (0 km)";
					break;
				case "FlyingLow": 
					echo " (&lt; ".$currBody->atmosphereLimit[0]." km)";
					break;
				case "FlyingHigh": 
					echo " (&lt; ".$currBody->atmosphereLimit[1]." km)";
					break;
				case "InSpaceLow": 
					echo " (&lt; ".$currBody->spaceLimit." km)";
					break;
				case "InSpaceHigh": 
					echo " (&gt; ".$currBody->spaceLimit." km)";
					break;
			}
			echo "</td>";
			echo $allSituations[$sitName];
			echo "</tr>\n";
		}

		echo "</table></div>\n";
	}
	echo "<h2>Asteroids</h2>\n";
	if (isset($currBody->showBiomes))
	{
		$foundScience = from($science)
			->where('$v->body == $GLOBALS["currBody"]->name && in_array($v->where,$GLOBALS["currBody"]->showBiomes) && $v->what == "asteroidSample"')->orderBy('$v->asteroid')->thenBy('$v->where')->toList();
			
	}
	else
	{
		$foundScience = from($science)
			->where('$v->body == $GLOBALS["currBody"]->name && $v->what == "asteroidSample"')->orderBy('$v->asteroid')->thenBy('$v->where')->toList();

	}
	$currAsteroid = "";
	foreach($foundScience as $i => $v) 
	{
		if ($v->asteroid != $currAsteroid)
		{
			$currAsteroid = $v->asteroid;
			echo "<h3>".$v->asteroid."</h3>\n";
		}
		echo $v->where." / ".$v->how." : ".$v->retrieved."/".$v->many."<br/>\n";
	}
	echo "</div>\n";
}
?>
</body>
</html>