<?php
include "config.inc.php";

require_once 'vendor/autoload.php';
use \YaLinqo\Enumerable;

define("DEBUG",true);

function debug($txt)
{
	if (DEBUG)
	{
		echo $txt."<br/>\n";
	}
}


$situations = Array();
$situations[] = "Flyby";
$situations[] = "Orbit";
$situations[] = "Flight";
$situations[] = "Land";
$situations[] = "PlantFlag";

$baseXp = Array();
$baseXp[] = 1;
$baseXp[] = 1.5;
$baseXp[] = 2;
$baseXp[] = 2.3;
$baseXp[] = 2.5;


$stepsXp = Array();
$stepsXp[] = 0;
$stepsXp[] = 2;
$stepsXp[] = 8;
$stepsXp[] = 16;
$stepsXp[] = 32;
$stepsXp[] = 64;

class Kerbal {
	public $name;
	public $trait;
	public $xp;
	public $pendingXp;
	public $careerLog;
	public $flightLog;
	public $extraXP;
}

function situationXp($bodyparam, $activity)
{
	global $idBody;
	$idBody	= $bodyparam;
	if ($bodyparam ==" Kerbin")
	{
		$activity = 4 -$activity;
	}
	$body = @from($GLOBALS["bodies"]->bodies)->First('$v->name == $GLOBALS["idBody"]');
	if ($body->name == "Kerbin")
	{
		return $body->xpSpecial[$activity];
	}
	return $GLOBALS["baseXp"][$activity]* $body->xpMultiplier;
	
}

function starsByXp($xp)
{
	$out = "";
	foreach($GLOBALS["stepsXp"] as $i => $s)
	{
		if ($i == 0) continue;
		if ($s <= $xp)
		{
			$out .= "X";
		}
		else
		{
			$out .= "O";
		}
	}
	return $out;
}

$bodies = json_decode(file_get_contents("bodies.json"));

?>
<!DOCTYPE html>
<html>
<head>
<title>Kerbal XP</title>
<style type='text/css'>
table { text-align:center; }
.contracttitle 
{
	text-align:left;
}
.cell
{
	width: 60px;
}
td.Complete 
{
	background-color:green;
}
span.Complete
{
	text-decoration: line-through;
}
td.Incomplete
{
	background-color:orange;
}
td.dangerous
{
	border:3px solid red;
}
span.dangerous
{
	font-weight:bold;
}
.Offered
{
	background-color:grey;
}
.Active
{
	background-color:orange;
}

td:nth-child(5n+1)
{
	border-right:solid black 2px;
}

</style>
<script type='text/javascript'>
function loaded()
{
	
}
</script>
</head>
<body onload='loaded()'>
<?php
if (!defined("SAVEFILE"))
{
	echo "
	<a href='https://github.com/Goufalite/kerbalscience' target='_NEW'>Kerbal Science on Github</a><br/>
<form enctype='multipart/form-data' action='' method='post'>
  <input type='hidden' name='MAX_FILE_SIZE' value='12000000' />
  Upload your persistent.sfs save file : <input name='savefile' type='file' />
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
		if (isset($_GET["save"]) && defined("STEAMPATH"))
		{
			$f = fopen(STEAMPATH.$_GET["save"]."/persistent.sfs","r");
		}
		else
		{
			$f = fopen(SAVEFILE,"r");
		}
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

$kerbals = Array();
$inkerbal = false;
$incareerlog = false;
$inflightlog = false;

while ($l = fgets($f))
{
	if (preg_match("/KERBAL$/",trim($l)))
	{

		// full save
		if (isset($kerbal))
		{
			$kerbal->careerLog = isset($careerLog) ? $careerLog : Array();
			$kerbal->xp = 0;
			foreach ($kerbal->careerLog as $b => $a)
			{
				$kerbal->xp += situationXp($b,$a);
			}
			if (!isset($flightLog)) { $flightLog = Array(); }
			$flightBodies = array_keys($flightLog);
			$kerbal->flightLog = $flightLog;
			$kerbal->pendingXp = 0;
			foreach ($kerbal->flightLog as $b => $a)
			{
				$carXp = 0;
				if (array_key_exists($b, $careerLog))
				{
					$carXp = situationXp($b,$careerLog[$b]);
				}
				$kerbal->pendingXp += (situationXp($b,$a)-$carXp);
			}
			$kerbals[] = $kerbal;
			unset($careerLog);
			unset($flightLog);
		}
		$incareerlog = false;
		$inflightlog = false;
		$inkerbal = true;
		$kerbal = new Kerbal();
	}
	if (preg_match("/name = (.*)/",$l,$matches) && $inkerbal && $kerbal->name == null)
	{
		$kerbal->name = trim($matches[1]);
	}
	if (preg_match("/trait = ([A-Za-z]+)/",$l,$matches) && $inkerbal)
	{
		$kerbal->trait = $matches[1];
	}
	if (preg_match("/type = ([A-Za-z]+)/",$l,$matches) && $inkerbal)
	{
		if ($matches[1]!="Crew")
		{
			unset($kerbal);
			$inkerbal = false;
		}
	}
	if (preg_match("/extraXp = ([0-9.]+)/",$l,$matches) && $inkerbal)
	{
		$kerbal->extraXp = $matches[1];
	}
	
	// CAREER_LOG
	if (preg_match("/CAREER_LOG$/",trim($l)))
	{
		$incareerlog = true;
		$careerLog = Array();
	}
	
	// CAREER_LOG
	if (preg_match("/FLIGHT_LOG$/",trim($l)))
	{
		$inflightlog = true;
		$incareerlog = false;
		$flightLog = Array();
	}
	
	if (preg_match("/\\d = ([A-Za-z0-9\-]+),([A-Za-z0-9\-]+)/",$l,$matches) && $inkerbal && ($incareerlog || $inflightlog))
	{
		$sit = $matches[1];
		$sitId = array_search($sit,$situations);
		$body = $matches[2];
		if ($body == "Kerbin")
		{
			$sitId = 4-$sitId;
		}
		if ($sitId !== false)
		{
			if ($incareerlog)
			{
				if (!key_exists($body, $careerLog) || (key_exists($body, $careerLog) && $careerLog[$body] < $sitId))
				{
					$careerLog[$body] = $sitId;
				}
				
			}
			else
			{
				if (!key_exists($body, $flightLog) || (key_exists($body, $flightLog) && $flightLog[$body] < $sitId))
				{
					$flightLog[$body] = $sitId;
				}
			}
		}
	}
	

	
}
if (isset($kerbal))
{
	$kerbal->careerLog = $careerLog;
	$kerbal->xp = 0;
	foreach ($kerbal->careerLog as $b => $a)
	{
		$kerbal->xp += situationXp($b,$a);
	}
	$kerbal->flightLog = $flightLog;
	$kerbal->pendingXp = 0;
	foreach ($kerbal->flightLog as $b => $a)
	{
		$carXp = 0;
		if (array_key_exists($b, $careerLog))
		{
			$carXp = situationXp($b,$careerLog[$b]);
		}
		$kerbal->pendingXp += (situationXp($b,$a)-$carXp);
	}
	$kerbals[] = $kerbal;
	unset($careerLog);
	unset($flightLog);
}


foreach($kerbals as $k)
{
	echo "<hr/>\n";
	echo "<b>".$k->name."</b><br/>\n";
	echo "<b>".$k->trait."</b>&nbsp;";
	echo starsByXp($k->xp);
	echo "(".$k->xp.") / Pending : ".starsByXp($k->xp+$k->pendingXp)." (".($k->xp+$k->pendingXp).")<br/>\n";
	echo "<table style='width:100%;' border=0><tr><td style='text-align:left;width:50%'>";
	foreach($k->careerLog as $b =>$a)
	{
		echo $b." : ".$situations[($b=="Kerbin"? 4-$a: $a)]." (".situationXp($b, $a).")<br/>\n";
	}
	echo "</td><td style='text-align:left;'>";
	foreach($k->flightLog as $b => $a)
	{
		$carXp = 0;
		if (array_key_exists($b, $k->careerLog))
		{
			$carXp = situationXp($b,$k->careerLog[$b]);
		}
		echo $b." : ".$situations[($b=="Kerbin"? 4-$a: $a)]." (".(situationXp($b, $a)-$carXp).")<br/>\n";
	}
	echo "</td></tr></table>";
	
}

?>
</body>
</html>